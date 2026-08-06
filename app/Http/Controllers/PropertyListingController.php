<?php

namespace App\Http\Controllers;

use App\Helpers\GetArea;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;

class PropertyListingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:property,unit',
            'id' => 'required',
            'listing_type' => 'required|in:sell,rent',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|in:PKR,USD,AED,SAR',
            'contact_no' => 'required',
            'email' => 'required|email',
            'description' => 'required',
        ]);

        $model = $request->type === 'property'
          ? Property::findOrFail($request->id)
          : PropertyUnit::findOrFail($request->id);

        $model->listing()->updateOrCreate([], [
            'listing_type' => $request->listing_type,
            'price' => $request->price,
            'unit' => $request->unit,
            'show_price' => $request->boolean('show_price'),
            'contact_no' => $request->contact_no,
            'show_contact_no' => $request->boolean('show_contact_no'),
            'email' => $request->email,
            'description' => $request->description,
        ]);

        return back()->with('success', 'Listed successfully.');
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'ids' => 'required|string',
            'listing_type' => 'required|in:sell,rent',
            'rate' => 'required|numeric|min:0',
            'unit' => 'required|in:PKR,USD,AED,SAR',
            'rate_unit' => 'required|in:marla,square_yard',
            'contact_no' => 'required',
            'email' => 'required|email',
            'description' => 'required',
        ]);

        $propertyUuids = array_filter(array_map('trim', explode(',', $request->ids)));
        if (empty($propertyUuids)) {
            return back()->with('error', 'No properties selected.');
        }

        $properties = Property::with(['dimensions', 'block.society'])
            ->whereIn('uuid', $propertyUuids)
            ->whereNull('owner_id')
            ->get();

        $total = 0;
        $skipped = [];

        foreach ($properties as $property) {
            $price = $this->calcPrice($property, $request);
            if ($price) {
                $this->createListing($property, $price, $request);
                $total++;
            } else {
                $skipped[] = $property->property_no;
            }
        }

        $msg = "{$total} item".($total === 1 ? '' : 's').' listed.';
        if ($skipped) {
            $msg .= ' Skipped: '.implode(', ', $skipped);
        }

        return back()->with('success', $msg);
    }

    private function calcPrice($item, $request)
    {
        $area = GetArea::calculate($item->dimensions);
        if ($area <= 0) {
            return null;
        }

        $marlaSize = optional(optional($item->block)->society)->marla_size ?? 272.25;
        $unitArea = $request->rate_unit === 'marla' ? $area / $marlaSize : $area / 9;

        return round($request->rate * $unitArea, 2);
    }

    private function createListing($item, $price, $request)
    {
        $item->listing()->updateOrCreate([], [
            'listing_type' => $request->listing_type,
            'price' => $price,
            'unit' => $request->unit,
            'show_price' => $request->boolean('show_price'),
            'contact_no' => $request->contact_no,
            'show_contact_no' => $request->boolean('show_contact_no'),
            'email' => $request->email,
            'description' => $request->description,
        ]);
    }

    public function destroy($uuid)
    {
        $listing = PropertyListing::where('uuid', $uuid)->firstOrFail();
        $listing->inquiries()->delete();
        $listing->delete();

        return back()->with('success', 'Unlisted successfully.');
    }
}
