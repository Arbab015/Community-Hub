<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Floor;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Room;
use App\Services\FileServices;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertiesController extends Controller
{
    public function show(Request $request, $uuid)
    {
        $block = Block::where('uuid', $uuid)->first();
        $skip = $request->input('skip', 0);
        $total_skip = 4 + $skip;
        $take = 4;

        $query = Property::where('block_id', $block->id)->with(
            'dimensions',
            'attachment',
            'attachments',
            'floors.dimensions',
            'floors.rooms.dimensions',
            'floors.units.rooms.dimensions',
            'floors.units.owner',
        )->latest();

        $query->when($request->category, fn ($q) => $q->where('category', $request->category)
        );
        $query->when($request->type, fn ($q) => $q->where('type', $request->type));
        $total_properties = $query->count();
        $properties = $query->skip($skip)->take($take)->get();
        $query_dropdown = Property::query()->where('block_id', $block->id);
        $property_categories = $query_dropdown->distinct('id')->pluck('category');
        $property_types = $query_dropdown->distinct('id')->pluck('type');
        if ($request->ajax()) {
            return response()->json([
                'total_skip' => $total_skip,
                'properties' => $properties,
                'total_properties' => $total_properties,
            ]);
        }

        return view('content.property management.blocks.show', compact('block', 'properties', 'total_skip', 'property_categories', 'property_types', 'total_properties'));
    }

    public function create(Request $request, $uuid = null)
    {
        $block = Block::findOrFail($request->query('block'));
        $tab = $request->query('tab');
        $property = $uuid ? $property = Property::where('uuid', $uuid)
            ->with([
                'dimensions',
                'attachment',
                'attachments',
                'floors.dimensions',
                'floors.rooms.dimensions',
                'floors.units.rooms.dimensions',
                'floors.units.owner',
            ])
            ->first() : null;

        return view('content.property management.blocks.properties.create', compact('block', 'property', 'tab'));
    }

    public function storeOrUpdate(Request $request)
    {
        //              dd($request->floors);
        $section = $request->input('section');
        $block = Block::findOrFail($request->block_id);
        $society = $block->society;
        $property = $request->property_id ? Property::findOrFail($request->property_id) : null;

        if ($section === 'property') {
            $request->validate([
                'block_id' => 'required|exists:blocks,id',
                'category' => 'required|string|in:residential,commercial,other',
                'name' => 'nullable|string|max:100',
                'property_no' => [
                    'required', 'string', 'max:50',
                    Rule::unique('properties', 'property_no')
                        ->where(fn ($q) => $q->whereIn('block_id', $society->blocks()->pluck('id')))
                        ->ignore($property?->id),
                ],
                'type' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'is_constructed' => 'nullable|boolean',
                'dimensions' => 'required|array|min:1',
                'dimensions.*.name' => 'required|string|max:15',
                'dimensions.*.size' => 'required|decimal:0,2|min:0',
                'dimensions.*.unit' => 'required|string|in:feet,square_feet,meter,yard,marla,kanal',
            ]);
        }

        if ($section === 'documents') {
            $request->validate([
                'property_id' => 'required|exists:properties,id',
                'main_pic' => 'nullable|image|max:5120',
                'documents.*' => 'nullable|file|max:10240',
            ]);
        }

        if ($section === 'construction') {
            $request->validate([
                'property_id' => 'required|exists:properties,id',
                'floors' => 'required|array|min:1',
                'floors.*.floor_type' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('floors', 'floor_type')
                        ->where(fn ($query) => $query->where('property_id', $property->id)),
                ],
                'floors.*.dimensions.*.name' => 'nullable|string|max:15',
                'floors.*.dimensions.*.size' => 'nullable|decimal:0,2|min:0',
                'floors.*.dimensions.*.unit' => 'nullable|string|in:feet,square_feet,meter,yard,marla,kanal',
                'floors.*.units.*.unit_no' => 'nullable|integer',
                'floors.*.units.*.unit_name' => 'nullable|string|max:50',
                'floors.*.units.*.unit_type' => 'nullable|string|in:apartment,office,shop,studio,other',
                'floors.*.units.*.rooms.*.room_type' => 'nullable|string|max:50',
                'floors.*.rooms.*.room_type' => 'nullable|string|max:50',
            ]);
        }

        // STORE / UPDATE
        try {

            // PROPERTY BASIC
            if ($section === 'property') {
                if ($property) {
                    $property->update([
                        'name' => $request->get('name'),
                        'category' => $request->category,
                        'property_no' => $request->property_no,
                        'type' => $request->type,
                        'address' => $request->address,
                        'is_constructed' => $request->boolean('is_constructed'),
                    ]);
                    $property->dimensions()->delete();
                } else {
                    $property = Property::create([
                        'block_id' => $block->id,
                        'name' => $request->get('name'),
                        'category' => $request->category,
                        'property_no' => $request->property_no,
                        'type' => $request->type,
                        'address' => $request->address,
                        'is_constructed' => $request->boolean('is_constructed'),
                    ]);
                }

                foreach ($request->dimensions as $dimension) {
                    $property->dimensions()->create([
                        'name' => $dimension['name'],
                        'size' => $dimension['size'],
                        'unit' => $dimension['unit'],
                    ]);
                }

                return redirect()->route('property.create', [
                    'block' => $block,
                    'uuid' => $property->uuid,
                    'tab' => 'documents',
                ])->with('success', 'Property saved. Now add documents.');
            }

            // DOCUMENTS
            if ($section === 'documents') {
                if ($request->hasFile('main_pic')) {
                    app(FileServices::class)->compressAndStore(
                        $request->file('main_pic'), $property, true, true
                    );
                }
                if ($request->hasFile('documents')) {
                    app(FileServices::class)->compressAndStore(
                        $request->file('documents'), $property, false
                    );
                }

//                $tab = $property->is_constructed ? 'construction' : 'documents';
                if ($property->is_constructed) {
                    return redirect()->route('property.create', [
                        'block' => $block->id,
                        'uuid' => $property->uuid,
                        'tab' => 'construction',
                    ])->with('success', 'Documents saved successfully.');
                } else {
                    return redirect()->route('blocks.view', $block->uuid)->with('success', 'Documents saved and property created successfully.');
                }

            }

            //  CONSTRUCTION
            if ($section === 'construction') {
                foreach ($request->floors as $floor) {

                    // Create floor
                    $floor = Floor::create([
                        'property_id' => $property->id,
                        'floor_type' => $floor['floor_type'],
                    ]);

                    // Floor dimensions
                    if (! empty($floor['dimensions'])) {
                        foreach ($floor['dimensions'] as $dimension) {
                            if (empty($dimension['name'])) {
                                continue;
                            }
                            $floor->dimensions()->create([
                                'name' => $dimension['name'],
                                'size' => $dimension['size'],
                                'unit' => $dimension['unit'],
                            ]);
                        }
                    }

                    // Units (commercial with has_units ticked)
                    if (! empty($floor['has_units']) && ! empty($floor['units'])) {
                        foreach ($floor['units'] as $unit) {
                            $unit = PropertyUnit::create([
                                'floor_id' => $floor->id,
                                'unit_no' => $unit['unit_no'] ?? null,
                                'unit_name' => $unit['unit_name'] ?? null,
                                'unit_type' => $unit['unit_type'] ?? null,
                            ]);

                            // Rooms inside unit
                            if (! empty($unit['rooms'])) {
                                foreach ($unit['rooms'] as $room) {
                                    if (empty($room['room_type'])) {
                                        continue;
                                    }
                                    $room = Room::create([
                                        'floor_id' => $floor->id,
                                        'unit_id' => $unit->id,
                                        'room_type' => $room['room_type'],
                                        'has_attached_bathroom' => ! empty($room['has_attached_bathroom']),
                                        'has_attached_ac' => ! empty($room['has_attached_ac']),
                                        'has_attached_balcony' => ! empty($room['has_attached_balcony']),
                                        'has_attached_wardrobe' => ! empty($room['has_attached_wardrobe']),
                                    ]);
                                    // Room dimensions
                                    if (! empty($room['dimensions'])) {
                                        foreach ($room['dimensions'] as $dimension) {
                                            if (empty($dimension['name'])) {
                                                continue;
                                            }
                                            $room->dimensions()->create([
                                                'name' => $dimension['name'],
                                                'size' => $dimension['size'],
                                                'unit' => $dimension['unit'],
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // Rooms directly on floor (non-commercial or commercial without units)
                        if (! empty($floor['rooms'])) {
                            foreach ($floor['rooms'] as $room) {
                                if (empty($room['room_type'])) {
                                    continue;
                                }
                                $room = Room::create([
                                    'floor_id' => $floor->id,
                                    'unit_id' => null,
                                    'room_type' => $room['room_type'],
                                    'has_attached_bathroom' => ! empty($room['has_attached_bathroom']),
                                    'has_attached_ac' => ! empty($room['has_attached_ac']),
                                    'has_attached_balcony' => ! empty($room['has_attached_balcony']),
                                    'has_attached_wardrobe' => ! empty($room['has_attached_wardrobe']),
                                ]);
                                if (! empty($room['dimensions'])) {
                                    foreach ($room['dimensions'] as $dimension) {
                                        if (empty($dimension['name'])) {
                                            continue;
                                        }
                                        $room->dimensions()->create([
                                            'name' => $dimension['name'],
                                            'size' => $dimension['size'],
                                            'unit' => $dimension['unit'],
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                return redirect()->route('blocks.view', $block->uuid)->with('success', 'Construction details saved and property created successfully.');
            }

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function propertyDetails(Request $request, $uuid)
    {
        $property = Property::where('uuid', $uuid)->with('block')->firstOrFail();
        $documents = $property->attachments->filter(function ($attachment) {
            return in_array($attachment->extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx']);
        });

        return view('content.property management.blocks.properties.property_details', compact('property', 'documents'));
    }
}
