<?php

namespace App\Http\Controllers\frontend;

use App\Helpers\GetArea;
use App\Models\Property;
use App\Models\PropertyAttribute;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class landingPageController
{
    public function index()
    {
        return view('frontend.content.landing-page', ['pageConfigs' => ['myLayout' => 'front']]);
    }

    public function findProperty(Request $request)
    {
        try {
            $pageConfigs = ['myLayout' => 'front'];
            $areaUnit = $request->input('area_unit', 'sqft');

            $currency = strtoupper($request->input('currency', 'PKR'));

            $currencyRates = [
                'PKR' => 1,
            ];

            try {

                $response = Http::timeout(5)
                    ->get('https://open.er-api.com/v6/latest/PKR');

                if ($response->successful()) {

                    $rates = $response->json('rates', []);
                    logger($rates);
                    foreach ([
                        'USD',
                        'EUR',
                        'GBP',
                        'AED',
                        'SAR',
                        'CAD',
                        'AUD',
                    ] as $curr) {

                        if (
                            isset($rates[$curr]) &&
                            is_numeric($rates[$curr]) &&
                            $rates[$curr] > 0
                        ) {

                            // 1 Currency = X PKR
                            $currencyRates[$curr] = 1 / $rates[$curr];
                        }
                    }
                }

            } catch (\Exception $e) {
            }

            $rate = $currencyRates[$currency] ?? 1;
            logger($rate);
            $areaLabels = [
                'sqft' => 'Sq Ft',
                'marla' => 'Marla',
                'kanal' => 'Kanal',
            ];

            $areaLabel = $areaLabels[$areaUnit] ?? 'Sq Ft';
            $listingType = $request->input('listing_type', 'sell');

            $query = Property::query()
                ->with(['block.society', 'attachment', 'dimensions', 'listing'])
                ->whereHas('listing', fn ($q) => $q->where(
                    'listing_type',
                    $listingType === 'rent' ? 'rent' : 'sell'
                ));

            if ($request->filled('society')) {
                $query->whereHas('block.society', fn ($q) => $q->where('name', 'LIKE', '%'.$request->society.'%'));
            }

            if ($request->filled('property_name')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', "%{$request->property_name}%")
                        ->orWhere('property_no', 'LIKE', "%{$request->property_name}%");
                });
            }

            if ($request->filled('location')) {
                $query->where(function ($q) use ($request) {
                    $q->where('street', 'LIKE', "%{$request->location}%")
                        ->orWhere('landmark', 'LIKE', "%{$request->location}%")
                        ->orWhereHas('block', fn ($b) => $b->where('name', 'LIKE', "%{$request->location}%"))
                        ->orWhereHas('block.society', fn ($s) => $s->where('city', 'LIKE', "%{$request->location}%"));
                });
            }

            if ($request->filled('category') && $request->category !== 'any') {
                $query->where('category', $request->category);
            }

            $requestedType = $request->filled('type') && $request->type !== 'Any'
              ? $request->type
              : null;

            if ($requestedType) {

                if (is_numeric($requestedType)) {

                    $typeName = PropertyAttribute::find($requestedType)?->name;

                    $query->where('type', $typeName ?? $requestedType);

                } else {

                    $query->where('type', 'LIKE', "%{$requestedType}%");
                }
            }

            if ($request->filled('city')) {
                $query->whereHas('block.society', fn ($q) => $q->where('city', $request->city));
            }

            if ($request->filled('country')) {
                $query->whereHas('block.society', fn ($q) => $q->where('country', $request->country));
            }

            if ($request->filled('min_price')) {
                $query->whereHas('listing', fn ($q) => $q->where('price', '>=', $request->min_price * $rate));
            }

            if ($request->filled('max_price')) {
                $query->whereHas('listing', fn ($q) => $q->where('price', '<=', $request->max_price * $rate));
            }

            $properties = $query->get();

            if ($requestedType && $properties->isEmpty()) {

                $attrIds = is_numeric($requestedType)
                  ? PropertyAttribute::where('id', $requestedType)->pluck('id')
                  : PropertyAttribute::where('type', 'unit_type')
                      ->where('name', 'LIKE', "%{$requestedType}%")
                      ->pluck('id');

                $properties = PropertyUnit::with([
                    'floor.property.block.society',
                    'floor.property.attachment',
                    'floor.property.dimensions',
                    'floor.property.listing',
                ])
                    ->whereIn('unit_type', $attrIds)
                    ->whereHas('floor.property.listing', fn ($q) => $q->where(
                        'listing_type',
                        $listingType === 'rent' ? 'rent' : 'sell'
                    ))
                    ->get()
                    ->map(fn ($u) => $u->floor?->property)
                    ->filter()
                    ->unique('id')
                    ->values();
            }

            $marlaSize = (float) ($properties->first()?->block?->society?->marla_size ?: 272.25);

            $areaToSqft = [
                'sqft' => 1,
                'marla' => $marlaSize,
                'kanal' => $marlaSize * 20,
            ];

            $areaFactor = $areaToSqft[$areaUnit] ?? 1;

            if ($request->filled('min_area')) {
                $properties = $properties->filter(
                    fn ($p) => GetArea::calculate($p->dimensions) >= ($request->min_area * $areaFactor)
                );
            }

            if ($request->filled('max_area')) {
                $properties = $properties->filter(
                    fn ($p) => GetArea::calculate($p->dimensions) <= ($request->max_area * $areaFactor)
                );
            }

            $searchInputs = $request->only([
                'listing_type',
                'society',
                'property_name',
                'location',
                'category',
                'type',
                'city',
                'country',
                'min_price',
                'max_price',
                'min_area',
                'max_area',
                'currency',
                'area_unit',
                'rooms',
                'floors',
            ]);

            return view('frontend.content.properties', compact(
                'properties',
                'pageConfigs',
                'currency',
                'areaUnit',
                'areaLabel',
                'rate',
                'areaFactor',
                'searchInputs'
            ));

        } catch (\Exception $e) {

            $searchInputs = $request->only([
                'listing_type',
                'society',
                'property_name',
                'location',
                'category',
                'type',
                'city',
                'country',
                'min_price',
                'max_price',
                'min_area',
                'max_area',
                'currency',
                'area_unit',
                'rooms',
                'floors',
            ]);

            return view('frontend.content.properties', [
                'properties' => collect(),
                'pageConfigs' => ['myLayout' => 'front'],
                'currency' => $request->input('currency', 'PKR'),
                'areaUnit' => $request->input('area_unit', 'sqft'),
                'areaLabel' => 'Sq Ft',
                'rate' => 1,
                'areaFactor' => 1,
                'searchInputs' => $searchInputs,
            ])->with('error', $e->getMessage());
        }
    }

    public function sendPropertyMail(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'id' => 'required|exists:properties,id',
            'contact_no' => 'required|string|max:50',
            'email' => 'required|email',
            'message' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        $property = Property::with([
            'owner',
            'listing',
            'block.society',
        ])->findOrFail($request->id);
        logger($property);
        $ownerEmail = $property->owner?->email
          ?? $property->block?->society?->owner?->email;

        dd($ownerEmail);

        if (! $ownerEmail) {
            return back()->with('error', 'Property owner email not found.');
        }
        //
        //        Mail::send('emails.', [
        //            'property' => $property,
        //            'data' => $request->all(),
        //        ], function ($mail) use ($ownerEmail, $property) {
        //
        //            $mail->to($ownerEmail)
        //                ->replyTo(request('email'))
        //                ->subject('Property Inquiry - Property #'.$property->id);
        //        });

        return back()->with('success', 'Your inquiry has been sent successfully.');
    }
}
