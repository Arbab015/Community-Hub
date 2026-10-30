<?php

namespace App\Services\Property;

use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides which committed payment plans - and therefore which payments, plans
 * and transaction history - a viewer is allowed to see for a property or unit.
 *
 * Staff (society owner, society managers, admins) see every ownership the
 * property has ever had: the current one plus the full history.
 *
 * A "Society Member" only ever sees the plans tied to their OWN ownership:
 *   - the reservation plan the property was sold to them on,
 *   - the transfer-fee plan they were handed when the property changed hands,
 *   - and, read-only, the records of an ownership they have since passed on.
 * They never see the money of another owner.
 *
 * Every view and endpoint that reads payment data asks this service, so the
 * page and the JSON behind it can never disagree about who may see what.
 */
class PaymentVisibilityService
{
    /**
     * A society member is a buyer, not staff: their view is limited to their
     * own ownership records.
     */
    public function isRestricted(?User $viewer): bool
    {
        return $viewer !== null && $viewer->hasRole('Society Member');
    }

    /**
     * The one active ownership of a property/unit, whoever holds it.
     *
     * Read without any ownership filter, because a member also needs to know
     * that the property is no longer theirs.
     */
    public function activeOwnership(Property|PropertyUnit $ownerable): ?PropertyOwner
    {
        return $ownerable->ownerships()
            ->active()
            ->latest('id')
            ->first();
    }

    /**
     * Every committed plan of the ownerable the viewer may see, newest first.
     *
     * @return Collection<int, PropertyReservedPaymentPlan>
     */
    public function visiblePlans(Property|PropertyUnit $ownerable, ?User $viewer): Collection
    {
        return PropertyReservedPaymentPlan::query()
            ->whereMorphedTo('propertyOwnerable', $ownerable)
            ->when($this->isRestricted($viewer), fn ($q) => $q->whereHas(
                'propertyOwner',
                fn ($o) => $o->where('owner_id', $viewer->id)
            ))
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The ids of the plans the viewer may see for the given ownerable.
     *
     * @return list<int>
     */
    public function visiblePlanIds(Property|PropertyUnit $ownerable, ?User $viewer): array
    {
        return $this->visiblePlans($ownerable, $viewer)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Whether the viewer may read this plan's payments at all.
     */
    public function canViewPlan(?PropertyReservedPaymentPlan $plan, ?User $viewer): bool
    {
        if (! $plan) {
            return false;
        }

        if (! $this->isRestricted($viewer)) {
            return true;
        }

        return (int) ($plan->propertyOwner?->owner_id ?? 0) === (int) $viewer->id;
    }

    /**
     * Stop the request unless the viewer may read this plan's payments.
     */
    public function authorizePlan(?PropertyReservedPaymentPlan $plan, ?User $viewer): void
    {
        abort_unless($this->canViewPlan($plan, $viewer), 403);
    }
}
