<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\Block;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The navbar property search.
 *
 * The user types a property name, a property number or an owner name and gets the matching
 * properties back; clicking a result opens the property itself.
 *
 * The search never leaves the viewer's own world:
 *   - a Super Admin sees every property,
 *   - staff (a society owner / the roles that owner created) sees the properties of the
 *     societies they own,
 *   - a member only ever sees the properties they own themselves.
 */
class PropertyQuickSearchController extends Controller
{
    /** How many properties a single search returns at most. */
    private const LIMIT = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('term', ''));

        if ($term === '') {
            return response()->json([
                'term' => '',
                'results' => [],
            ]);
        }

        $user = $request->user();

        $query = Property::query()
            ->with('block.society')
            ->with('owner')
            ->with(['ownerships' => fn ($q) => $q->active()->with('owner')]);

        $this->scopeToViewer($query, $user);

        $like = '%'.$term.'%';

        $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'LIKE', $like)
                ->orWhere('property_no', 'LIKE', $like)
                ->orWhereHas('owner', fn ($o) => $this->matchOwnerName($o, $like))
                ->orWhereHas('ownerships', fn ($o) => $o->active()
                    ->whereHas('owner', fn ($u) => $this->matchOwnerName($u, $like)));
        });

        $properties = $query->orderBy('name')->limit(self::LIMIT)->get();

        return response()->json([
            'term' => $term,
            'results' => $properties
                ->map(fn (Property $property) => $this->result($property, $user))
                ->values(),
        ]);
    }

    /**
     * Restrict the search to the properties the viewer is allowed to open.
     */
    private function scopeToViewer(Builder $query, User $user): void
    {
        if ($user->hasRole('Super Admin')) {
            return;
        }

        $scope = SocietyAccessResolver::resolver($user);

        if ($scope['isSocietyScoped']) {
            $query->whereIn('block_id', Block::query()
                ->select('id')
                ->whereIn('society_id', $scope['ownedSocietyIds']));

            return;
        }

        // A member may only find their own properties.
        $query->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhereHas('ownerships', fn ($o) => $o->active()->where('owner_id', $user->id));
        });
    }

    /**
     * Match the first name, the last name or both of an owner.
     */
    private function matchOwnerName(Builder $query, string $like): void
    {
        $query->where(function (Builder $q) use ($like) {
            $q->where('first_name', 'LIKE', $like)
                ->orWhere('last_name', 'LIKE', $like)
                ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$like]);
        });
    }

    /**
     * The payload of a single search result.
     */
    private function result(Property $property, User $user): array
    {
        $ownership = $property->ownerships->first();
        $owner = $property->owner ?: $ownership?->owner;
        $society = $property->block?->society;

        return [
            'uuid' => $property->uuid,
            'name' => $property->name ?: ucfirst((string) $property->type),
            'property_no' => $property->property_no,
            'type' => $property->type,
            'category' => $property->category,
            'owner_name' => $owner?->full_name,
            'society_name' => $society?->name,
            'block_name' => $property->block?->name,
            'location' => $this->location($property),
            'url' => $this->detailsUrl($property, $user),
        ];
    }

    /**
     * A single line describing where the property is.
     */
    private function location(Property $property): string
    {
        return trim(implode(', ', array_filter([
            $property->street,
            $property->block?->name,
            $property->block?->society?->name,
        ])));
    }

    /**
     * The page the property is opened on.
     */
    private function detailsUrl(Property $property, User $user): string
    {
        $society = $property->block?->society;

        if (! $society) {
            return route('property.details', $property->uuid);
        }

        if ($this->isMember($user)) {
            return route('owner.property.details', $property->uuid);
        }

        $userType = $user->hasRole('Society Owner') ? 'society_owners' : 'society_managers';

        return route('society.property.details', [$userType, $society->uuid, $property->uuid]);
    }

    /**
     * A member is a buyer: they only ever work with their own property.
     */
    private function isMember(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return false;
        }

        return ! SocietyAccessResolver::resolver($user)['isSocietyScoped'];
    }
}
