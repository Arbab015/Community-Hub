<?php

namespace App\Http\Controllers;

use App\Helpers\GetArea;
use App\Models\Attachment;
use App\Models\Block;
use App\Models\Floor;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Room;
use App\Services\FileServices;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertiesController extends Controller
{
    public static function convertForSystem($value, $from)
    {
        return match ($from) {
            'meter' => $value * 3.28084,
            'yard' => $value * 3,
            'feet' => $value,
        };
    }

    public function convertForUser($value_in_feet, $to_unit)
    {
        return match ($to_unit) {
            'meter' => $value_in_feet / 3.28084,
            'yard' => $value_in_feet / 3,
            'feet' => $value_in_feet,
        };
    }

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
        $query->when($request->category, fn ($q) => $q->where('category', $request->category));
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

    public function create(Request $request, $block_uuid, $uuid = null)
    {

        $block = Block::where('uuid', $block_uuid)->first();
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
        //        dd($request->all());
        $section = $request->input('section');
        $block = Block::findOrFail($request->block_id);
        $society = $block->society;
        $property = $request->property_id ? Property::findOrFail($request->property_id) : null;
        $request_type = strtolower($request->input('request_type', 'on_create'));
        if ($section === 'property') {
            $rules[] = '';
            if ($request_type === 'property_info' || $request_type === 'on_create') {
                $rules = [
                    'block_id' => 'required|exists:blocks,id',
                    'category' => 'required|string|in:residential,commercial,other',
                    'name' => 'required|string|max:100',
                    'property_no' => [
                        'required', 'string', 'max:50',
                        Rule::unique('properties', 'property_no')
                            ->where(fn ($q) => $q->whereIn('block_id', $society->blocks()->pluck('id')))
                            ->ignore($property?->id),
                    ],
                    'type' => 'required|string|max:255',
                    'address' => 'required|string|max:255',
                    'const_status' => 'nullable|string|in:constructed,in_progress,pending',
                ];
            }
            if ($request_type === 'property_dimension' || $request_type === 'on_create') {
                $rules['dimensions'] = 'required|array|min:2|max:8';
                $rules['dimensions.*.name'] = 'required|string|max:15|distinct';
                $rules['dimensions.*.size'] = ['required', 'regex:/^\d+(\.\d{1,4})?$/', 'min:0', 'max:999999.9999'];
                $rules['dimensions.*.unit'] = 'required|string|in:feet,meter,yard';
            }
            $request->validate($rules);
        }

        if ($section === 'documents') {
            $request->validate([
                'property_id' => 'required|exists:properties,id',
                'main_pic' => 'nullable|image|max:20000',
                'documents.*' => match ($request_type) {
                    'document' => ['file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:20000'],
                    'media' => ['file', 'mimes:jpg,jpeg,png,gif,svg,mp4,mov,avi,webm,mkv', 'max:20000'],
                    default => ['file', 'mimes:jpg,jpeg,png,gif,svg,mp4,mov,avi,webm,mkv,pdf,doc,docx,xls,xlsx', 'max:20000'],
                },
            ]);
        }

        if ($section === 'construction') {
            $isUpdate = $request_type === 'property_const';

            // Floor type uniqueness: on update ignore already-submitted floor IDs
            $floor_type_rule = $isUpdate
              ? [
                  'required', 'string', 'max:50',
                  function ($attribute, $value, $fail) use ($request, $property) {
                      $submitted = collect($request->input('floors', []))
                          ->pluck('floor_type')
                          ->map(fn ($v) => strtolower($v));
                      if ($submitted->duplicates()->isNotEmpty()) {
                          $fail('Floor type must be unique within the same property.');

                          return;
                      }
                      $submitted_ids = collect($request->input('floors', []))
                          ->pluck('id')->filter()->toArray();
                      $exists = Floor::where('property_id', $property->id)
                          ->whereNotIn('id', $submitted_ids)
                          ->whereRaw('LOWER(floor_type) = ?', [strtolower($value)])
                          ->exists();
                      if ($exists) {
                          $fail('Floor type must be unique within the same property.');
                      }
                  },
              ]
              : [
                  'required', 'string', 'max:50',
                  Rule::unique('floors', 'floor_type')
                      ->where(fn ($q) => $q->where('property_id', $property->id)),
              ];

            // Collect all submitted unit IDs so we can ignore them in the unique check
            $submitted_unit_ids = collect($request->input('floors', []))
                ->flatMap(fn ($floor) => collect($floor['units'] ?? [])->pluck('id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->toArray();

            // Unit name uniqueness rule

            //            $unitNameRule = [
            //                'required', 'string', 'max:50',
            //                // Cross-request duplicate check (same form submission)
            //                function ($attribute, $value, $fail) use ($request) {
            //                    $allUnitNames = [];
            //                    foreach ($request->input('floors', []) as $floor) {
            //                        foreach ($floor['units'] ?? [] as $unit) {
            //                            $allUnitNames[] = strtolower($unit['unit_name'] ?? '');
            //                        }
            //                    }
            //                    if (count(array_keys($allUnitNames, strtolower($value))) > 1) {
            //                        $fail('The unit name must be unique within the same property.');
            //                    }
            //                },
            //                // DB unique check — always ignore the unit IDs being submitted so existing
            //                // units don't conflict with themselves during update
            //                Rule::unique('property_units', 'unit_name')
            //                    ->where(fn ($q) => $q->whereIn('floor_id',
            //                        Floor::where('property_id', $property?->id)->pluck('id')
            //                    ))
            //                    ->ignore(
            //                        // ignoreWhere is not available for multi-ignore, so we use whereNotIn via a
            //                        // custom closure on the rule itself — instead, pass a single ID trick:
            //                        // We handle this by scoping the query to exclude submitted IDs below.
            //                        null // placeholder — real exclusion handled by whereNotIn closure
            //                    ),
            //            ];

            // Replace the Rule::unique with a fully custom closure that handles both
            // scope + ignore-submitted-ids in one place (cleaner than the above placeholder)
            $unit_name_rule = [
                'required', 'string', 'max:50',
                // Cross-request duplicate check (same form submission)
                function ($attribute, $value, $fail) use ($request) {
                    $all_unit_names = [];
                    foreach ($request->input('floors', []) as $floor) {
                        foreach ($floor['units'] ?? [] as $unit) {
                            $all_unit_names[] = strtolower($unit['unit_name'] ?? '');
                        }
                    }
                    if (count(array_keys($all_unit_names, strtolower($value))) > 1) {
                        $fail('The unit name must be unique within the same property.');
                    }
                },
                // DB uniqueness — exclude unit IDs that are being updated (they own their name)
                function ($attribute, $value, $fail) use ($property, $submitted_unit_ids) {
                    $floorIds = Floor::where('property_id', $property?->id)->pluck('id');
                    $exists = PropertyUnit::whereIn('floor_id', $floorIds)
                        ->whereNotIn('id', $submitted_unit_ids)
                        ->whereRaw('LOWER(unit_name) = ?', [strtolower($value)])
                        ->exists();
                    if ($exists) {
                        $fail('The unit name has already been taken.');
                    }
                },
            ];

            $request->validate([
                'property_id' => 'required|exists:properties,id',
                'floors' => 'required|array|min:1',
                'floors.*.floor_type' => $floor_type_rule,
                'floors.*.units.*.unit_name' => $unit_name_rule,
                'floors.*.units.*.unit_type' => 'required|string|in:apartment,office,shop,studio,other',
                'floors.*.units.*.rooms.*.room_type' => 'required|string|max:50',
                'floors.*.units.*.rooms.*.dimensions' => 'required|array|min:2|max:8',
                'floors.*.units.*.rooms.*.dimensions.*.name' => 'required|string|max:20',
                'floors.*.units.*.rooms.*.dimensions.*.size' => 'required|numeric|min:0|max:999999.99',
                'floors.*.units.*.rooms.*.dimensions.*.unit' => 'required|string|in:feet,meter,yard',
                'floors.*.rooms.*.room_type' => 'required|string|max:50',
                'floors.*.rooms.*.dimensions' => 'required|array|min:2|max:8',
                'floors.*.rooms.*.dimensions.*.name' => 'required|string|max:20',
                'floors.*.rooms.*.dimensions.*.size' => 'required|numeric|min:0|max:999999.99',
                'floors.*.rooms.*.dimensions.*.unit' => 'required|string|in:feet,meter,yard',
            ]);
        }

        // STORE / UPDATE
        try {
            // PROPERTY BASIC
            if ($section === 'property') {
                if ($property && ($request_type === 'property_info' || $request_type === 'on_create')) {
                    logger('validation update start');
                    $property->update([
                        'name' => $request->get('name'),
                        'category' => $request->category,
                        'property_no' => $request->property_no,
                        'type' => $request->type,
                        'address' => $request->address,
                        'const_status' => $request->const_status,
                    ]);
                } elseif ($request_type === 'on_create' && ! isset($property)) {
                    $property = Property::create([
                        'block_id' => $block->id,
                        'name' => $request->get('name'),
                        'category' => $request->category,
                        'property_no' => $request->property_no,
                        'type' => $request->type,
                        'address' => $request->address,
                        'const_status' => $request->const_status,
                    ]);
                }

                if ($request_type === 'property_dimension' || $request_type === 'on_create') {
                    $property->dimensions()->delete();
                    foreach ($request->dimensions as $dimension) {
                        $converted_size = $this->convertForSystem($dimension['size'], $dimension['unit']);
                        $property->dimensions()->create([
                            'name' => $dimension['name'],
                            'size' => $converted_size,
                            'unit' => $dimension['unit'],
                        ]);
                    }
                }

                if ($request_type !== 'on_create') {
                    return redirect()->back()->with('success', 'Property Updated Successfully');
                } else {
                    return redirect()->route('property.create', [
                        'block_uuid' => $block->uuid,
                        'uuid' => $property->uuid,
                        'tab' => 'documents',
                    ])->with('success', 'Property saved. Now add documents.');
                }
            }

            // DOCUMENTS
            if ($section === 'documents') {
                if ($request->deleted_files) {
                    $ids = json_decode($request->deleted_files, true);
                    Attachment::whereIn('id', $ids)->delete();
                }
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
                if ($property->const_status !== 'pending' && $request_type === 'on_create') {
                    return redirect()->route('property.create', [
                        'block_uuid' => $block->uuid,
                        'uuid' => $property->uuid,
                        'tab' => 'construction',
                    ])->with('success', 'Documents saved successfully.');
                } elseif ($request_type === 'document') {
                    $message = 'Documents updated successfully.';

                    return back()->with('success', $message);
                } else {
                    return redirect()->route('blocks.view', $block->uuid)
                        ->with('success', 'Documents saved and property created successfully.');
                }
            }

            // CONSTRUCTION  —  CREATE (on_create)  or  UPDATE (property_const)
            if ($section === 'construction') {

                $isUpdate = $request_type === 'property_const';

                foreach ($request->floors as $floor_data) {

                    logger($floor_data);
                    //  FLOOR
                    $floorId = ! empty($floor_data['id']) ? (int) $floor_data['id'] : null;
                    //                    logger('floor id => ', $floorId);
                    if ($isUpdate && $floorId) {
                        $floor = Floor::find($floorId);
                        logger($floor);
                        if ($floor) {
                            $floor->update(['floor_type' => $floor_data['floor_type']]);
                        }
                    } else {
                        logger('floor not found');
                        $floor = Floor::create([
                            'property_id' => $property->id,
                            'floor_type' => $floor_data['floor_type'],
                        ]);
                    }

                    if (! $floor) {
                        continue;
                    }
                    //  UNITS
                    if (! empty($floor_data['has_units']) && ! empty($floor_data['units'])) {
                        logger('floor units => ', $floor_data['units']);
                        foreach ($floor_data['units'] as $unit_data) {

                            $unitId = ! empty($unit_data['id']) ? (int) $unit_data['id'] : null;
                            if ($isUpdate && $unitId) {
                                $unit = PropertyUnit::find($unitId);
                                if ($unit) {
                                    $unit->update([
                                        'unit_name' => $unit_data['unit_name'],
                                        'unit_type' => $unit_data['unit_type'],
                                    ]);
                                }
                            } else {
                                $unit = PropertyUnit::create([
                                    'floor_id' => $floor->id,
                                    'unit_name' => $unit_data['unit_name'] ?? null,
                                    'unit_type' => $unit_data['unit_type'] ?? null,
                                ]);
                            }

                            if (! $unit) {
                                continue;
                            }

                            //  ROOMS inside unit
                            if (! empty($unit_data['rooms'])) {
                                foreach ($unit_data['rooms'] as $room_data) {
                                    $roomId = ! empty($room_data['id']) ? (int) $room_data['id'] : null;
                                    $roomAttributes = [
                                        'room_type' => $room_data['room_type'],
                                        'has_attached_bathroom' => ! empty($room_data['has_attached_bathroom']),
                                        'has_attached_ac' => ! empty($room_data['has_attached_ac']),
                                        'has_attached_balcony' => ! empty($room_data['has_attached_balcony']),
                                        'has_attached_wardrobe' => ! empty($room_data['has_attached_wardrobe']),
                                    ];

                                    if ($isUpdate && $roomId) {
                                        $room = Room::find($roomId);
                                        if ($room) {
                                            $room->update($roomAttributes);
                                        }
                                    } else {
                                        logger('unit room not found');
                                        $room = Room::create(array_merge($roomAttributes, [
                                            'floor_id' => $floor->id,
                                            'unit_id' => $unit->id,
                                        ]));
                                    }

                                    if (! $room) {
                                        continue;
                                    }

                                    $this->syncRoomDimensions($room, $room_data['dimensions'] ?? [], $isUpdate);
                                }
                            }
                        }
                    }

                    // ROOMS directly on floor
                    if (! empty($floor_data['rooms'])) {
                        foreach ($floor_data['rooms'] as $room_data) {
                            if (empty($room_data['room_type'])) {
                                continue;
                            }

                            $roomId = ! empty($room_data['id']) ? (int) $room_data['id'] : null;

                            $roomAttributes = [
                                'room_type' => $room_data['room_type'],
                                'has_attached_bathroom' => ! empty($room_data['has_attached_bathroom']),
                                'has_attached_ac' => ! empty($room_data['has_attached_ac']),
                                'has_attached_balcony' => ! empty($room_data['has_attached_balcony']),
                                'has_attached_wardrobe' => ! empty($room_data['has_attached_wardrobe']),
                            ];

                            if ($isUpdate && $roomId) {
                                $room = Room::find($roomId);

                                if ($room) {
                                    $room->update($roomAttributes);
                                }
                            } else {
                                $room = Room::create(array_merge($roomAttributes, [
                                    'floor_id' => $floor->id,
                                    'unit_id' => null,
                                ]));
                            }

                            if (! $room) {
                                continue;
                            }
                            $this->syncRoomDimensions($room, $room_data['dimensions'] ?? [], $isUpdate);
                        }
                    }
                }

                if ($isUpdate) {
                    return redirect()->back()->with('success', 'Construction details updated successfully.');
                }

                return redirect()->route('blocks.view', $block->uuid)
                    ->with('success', 'Construction details saved and property created successfully.');
            }

        } catch (QueryException $e) {
            if ($e->getCode() == '22003') {
                return back()->withErrors(['size' => 'Size exceeds allowed limit (max: 999999.9999)']);
            }
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function syncRoomDimensions(Room $room, array $dimensions, bool $isUpdate): void
    {
        foreach ($dimensions as $dim) {
            if (empty($dim['name'])) {
                continue;
            }

            $converted_size = $this->convertForSystem($dim['size'], $dim['unit']);
            $dimId = ! empty($dim['id']) ? (int) $dim['id'] : null;

            if ($isUpdate && $dimId) {
                $existing = $room->dimensions()->find($dimId);
                if ($existing) {
                    $existing->update([
                        'name' => $dim['name'],
                        'size' => $converted_size,
                        'unit' => $dim['unit'],
                    ]);

                    continue;
                }
            }

            // No id, or id not found → create
            $room->dimensions()->create([
                'name' => $dim['name'],
                'size' => $converted_size,
                'unit' => $dim['unit'],
            ]);
        }
    }

    public function propertyDetails(Request $request, $uuid)
    {
        $property = Property::where('uuid', $uuid)->with('block')->firstOrFail();
        $total_area = GetArea::calculate($property->dimensions);

        return view('content.property management.blocks.properties.property_details', compact('property', 'total_area'));
    }

    //    Delete property
    private function getConstraintMessage($section)
    {
        return match ($section) {
            'floor' => 'Cannot delete floor because it has units.',
            'unit' => 'Cannot delete unit because it has rooms.',
            'room' => 'Cannot delete room because it is in use.',
            default => 'Cannot delete this record due to related data.',
        };
    }

    public function destroy($section, $uuid)
    {
        try {
            if (! in_array($section, ['floor', 'unit', 'room'])) {
                return back()->with([
                    'error' => 'Invalid section type.',
                ], 400);
            }

            // Resolve model dynamically
            switch ($section) {
                case 'floor':
                    $model = Floor::where('uuid', $uuid)->first();
                    break;

                case 'unit':
                    $model = PropertyUnit::where('uuid', $uuid)->first();
                    break;

                case 'room':
                    $model = Room::where('uuid', $uuid)->first();
                    break;
            }

            // Check if record exists
            if (! $model) {
                return back()->with([
                    'error' => ucfirst($section).' not found.',
                ], 404);
            }

            $model->delete();

            return back()->with([
                'status' => true,
                'success' => ucfirst($section).' deleted successfully.',
            ]);

        } catch (QueryException $e) {
            // MySQL foreign key error code = 1451
            if ($e->errorInfo[1] == 1451) {
                return back()->with([
                    'error' => $this->getConstraintMessage($section),
                ], 400);
            }

            return back()->with([
                'error' => 'Database error occurred.',
            ], 500);

        } catch (\Exception $e) {
            return back()->with([
                'error' => $e->getMessage(),
            ], 500);
        }

    }
}
