<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\BlockPriceRange;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\PropertyUnit;
use App\Services\PropertyRangeService;
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
            'closed' => false,
        ]);

        app(PropertyRangeService::class)->syncBlockPaymentPlanFlag($model);

        return back()->with('success', 'Listed successfully.');
    }

    public function blockStore(Request $request)
    {
        $request->validate([
            'block_uuid' => 'nullable|string',
            'block_uuids' => 'nullable|array',
            'block_uuids.*' => 'string',
            'listing_type' => 'required|in:sell,rent',
            'size_range' => 'nullable|string',
            'category' => 'nullable|in:commercial,non_commercial',
            'construction_status' => 'nullable|in:0,1',
            'price' => 'required_if:listing_type,sell|nullable|numeric|min:0',
            'price_unit' => 'required_if:listing_type,sell|nullable|in:marla,square_yard',
            'contact_no' => 'required',
            'email' => 'required|email',
            'description' => 'required',
        ]);

        $uuids = collect($request->block_uuids ?? [])
            ->merge($request->block_uuid ? [$request->block_uuid] : [])
            ->filter()
            ->unique()
            ->values();

        if ($uuids->isEmpty()) {
            return back()->with('error', 'No block(s) specified.');
        }

        $blocks = Block::whereIn('uuid', $uuids)->get();

        if ($blocks->isEmpty()) {
            return back()->with('error', 'No matching blocks found.');
        }

        try {
            $service = app(PropertyRangeService::class);
            $total = 0;

            foreach ($blocks as $block) {
                if ($request->listing_type === 'sell' && $request->filled('size_range')) {
                    [$min, $max] = $service->parseSizeRange($request->size_range);

                    BlockPriceRange::updateOrCreate(
                        ['block_id' => $block->id, 'min_size' => $min, 'max_size' => $max],
                        ['price' => $request->price, 'price_unit' => $request->price_unit]
                    );
                }

                $total += $service->applyListing($block, $request);
            }

            if ($total === 0) {
                return back()->with('error', 'No matching properties found for the selected category and size range.');
            }

            return back()->with('success', "Listing applied to {$total} property(ies) across {$blocks->count()} block(s).");
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while applying the listing: '.$e->getMessage());
        }
    }

    public function destroy($uuid)
    {
        $listing = PropertyListing::where('uuid', $uuid)->firstOrFail();
        $listable = $listing->listable;
        $listing->inquiries()->delete();
        $listing->delete();

        app(PropertyRangeService::class)->syncBlockPaymentPlanFlag($listable);

        return back()->with('success', 'Unlisted successfully.');
    }
}
