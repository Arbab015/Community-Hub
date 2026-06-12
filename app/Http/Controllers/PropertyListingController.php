<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\PropertyUnit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class PropertyListingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:property,unit',
            'id' => 'required|integer',
            'listing_type' => 'required|in:sell,rent',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|in:PKR,USD,AED',
            'show_price' => 'nullable|boolean',
            'contact_no' => 'required|string|max:20',
            'show_contact_no' => 'nullable|boolean',
            'email' => 'required|email|max:255',
            'description' => 'required|string|max:2000',
        ]);

        try {
            $listable = match ($validated['type']) {
                'property' => Property::findOrFail($validated['id']),
                'unit' => PropertyUnit::findOrFail($validated['id']),
            };

            $listable->listing()->updateOrCreate(
                [],
                [
                    'listing_type' => $validated['listing_type'],
                    'price' => $validated['price'],
                    'unit' => $validated['unit'],
                    'show_price' => $request->boolean('show_price'),
                    'contact_no' => $validated['contact_no'],
                    'show_contact_no' => $request->boolean('show_contact_no'),
                    'email' => $validated['email'] ?? null,
                    'description' => $validated['description'],
                ]
            );

            return back()->with('success', 'Property listed successfully.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->with('error', 'Property not found.')->withInput();

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy($uuid)
    {

        try {
            $listed_property = PropertyListing::where('uuid', $uuid)->firstOrFail();
            $listed_property->delete();

            return redirect()->back()->with('success', 'Property Un-listed successfully.');
        } catch (ModelNotFoundException $e) {
            return redirect()->back()->with('error', 'Listing record not found.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
