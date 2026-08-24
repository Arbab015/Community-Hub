<?php

namespace App\Http\Controllers;

use App\Helpers\GetArea;
use App\Models\Block;
use App\Models\BlockPriceRange;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;

class PropertyListingController extends Controller
{
    private const DEFAULT_MARLA_SQFT = 272.25;

    private const KANAL_IN_MARLA = 20;

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

    public function blockStore(Request $request)
    {
        $request->validate([
            'block_uuid' => 'required|exists:blocks,uuid',
            'listing_type' => 'required|in:sell,rent',
            'size_range' => 'nullable|string',
            'price' => 'required_if:listing_type,sell|nullable|numeric|min:0',
            'price_unit' => 'required_if:listing_type,sell|nullable|in:marla,square_yard',
            'contact_no' => 'required',
            'email' => 'required|email',
            'description' => 'required',
        ]);

        try {
            $block = Block::where('uuid', $request->block_uuid)->firstOrFail();
            $query = Property::where('block_id', $block->id)->whereNull('owner_id');

            if ($request->listing_type === 'sell' && $request->filled('size_range')) {
                [$min, $max] = $this->parseSizeRange($request->size_range);
                BlockPriceRange::updateOrCreate(
                    ['block_id' => $block->id, 'min_size' => $min, 'max_size' => $max],
                    ['price' => $request->price, 'price_unit' => $request->price_unit]
                );

                $properties = $query->with(['dimensions', 'block.society'])
                    ->get()
                    ->filter(fn ($property) => $this->areaInRange($property, $min, $max));
            } else {
                $properties = $query->get();
            }

            if ($properties->isEmpty()) {
                return back()->with('error', 'No matching properties found.');
            }

            $total = 0;

            foreach ($properties as $property) {
                if ($request->listing_type === 'sell') {
                    $calculatedPrice = $this->calculatePrice($property, $request->price, $request->price_unit);

                    $property->listing()->updateOrCreate([], [
                        'listing_type' => $request->listing_type,
                        'price' => $calculatedPrice,
                        'unit' => $request->price_unit,
                        'show_price' => $request->boolean('show_price'),
                        'contact_no' => $request->contact_no,
                        'show_contact_no' => $request->boolean('show_contact_no'),
                        'email' => $request->email,
                        'description' => $request->description,
                    ]);
                } else {
                    $property->listing()->updateOrCreate([], [
                        'listing_type' => $request->listing_type,
                        'price' => 0,
                        'unit' => 'PKR',
                        'show_price' => $request->boolean('show_price'),
                        'contact_no' => $request->contact_no,
                        'show_contact_no' => $request->boolean('show_contact_no'),
                        'email' => $request->email,
                        'description' => $request->description,
                    ]);
                }

                $total++;
            }

            return back()->with('success', "Listing applied to {$total} property(ies).");
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while applying the listing: '.$e->getMessage());
        }
    }

    public function destroy($uuid)
    {
        $listing = PropertyListing::where('uuid', $uuid)->firstOrFail();
        $listing->inquiries()->delete();
        $listing->delete();

        return back()->with('success', 'Unlisted successfully.');
    }

    private function parseSizeRange(string $range): array
    {
        $parts = explode('-', $range);
        $isKanal = in_array('kanal', $parts);
        $isPlus = in_array('plus', $parts);

        $min = (float) $parts[0];
        $max = $isPlus ? null : (float) ($parts[1] ?? $min);

        if ($isKanal) {
            $min *= self::KANAL_IN_MARLA;
            $max = $max !== null ? $max * self::KANAL_IN_MARLA : null;
        }

        return [$min, $max];
    }

    private function areaInRange(Property $property, float $min, ?float $max): bool
    {
        $sqft = GetArea::calculate($property->dimensions);
        if ($sqft <= 0) {
            return false;
        }

        $marlaSize = optional(optional($property->block)->society)->marla_size ?? self::DEFAULT_MARLA_SQFT;
        $marla = $sqft / $marlaSize;

        return $marla > $min && ($max === null || $marla <= $max);
    }

    private function calculatePrice(Property $property, float $rate, string $priceUnit): int
    {
        $sqft = GetArea::calculate($property->dimensions);

        $marlaSize = optional(optional($property->block)->society)->marla_size ?? self::DEFAULT_MARLA_SQFT;

        $area = $priceUnit === 'marla' ? $sqft / $marlaSize : $sqft / 9;

        return (int) round($rate * $area);
    }
}
