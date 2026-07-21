<?php

namespace App\Http\Controllers\frontend;

use App\Helpers\GetArea;
use App\Http\Controllers\frontend\concerns\ResolvesVisitor;
use App\Jobs\SendContactMessageMail;
use App\Models\Floor;
use App\Models\Message;
use App\Models\Property;
use App\Models\PropertyAttribute;
use App\Models\PropertyInquiry;
use App\Models\PropertyUnit;
use App\Models\VisitorFavourite;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class landingPageController
{
    use ResolvesVisitor;

    private const PER_PAGE = 8;

    private function inquiriesByListing($visitor)
    {
        if (! $visitor) {
            return collect();
        }

        return PropertyInquiry::where('visitor_id', $visitor->id)
            ->get(['listing_id', 'name', 'email', 'contact_number', 'message'])
            ->keyBy('listing_id');
    }

    private function paginateCollection($collection, Request $request, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        return (new LengthAwarePaginator($items, $collection->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]))->appends($request->query());
    }

    public function index()
    {
        $visitor = $this->getOrCreateVisitor();

        return view('frontend.content.landing-page', [
            'pageConfigs' => ['myLayout' => 'front'],
            'unitTypes' => PropertyAttribute::where('type', 'unit_type')->get(['id', 'title']),
            'favouritedKeys' => $this->favouritedKeys($visitor),
            'inquiriesByListing' => $this->inquiriesByListing($visitor),

        ]);
    }

    public function findProperty(Request $request)
    {
        $visitor = $this->getOrCreateVisitor();
        try {
            $areaUnit = $request->input('area_unit', 'sqft');
            $currency = strtoupper($request->input('currency', 'PKR'));
            $listingTypeValue = $request->input('listing_type', 'sell') === 'rent' ? 'rent' : 'sell';
            $currencyRates = $this->getCurrencyRates();
            $rate = $currencyRates[$currency] ?? 1;
            $areaLabels = ['sqft' => 'Sq Ft', 'marla' => 'Marla', 'kanal' => 'Kanal'];

            $isUnitSearch = $request->filled('unit_type');

            if ($isUnitSearch) {
                $properties = $this->searchUnits($request, $listingTypeValue, $rate);
            } else {
                $properties = $this->searchAll($request, $listingTypeValue, $rate)
                    ->concat($this->searchUnits($request, $listingTypeValue, $rate));
            }

            if ($request->filled('floors') && $request->input('floors') !== 'All') {
                $properties = $properties->filter(
                    fn ($p) => $this->matchesCountFilter($this->floorsCountFor($p), $request->input('floors'))
                );
            }

            if ($request->filled('rooms') && $request->input('rooms') !== 'All') {
                $properties = $properties->filter(
                    fn ($p) => $this->matchesCountFilter($this->roomsCountFor($p), $request->input('rooms'))
                );
            }

            $rawMarlaSize = (float) ($properties->first()?->block?->society?->marla_size ?? 0);
            $marlaSize = $rawMarlaSize > 0 ? $rawMarlaSize : 272.25;
            $areaFactor = ['sqft' => 1, 'marla' => $marlaSize, 'kanal' => $marlaSize * 20][$areaUnit] ?? 1;

            if ($request->filled('min_area')) {
                $properties = $properties->filter(fn ($p) => ($p->unit_area_sqft ?? GetArea::calculate($p->dimensions)) >= ($request->min_area * $areaFactor)
                );
            }
            if ($request->filled('max_area')) {
                $properties = $properties->filter(fn ($p) => ($p->unit_area_sqft ?? GetArea::calculate($p->dimensions)) <= ($request->max_area * $areaFactor)
                );
            }

            return view('frontend.content.properties', [
                'properties' => $this->paginateCollection($properties->values(), $request),
                'pageConfigs' => ['myLayout' => 'front'],
                'currency' => $currency,
                'areaUnit' => $areaUnit,
                'areaLabel' => $areaLabels[$areaUnit] ?? 'Sq Ft',
                'rate' => $rate,
                'areaFactor' => $areaFactor,
                'currencyRates' => $currencyRates,
                'searchInputs' => $request->only(['listing_type', 'society', 'property_name', 'location', 'category', 'unit_type', 'type', 'city', 'country', 'min_price', 'max_price', 'min_area', 'max_area', 'currency', 'area_unit', 'rooms', 'floors']),
                'unitTypes' => PropertyAttribute::where('type', 'unit_type')->get(['id', 'title']),
                'isUnitSearch' => $isUnitSearch,
                'favouritedKeys' => $this->favouritedKeys($visitor),
                'inquiriesByListing' => $this->inquiriesByListing($visitor),
            ]);

        } catch (\Exception $e) {
            return view('frontend.content.properties', [
                'properties' => $this->paginateCollection(collect(), $request),
                'pageConfigs' => ['myLayout' => 'front'],
                'currency' => $request->input('currency', 'PKR'),
                'areaUnit' => $request->input('area_unit', 'sqft'),
                'areaLabel' => 'Sq Ft',
                'rate' => 1,
                'areaFactor' => 1,
                'currencyRates' => ['PKR' => 1],
                'searchInputs' => $request->only(['listing_type', 'society', 'property_name', 'location', 'category', 'unit_type', 'type', 'city', 'country', 'min_price', 'max_price', 'min_area', 'max_area', 'currency', 'area_unit', 'rooms', 'floors']),
                'unitTypes' => PropertyAttribute::where('type', 'unit_type')->get(['id', 'title']),
                'isUnitSearch' => false,
                'favouritedKeys' => collect(),
            ])->with('error', $e->getMessage());
        }
    }

    private function favouritedKeys($visitor)
    {
        if (! $visitor) {
            return collect();
        }

        return VisitorFavourite::where('visitor_id', $visitor->id)
            ->get(['favouritable_id', 'favouritable_type'])
            ->map(fn ($f) => $f->favouritable_type.':'.$f->favouritable_id);
    }

    private function searchUnits(Request $request, string $listingTypeValue, float $rate)
    {
        $query = PropertyUnit::query()
            ->with([
                'attachment', 'listing', 'dimensions', 'rooms',
                'floor.property', 'floor.property.block.society', 'floor.property.dimensions',
                'floor.property.floors',
            ])
            ->where('unit_type', $request->unit_type)
            ->whereHas('listing', fn ($q) => $q->where('listing_type', $listingTypeValue));

        $this->applyUnitFilters($query, $request, $rate);

        return $query->get()->map(fn ($unit) => $this->mapUnit($unit))->filter()->values();
    }

    private function searchAll(Request $request, string $listingTypeValue, float $rate)
    {
        $propQuery = Property::query()
            ->with([
                'block.society', 'attachment', 'dimensions', 'listing',
                'floors.rooms', 'floors.units.rooms',
            ])
            ->whereHas('listing', fn ($q) => $q->where('listing_type', $listingTypeValue));

        $this->applyPropertyFilters($propQuery, $request, $rate);
        $properties = $propQuery->get();

        $properties->each(function ($property) {
            $property->floors_count = $property->floors->count();
        });

        $unitQuery = PropertyUnit::query()
            ->with([
                'attachment', 'listing', 'dimensions', 'rooms',
                'floor.property', 'floor.property.block.society', 'floor.property.dimensions', 'floor.property.listing',
                'floor.property.floors',
            ])
            ->whereHas('listing', fn ($l) => $l->where('listing_type', $listingTypeValue));
        $this->applyUnitFilters($unitQuery, $request, $rate);

        $unitProperties = $unitQuery->get()->map(fn ($unit) => $this->mapUnit($unit))->filter()->values();
        Log::info('unit search debug', [
            'raw_units_matched' => $unitQuery->count(),
            'after_map_filter' => $unitProperties->count(),
        ]);

        return $properties->concat($unitProperties);
    }

    private function mapUnit(PropertyUnit $unit): ?Property
    {
        $property = $unit->floor?->property;
        if (! $property) {
            return null;
        }

        $property = clone $property;
        if ($unit->attachment) {
            $property->setRelation('attachment', $unit->attachment);
        }

        $property->setRelation('listing', $unit->listing ?? $property->listing);
        $property->matched_unit_uuid = $unit->uuid;
        $property->matched_unit_id = $unit->id;
        $property->unit_area_sqft = GetArea::calculate($unit->dimensions);
        $property->unit_rooms_count = $unit->rooms->count();
        $property->matched_unit_name = $unit->unit_name;
        $property->matched_unit_type_title = PropertyAttribute::where('id', $unit->unit_type)->value('title') ?? 'Unit';

        $floorContext = $this->floorContext($unit->floor);
        $property->matched_floor_title = $floorContext['title'];

        return $property;
    }

    private function floorContext(?Floor $floor): array
    {
        if (! $floor) {
            return ['title' => null];
        }

        $allFloors = ($floor->relationLoaded('property') && $floor->property?->relationLoaded('floors'))
          ? $floor->property->floors
          : collect([$floor]);

        $ordered = $allFloors->sortBy('id')->values();
        $position = $ordered->search(fn ($f) => $f->id === $floor->id);

        return [
            'title' => $this->floorAttributeTitle($floor->floor_type) ?? 'Floor',
        ];
    }

    private function floorAttributeTitle($floorTypeId): ?string
    {
        if (! $floorTypeId) {
            return null;
        }

        return PropertyAttribute::where('id', $floorTypeId)->value('title');
    }

    private function matchesCountFilter(int $count, string $filterValue): bool
    {
        if (str_ends_with($filterValue, '+')) {
            return $count >= (int) rtrim($filterValue, '+');
        }

        return $count === (int) $filterValue;
    }

    private function floorsCountFor(Property $property): int
    {
        if (isset($property->floors_count)) {
            return $property->floors_count;
        }

        return $property->relationLoaded('floors') ? $property->floors->count() : 0;
    }

    private function roomsCountFor(Property $property): int
    {
        if (isset($property->matched_unit_id)) {
            return $property->unit_rooms_count ?? 0;
        }

        if (! $property->relationLoaded('floors')) {
            return 0;
        }

        return $property->floors->sum(function ($floor) {
            $floorRooms = $floor->relationLoaded('rooms') ? $floor->rooms->count() : 0;
            $unitRooms = $floor->relationLoaded('units')
              ? $floor->units->sum(fn ($u) => $u->relationLoaded('rooms') ? $u->rooms->count() : 0)
              : 0;

            return $floorRooms + $unitRooms;
        });
    }

    private function applyPropertyFilters($query, Request $request, float $rate): void
    {
        if ($request->filled('society')) {
            $query->whereHas('block.society', fn ($q) => $q->where('name', 'LIKE', '%'.$request->society.'%'));
        }
        if ($request->filled('property_name')) {
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$request->property_name}%")->orWhere('property_no', 'LIKE', "%{$request->property_name}%"));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('type')) {
            $query->where('type', 'LIKE', "%{$request->type}%");
        }
        if ($request->filled('city')) {
            $query->whereHas('block.society', fn ($q) => $q->where('city', 'LIKE', '%'.$request->city.'%'));
        }
        if ($request->filled('country')) {
            $query->whereHas('block.society', fn ($q) => $q->where('country', 'LIKE', '%'.$request->country.'%'));
        }
        if ($request->filled('min_price')) {
            $query->whereHas('listing', fn ($q) => $q->where('price', '>=', $request->min_price * $rate));
        }
        if ($request->filled('max_price')) {
            $query->whereHas('listing', fn ($q) => $q->where('price', '<=', $request->max_price * $rate));
        }
        if ($request->filled('location')) {
            $query->where(fn ($q) => $q
                ->where('street', 'LIKE', "%{$request->location}%")
                ->orWhere('landmark', 'LIKE', "%{$request->location}%")
                ->orWhereHas('block', fn ($b) => $b->where('name', 'LIKE', "%{$request->location}%"))
                ->orWhereHas('block.society', fn ($s) => $s->where('city', 'LIKE', "%{$request->location}%"))
            );
        }
    }

    private function applyUnitFilters($query, Request $request, float $rate): void
    {
        if ($request->filled('society')) {
            $query->whereHas('floor.property.block.society', fn ($q) => $q->where('name', 'LIKE', '%'.$request->society.'%'));
        }
        if ($request->filled('property_name')) {
            $query->whereHas('floor.property', fn ($q) => $q->where('name', 'LIKE', "%{$request->property_name}%")->orWhere('property_no', 'LIKE', "%{$request->property_name}%"));
        }
        if ($request->filled('category')) {
            $query->whereHas('floor.property', fn ($q) => $q->where('category', $request->category));
        }
        if ($request->filled('type')) {
            $query->whereHas('floor.property', fn ($q) => $q->where('type', 'LIKE', "%{$request->type}%"));
        }
        if ($request->filled('city')) {
            $query->whereHas('floor.property.block.society', fn ($q) => $q->where('city', 'LIKE', '%'.$request->city.'%'));
        }
        if ($request->filled('country')) {
            $query->whereHas('floor.property.block.society', fn ($q) => $q->where('country', 'LIKE', '%'.$request->country.'%'));
        }
        if ($request->filled('min_price')) {
            $query->whereHas('listing', fn ($q) => $q->where('price', '>=', $request->min_price * $rate));
        }
        if ($request->filled('max_price')) {
            $query->whereHas('listing', fn ($q) => $q->where('price', '<=', $request->max_price * $rate));
        }
        if ($request->filled('location')) {
            $query->whereHas('floor.property', fn ($q) => $q
                ->where('street', 'LIKE', "%{$request->location}%")
                ->orWhere('landmark', 'LIKE', "%{$request->location}%")
                ->orWhereHas('block', fn ($b) => $b->where('name', 'LIKE', "%{$request->location}%"))
                ->orWhereHas('block.society', fn ($s) => $s->where('city', 'LIKE', "%{$request->location}%"))
            );
        }
    }

    private function getCurrencyRates(): array
    {
        $rates = ['PKR' => 1];
        try {
            $response = Http::timeout(5)->get('https://open.er-api.com/v6/latest/PKR');
            if ($response->successful()) {
                foreach (['USD', 'EUR', 'GBP', 'AED', 'SAR', 'CAD', 'AUD'] as $curr) {
                    $r = $response->json("rates.{$curr}");
                    if (is_numeric($r) && $r > 0) {
                        $rates[$curr] = 1 / $r;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return $rates;
    }

    private function buildFloorDetails($floors, $roomTypeAttrs, $unitTypeAttrs)
    {
        return $floors->sortBy('id')->values()->map(function ($floor) use ($roomTypeAttrs, $unitTypeAttrs) {

            $unitGroups = $floor->units
                ->groupBy(function ($unit) {
                    $roomsSignature = $unit->rooms
                        ->map(fn ($r) => $r->room_type.':'.$r->dimensions
                            ->map(fn ($d) => strtolower($d->name).':'.round($d->size, 4).':'.$d->unit)
                            ->sort()->implode('|'))
                        ->sort()->implode(',');

                    return $unit->unit_type.'::'.$roomsSignature;
                })
                ->map(function ($units) use ($unitTypeAttrs, $roomTypeAttrs) {
                    $sample = $units->first();
                    $hasRooms = $sample->rooms->isNotEmpty();

                    return [
                        'unit_type_title' => $unitTypeAttrs->firstWhere('id', $sample->unit_type)?->title ?? 'Unit',
                        'count' => $units->count(),
                        'rooms' => $hasRooms
                          ? $this->buildRoomBreakdown($sample->rooms, $roomTypeAttrs)
                          : collect(),
                        'dimensions' => $hasRooms
                          ? collect()
                          : $sample->dimensions->map(function ($d) {
                              $converted = app(\App\Http\Controllers\PropertiesController::class)
                                  ->convertForUser($d->size, $d->unit);

                              return [
                                  'name' => ucfirst($d->name),
                                  'value' => round($converted, 2),
                                  'unit' => ucfirst($d->unit),
                              ];
                          })->values(),
                    ];
                })
                ->values();

            return [
                'title' => $this->floorAttributeTitle($floor->floor_type) ?? 'Floor',
                'units' => $unitGroups,
                'rooms' => $this->buildRoomBreakdown($floor->rooms, $roomTypeAttrs),
            ];
        })->values();
    }

    private function buildRoomBreakdown($rooms, $roomTypeAttrs)
    {
        return $rooms->groupBy('room_type')->map(function ($group, $typeId) use ($roomTypeAttrs) {
            $title = $roomTypeAttrs->firstWhere('id', $typeId)?->title ?? 'Room';

            $variants = $group->groupBy(function ($room) {
                return $room->dimensions
                    ->map(fn ($d) => strtolower($d->name).':'.round($d->size, 4).':'.$d->unit)
                    ->sort()
                    ->implode('|');
            });

            return [
                'title' => ucwords($title),
                'total' => $group->count(),
                'variants' => $variants->map(function ($variantRooms) {
                    $sample = $variantRooms->first();

                    return [
                        'count' => $variantRooms->count(),
                        'dimensions' => $sample->dimensions->map(function ($d) {
                            $converted = app(\App\Http\Controllers\PropertiesController::class)
                                ->convertForUser($d->size, $d->unit);

                            return [
                                'name' => ucfirst($d->name),
                                'value' => round($converted, 2),
                                'unit' => ucfirst($d->unit),
                            ];
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();
    }

    /**
     * Shared data needed by both the property show() and unit showUnit() pages:
     * images, attribute lists, currency, geocode fallback tiers, rooms/amenities, inquiries/favourites.
     */
    private function commonListingData(Request $request, $visitor, $attachments, $parentProperty, $block, $society, $rooms, $amenityIds): array
    {
        $attrs = PropertyAttribute::orderBy('created_at')->get();

        $geocodeQueries = collect([
            collect([$parentProperty->name ?: $parentProperty->property_no, $parentProperty->street, $block?->name, $society?->name, $society?->city, 'Pakistan']),
            collect([$parentProperty->name ?: $parentProperty->property_no, $block?->name, $society?->name, $society?->city, 'Pakistan']),
            collect([$parentProperty->name ?: $parentProperty->property_no, $society?->name, $society?->city, 'Pakistan']),
            collect([$parentProperty->street, $block?->name, $society?->name, $society?->city, 'Pakistan']),
            collect([$parentProperty->street, $society?->name, $society?->city, 'Pakistan']),
            collect([$block?->name, $society?->name, $society?->city, 'Pakistan']),
            collect([$society?->name, $society?->city, 'Pakistan']),
            collect([$society?->city, 'Pakistan']),
        ])->map(fn ($p) => $p->filter()->implode(', '))->filter()->unique()->values()->all();

        return [
            'images' => $attachments->filter(fn ($a) => in_array(strtolower($a->extension ?? ''), ['jpg', 'jpeg', 'png', 'gif', 'svg', 'mp4', 'mov', 'avi', 'webm', 'mkv']))->values(),
            'attrs' => $attrs,
            'roomTypes' => $attrs->where('type', 'room_type'),
            'currency' => strtoupper($request->input('currency', 'PKR')),
            'currencyRates' => $this->getCurrencyRates(),
            'geocodeQueries' => $geocodeQueries,
            'fallbackCountry' => $society?->country ?: 'Pakistan',
            'roomBreakdown' => $this->buildRoomBreakdown($rooms, $attrs->where('type', 'room_type')),
            'amenityList' => $attrs->where('type', 'amenity')->whereIn('id', $amenityIds),
            'inquiriesByListing' => $this->inquiriesByListing($visitor),
            'favouritedKeys' => $this->favouritedKeys($visitor),
        ];
    }

    public function show(Request $request, $uuid)
    {
        $visitor = $this->getOrCreateVisitor();

        $property = Property::where('uuid', $uuid)
            ->with(['block.society', 'attachments', 'dimensions', 'listing', 'floors.units.dimensions', 'floors.units.rooms', 'floors.rooms'])
            ->firstOrFail();

        $allRooms = $property->floors->flatMap(fn ($f) => $f->rooms)->merge($property->floors->flatMap(fn ($f) => $f->units->flatMap(fn ($u) => $u->rooms)));

        $allAmenityIds = $allRooms->flatMap(fn ($r) => is_array($r->amenities) ? $r->amenities : json_decode($r->amenities ?? '[]', true))
            ->merge($property->floors->flatMap(fn ($f) => $f->units)->filter(fn ($u) => $u->rooms->isEmpty())
                ->flatMap(fn ($u) => is_array($u->amenities) ? $u->amenities : json_decode($u->amenities ?? '[]', true))
            )->unique()->values();

        $unitTypeCounts = $property->floors->flatMap(fn ($f) => $f->units)->groupBy('unit_type')->map->count();

        $common = $this->commonListingData($request, $visitor, $property->attachments, $property, $property->block, $property->block?->society, $allRooms, $allAmenityIds);

        return view('frontend.content.listing-details', array_merge($common, [
            'pageConfigs' => ['myLayout' => 'front'],
            'entity' => $property,
            'type' => 'property',
            'listing' => $property->listing,
            'society' => $property->block?->society,
            'areaSqft' => GetArea::calculate($property->dimensions),
            'marlaSize' => (float) ($property->block?->society?->marla_size ?: 272.25),
            'unitTypes' => $common['attrs']->where('type', 'unit_type'),
            'roomTypeCounts' => $allRooms->groupBy('room_type')->map->count(),
            'floorDetails' => $this->buildFloorDetails($property->floors, $common['attrs']->where('type', 'room_type'), $common['attrs']->where('type', 'unit_type')),
            'unitTypeCounts' => $unitTypeCounts,
            'totalUnits' => $unitTypeCounts->sum(),
            'totalRooms' => $allRooms->count(),
            'floorsCount' => $property->floors->count(),
        ]));
    }

    public function showUnit(Request $request, $uuid)
    {
        $visitor = $this->getOrCreateVisitor();

        $unit = PropertyUnit::where('uuid', $uuid)
            ->with(['floor.property.block.society', 'floor.property.floors', 'attachments', 'dimensions', 'listing', 'rooms.dimensions'])
            ->firstOrFail();

        $unitAmenityIds = is_array($unit->amenities) ? $unit->amenities : json_decode($unit->amenities ?? '[]', true);
        $roomAmenityIds = $unit->rooms->flatMap(fn ($r) => is_array($r->amenities) ? $r->amenities : json_decode($r->amenities ?? '[]', true));
        $amenityIds = collect($unitAmenityIds)->merge($roomAmenityIds)->unique()->values();

        $parentProperty = $unit->floor->property;
        $floorContext = $this->floorContext($unit->floor);

        $common = $this->commonListingData($request, $visitor, $unit->attachments, $parentProperty, $parentProperty->block, $parentProperty->block?->society, $unit->rooms, $amenityIds);

        return view('frontend.content.listing-details', array_merge($common, [
            'pageConfigs' => ['myLayout' => 'front'],
            'entity' => $unit,
            'type' => 'unit',
            'listing' => $unit->listing,
            'society' => $parentProperty->block?->society,
            'areaSqft' => GetArea::calculate($unit->dimensions->isNotEmpty() ? $unit->dimensions : $unit->rooms->flatMap->dimensions),
            'marlaSize' => (float) ($parentProperty->block?->society?->marla_size ?: 272.25),
            'unitTypes' => collect(),
            'roomTypeCounts' => $unit->rooms->groupBy('room_type')->map->count(),
            'unitTypeCounts' => collect(),
            'totalUnits' => 0,
            'totalRooms' => $unit->rooms->count(),
            'unitTypeTitle' => $unit->unit_type ? PropertyAttribute::where('id', $unit->unit_type)->value('title') : null,
            'floorTitle' => $floorContext['title'],
        ]));
    }

    public function toggleFavourite(Request $request)
    {
        $request->validate([
            'type' => 'required|in:property,unit',
            'uuid' => 'required|uuid',
        ]);

        $visitor = $this->getOrCreateVisitor();

        $model = $request->type === 'property'
          ? Property::where('uuid', $request->uuid)->firstOrFail()
          : PropertyUnit::where('uuid', $request->uuid)->firstOrFail();

        $existing = VisitorFavourite::where('visitor_id', $visitor->id)
            ->where('favouritable_id', $model->id)
            ->where('favouritable_type', get_class($model))
            ->first();

        if ($existing) {
            $existing->delete();
            $favourited = false;
        } else {
            VisitorFavourite::create([
                'visitor_id' => $visitor->id,
                'favouritable_id' => $model->id,
                'favouritable_type' => get_class($model),
            ]);
            $favourited = true;
        }

        return response()->json(['favourited' => $favourited]);
    }

    public function favourites(Request $request)
    {
        $visitor = $this->getOrCreateVisitor();

        $favourites = VisitorFavourite::where('visitor_id', $visitor->id)->get();

        $propertyIds = $favourites->where('favouritable_type', Property::class)->pluck('favouritable_id');
        $unitIds = $favourites->where('favouritable_type', PropertyUnit::class)->pluck('favouritable_id');

        $properties = Property::whereIn('id', $propertyIds)
            ->with(['block.society', 'attachment', 'dimensions', 'listing', 'floors'])
            ->get();

        $properties->each(function ($property) {
            $property->floors_count = $property->floors->count();
        });

        $units = PropertyUnit::whereIn('id', $unitIds)
            ->with([
                'attachment', 'listing', 'dimensions', 'rooms',
                'floor.property', 'floor.property.block.society', 'floor.property.dimensions', 'floor.property.floors',
            ])
            ->get()
            ->map(fn ($unit) => $this->mapUnit($unit))
            ->filter()
            ->values();

        $items = $properties->concat($units);

        $currency = strtoupper($request->input('currency', 'PKR'));
        $currencyRates = $this->getCurrencyRates();
        $rate = $currencyRates[$currency] ?? 1;
        $areaUnit = $request->input('area_unit', 'sqft');
        $areaLabels = ['sqft' => 'Sq Ft', 'marla' => 'Marla', 'kanal' => 'Kanal'];
        $rawMarlaSize = (float) ($items->first()?->block?->society?->marla_size ?? 0);
        $marlaSize = $rawMarlaSize > 0 ? $rawMarlaSize : 272.25;
        $areaFactor = ['sqft' => 1, 'marla' => $marlaSize, 'kanal' => $marlaSize * 20][$areaUnit] ?? 1;

        return view('frontend.content.favourites', [
            'pageConfigs' => ['myLayout' => 'front'],
            'properties' => $this->paginateCollection($items->values(), $request),
            'currency' => $currency,
            'currencyRates' => $currencyRates,
            'rate' => $rate,
            'areaUnit' => $areaUnit,
            'areaLabel' => $areaLabels[$areaUnit] ?? 'Sq Ft',
            'areaFactor' => $areaFactor,
            'favouritedKeys' => $this->favouritedKeys($visitor),
            'inquiriesByListing' => $this->inquiriesByListing($visitor),
            'isUnitSearch' => false,
        ]);
    }

    public function contactUs()
    {
        return view('frontend.content.contact-us', [
            'pageConfigs' => ['myLayout' => 'front'],
        ]);
    }

    public function sendContactMessage(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|max:5000',
        ]);

        try {
            Message::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'message' => $validated['message'],
            ]);

            SendContactMessageMail::dispatch(
                $validated['full_name'],
                $validated['email'],
                $validated['message']
            );

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent. We\'ll get back to you soon.',
            ]);
        } catch (\Exception $e) {
            Log::error('Contact message save failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function geocode(Request $request)
    {
        $address = trim((string) $request->query('address', ''));

        if ($address === '') {
            return response()->json(['lat' => null, 'lng' => null]);
        }

        $cacheKey = 'geocode:'.md5($address);

        $coords = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addDays(30), function () use ($address) {
            $parts = collect(explode(',', $address))
                ->map(fn ($part) => trim($part))
                ->filter()
                ->values();

            $queries = collect([$address])
                ->merge($parts->count() > 2 ? [$parts->slice(1)->implode(', ')] : [])
                ->merge($parts->count() > 3 ? [$parts->slice(2)->implode(', ')] : [])
                ->unique()
                ->filter();

            foreach ($queries as $query) {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => config('app.name', 'PropertyApp').' Geocoder (contact@yourdomain.com)',
                    ])->timeout(8)->get('https://nominatim.openstreetmap.org/search', [
                        'format' => 'json',
                        'limit' => 1,
                        'q' => $query,
                    ]);

                    if ($response->successful() && $response->json('0.lat') && $response->json('0.lon')) {
                        return [
                            'lat' => (float) $response->json('0.lat'),
                            'lng' => (float) $response->json('0.lon'),
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Property geocode lookup failed: '.$e->getMessage(), ['address' => $query]);
                }
            }

            return ['lat' => null, 'lng' => null];
        });

        return response()->json($coords);
    }
}
