<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Jobs\ImportProperties;
use App\Models\Attachment;
use App\Models\Block;
use App\Models\Floor;
use App\Models\Property;
use App\Models\PropertyAttribute;
use App\Models\PropertyUnit;
use App\Models\Room;
use App\Models\Society;
use App\Services\FileServices;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PropertiesController extends Controller
{
    public function download($type)
    {
        if ($type === 'template') {
            $path = public_path('templates_&_guidelines/Property-template.csv');

            return response()->download($path, 'Property Template.csv');
        }
        if ($type === 'guidelines') {
            $path = public_path('templates_&_guidelines/Import-Properties-Guidelines.txt');

            return response()->download($path, 'Import Properties Guidelines.txt');
        }

    }

    public static function convertForSystem($value, $from)
    {
        return match ($from) {
            'meter' => $value * 3.28084,
            'yard' => $value * 3,
            'feet' => $value,
            'inch' => $value / 12,
        };
    }

    public function convertForUser($value_in_feet, $to_unit)
    {
        return match ($to_unit) {
            'meter' => $value_in_feet / 3.28084,
            'yard' => $value_in_feet / 3,
            'feet' => $value_in_feet,
            'inch' => $value_in_feet * 12,
        };
    }

    public function propertiesFromBlocksShow(Request $request, $uuid, $isPending = null)
    {
        return $this->show($request, $uuid, $isPending);
    }

    public function propertiesFromSocietyShow(Request $request, $user_type, $society_uuid, $uuid)
    {
        return $this->show($request, $uuid, $user_type, $society_uuid);
    }

    public function show(Request $request, $uuid, $user_type = null, $society_uuid = null, $isPending = null)
    {
        $block = Block::where('uuid', $uuid)->first();
        $skip = $request->input('skip', 0);
        $total_skip = 4 + $skip;
        $take = 4;
        $society = Society::where('uuid', $society_uuid)->first();
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
        $query->when($request->search_content, function ($q) use ($request) {
            $search = $request->search_content;
            $q->where(function ($subQ) use ($search) {
                $subQ->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('property_no', 'LIKE', "%{$search}%");
            });
        });

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

        if ($isPending) {
            session()->flash('success', 'Property created successfully.');
        }

        return view(
            'content.property_management.blocks.show',
            compact(
                'society',
                'user_type',
                'block',
                'properties',
                'total_skip',
                'property_categories',
                'property_types',
                'total_properties'
            )
        );

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

        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        $property_attributes = PropertyAttribute::where('owner_id', $scope['ownerId'])->get();
        $floor_types = $property_attributes->where('type', 'floor_type');
        $unit_types = $property_attributes->where('type', 'unit_type');
        $room_types = $property_attributes->where('type', 'room_type');
        $amenities = $property_attributes->where('type', 'amenity');

        return view('content.property_management.blocks.properties.create', compact('block', 'property', 'tab', 'property_attributes', 'floor_types', 'unit_types', 'room_types', 'amenities'));
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
                    'name' => 'nullable|string|max:100',
                    'property_no' => [
                        'required', 'string', 'max:50',
                        Rule::unique('properties', 'property_no')
                            ->where(fn ($q) => $q->whereIn('block_id', $society->blocks()->pluck('id')))
                            ->ignore($property?->id),
                    ],
                    'type' => 'required|string|max:20',
                    'street' => 'required|string|max:50',
                    'landmark' => 'nullable|string|max:50',
                    'construction_status' => 'nullable|string|in:constructed,in_progress,pending',
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

            $unit_name_rule = [
                'required', 'string', 'max:50',
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
                'floors.*.units.*.unit_type' => 'required|string|max:50',

                'floors.*.units.*.amenities' => 'nullable|array',
                'floors.*.units.*.dimensions' => 'required_if:floors.*.units.*.no_rooms,1|array|min:2|max:8',
                'floors.*.units.*.dimensions.*.name' => 'required_if:floors.*.units.*.no_rooms,1|string|max:20',
                'floors.*.units.*.dimensions.*.size' => 'required_if:floors.*.units.*.no_rooms,1|numeric|min:0|max:999999.99',
                'floors.*.units.*.dimensions.*.unit' => 'required_if:floors.*.units.*.no_rooms,1|string|in:inch,feet,meter,yard',
                'floors.*.units.*.rooms.*.room_type' => 'required|string|max:50',
                'floors.*.units.*.rooms.*.dimensions' => 'required|array|min:2|max:8',
                'floors.*.units.*.rooms.*.dimensions.*.name' => 'required|string|max:20',
                'floors.*.units.*.rooms.*.dimensions.*.size' => 'required|numeric|min:0|max:999999.99',
                'floors.*.units.*.rooms.*.dimensions.*.unit' => 'required|string|in:inch,feet,meter,yard',
                'floors.*.units.*.rooms.*.amenities' => 'nullable|array',

                'floors.*.rooms.*.room_type' => 'required|string|max:50',
                'floors.*.rooms.*.dimensions' => 'required|array|min:2|max:8',
                'floors.*.rooms.*.dimensions.*.name' => 'required|string|max:20',
                'floors.*.rooms.*.dimensions.*.size' => 'required|numeric|min:0|max:999999.99',
                'floors.*.rooms.*.dimensions.*.unit' => 'required|string|in:inch,feet,meter,yard',
                'floors.*.rooms.*.amenities' => 'nullable|array',
            ], [], [
                // custom attribute names — replaces the dot-index path with readable labels
                'floors.*.floor_type' => 'floor type',
                'floors.*.units.*.unit_name' => 'unit name',
                'floors.*.units.*.unit_type' => 'unit type',
                'floors.*.units.*.dimensions' => 'unit dimensions',
                'floors.*.units.*.dimensions.*.name' => 'dimension name',
                'floors.*.units.*.dimensions.*.size' => 'dimension size',
                'floors.*.units.*.dimensions.*.unit' => 'dimension unit',
                'floors.*.units.*.rooms.*.room_type' => 'room type',
                'floors.*.units.*.rooms.*.dimensions' => 'room dimensions',
                'floors.*.units.*.rooms.*.dimensions.*.name' => 'dimension name',
                'floors.*.units.*.rooms.*.dimensions.*.size' => 'dimension size',
                'floors.*.units.*.rooms.*.dimensions.*.unit' => 'dimension unit',
                'floors.*.rooms.*.room_type' => 'room type',
                'floors.*.rooms.*.dimensions' => 'room dimensions',
                'floors.*.rooms.*.dimensions.*.name' => 'dimension name',
                'floors.*.rooms.*.dimensions.*.size' => 'dimension size',
                'floors.*.rooms.*.dimensions.*.unit' => 'dimension unit',
            ]);

        }

        // STORE / UPDATE
        try {
            //            dd($request->floors);
            // PROPERTY BASIC
            if ($section === 'property') {
                $is_pending = $request->construction_status === 'pending';
                if ($property && ($request_type === 'property_info' || $request_type === 'on_create')) {
                    $isNotPending = $property->construction_status !== 'pending';
                    $property->update([
                        'name' => $request->get('name'),
                        'category' => $request->category,
                        'property_no' => $request->property_no,
                        'type' => $request->type,
                        'street' => $request->street,
                        'landmark' => $request->landmark,
                        'construction_status' => $request->construction_status,
                    ]);

                    if ($isNotPending && $request->construction_status === 'pending') {
                        logger($property);
                        $property->update(['isLocked' => true]);
                    } else {
                        $property->update(['isLocked' => false]);
                    }

                } elseif ($request_type === 'on_create' && ! isset($property)) {
                    $property = Property::create([
                        'block_id' => $block->id,
                        'name' => $request->get('name'),
                        'category' => $request->category,
                        'property_no' => $request->property_no,
                        'type' => $request->type,
                        'street' => $request->street,
                        'landmark' => $request->landmark,
                        'construction_status' => $request->construction_status,
                    ]);
                }

                if ($request_type === 'property_dimension' || $request_type === 'on_create') {
                    $this->syncRoomDimensions($property, $request->dimensions, $property && $request->property_id);
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

                $newAttachmentIds = [];
                $mainPicId = null;

                if ($request->hasFile('main_pic')) {
                    $ids = app(FileServices::class)->compressAndStore(
                        $request->file('main_pic'), $property, true, true
                    );
                    $mainPicId = $ids[0] ?? null;
                    $newAttachmentIds = array_merge($newAttachmentIds, $ids);
                }

                if ($request->hasFile('documents')) {
                    $ids = app(FileServices::class)->compressAndStore(
                        $request->file('documents'), $property, false
                    );
                    $newAttachmentIds = array_merge($newAttachmentIds, $ids);
                }

                if ($request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Files uploaded successfully.',
                        'attachment_ids' => $newAttachmentIds,
                        'main_pic_id' => $mainPicId,
                    ]);
                }

                if ($request_type === 'document' || $request_type === 'media') {
                    return back()->with('success', 'Documents updated successfully.');
                }
            }

            // CONSTRUCTION  —  CREATE (on_create)  or  UPDATE (property_const)
            if ($section === 'construction') {
                $isUpdate = $request_type === 'property_const';
                foreach ($request->floors as $floor_data) {
                    //  FLOOR
                    $floorId = ! empty($floor_data['id']) ? (int) $floor_data['id'] : null;
                    if ($isUpdate && $floorId) {
                        $floor = Floor::find($floorId);
                        if ($floor) {
                            $floor->update(['floor_type' => $floor_data['floor_type']]);
                        }
                    } else {
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
                                    'amenities' => isset($unit_data['amenities'])
                                      ? json_encode($unit_data['amenities'])
                                      : json_encode([]),
                                ]);
                            }

                            if (! empty($unit_data['no_rooms']) && ! empty($unit_data['amenities']) && ! empty($unit_data['dimensions'])) {
                                $unit->update([
                                    'amenities' => json_encode($unit_data['amenities']),
                                ]);
                                $this->syncRoomDimensions($unit, $unit_data['dimensions'], false);
                            } elseif (! empty($unit_data['rooms'])) {
                                foreach ($unit_data['rooms'] as $room_data) {
                                    $roomId = ! empty($room_data['id']) ? (int) $room_data['id'] : null;
                                    $roomAttributes = [
                                        'room_type' => $room_data['room_type'],
                                        'amenities' => isset($room_data['amenities'])
                                          ? json_encode($room_data['amenities'])
                                          : json_encode([]),
                                    ];

                                    if ($isUpdate && $roomId) {
                                        $room = Room::find($roomId);
                                        if ($room) {
                                            $room->update($roomAttributes);
                                        }
                                    } else {
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
                                'amenities' => isset($room_data['amenities'])
                                  ? json_encode($room_data['amenities'])
                                  : json_encode([]),
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

                return redirect()->route('block.view', $block->uuid)
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

    private function syncRoomDimensions(\Illuminate\Database\Eloquent\Model $model, array $dimensions, bool $isUpdate): void
    {
        $submitted_Ids = [];

        foreach ($dimensions as $dim) {
            if (empty($dim['name'])) {
                continue;
            }

            $converted_size = $this->convertForSystem($dim['size'], $dim['unit']);
            $dimId = ! empty($dim['id']) ? (int) $dim['id'] : null;

            if ($isUpdate && $dimId) {
                $existing = $model->dimensions()->find($dimId);
                if ($existing) {
                    $existing->update([
                        'name' => $dim['name'],
                        'size' => $converted_size,
                        'unit' => $dim['unit'],
                    ]);
                    $submitted_Ids[] = $dimId;

                    continue; // prevent from duplication
                }
            }

            // No id or not found → create new
            $newDim = $model->dimensions()->create([
                'name' => $dim['name'],
                'size' => $converted_size,
                'unit' => $dim['unit'],
            ]);
            $submitted_Ids[] = $newDim->id;
        }

        $model->dimensions()
            ->whereNotIn('id', $submitted_Ids)
            ->delete();
    }

    public function propertyDetails($uuid)
    {
        return $this->details(null, null, $uuid);
    }

    public function societyDetailsFromSociety($user_type, $society_uuid, $uuid)
    {
        return $this->details($user_type, $society_uuid, $uuid);
    }

    public function details($user_type, $society_uuid, $uuid)
    {
        $property = Property::where('uuid', $uuid)->with('block')->first();
        //        dd($property);
        $society = $property->block->society ?? null;

        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        $property_attributes = PropertyAttribute::where('owner_id', $scope['ownerId'])->get();

        $floor_types = $property_attributes->where('type', 'floor_type');
        $unit_types = $property_attributes->where('type', 'unit_type');
        $room_types = $property_attributes->where('type', 'room_type');
        $amenities = $property_attributes->where('type', 'amenity');

        return view(
            'content.property_management.blocks.properties.property_details',
            compact('property', 'floor_types', 'unit_types', 'room_types', 'amenities', 'society', 'user_type')
        );
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

        } catch (\Exception $e) {
            return back()->with([
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimetypes:text/csv,text/plain',
            'block_id' => 'required|exists:blocks,id',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->storeAs('imports', time().' '.$file->getClientOriginalName(), 'public');
            $block = Block::find($request->block_id);
            $fullPath = Storage::disk('public')->path($path);

            $result = ImportProperties::validate($fullPath, $block->id);
            if (! $result['success']) {
                return response()->json(['errors' => $result['errors']]);
            }
            $block->update(['import_properties_progress' => 0]);
            ImportProperties::dispatch($result['rows'], $block->id);

            return response()->json(['success' => true]);

        } catch (Exception $e) {
            return response()->json(['errors' => [$e->getMessage()]], 500);
        }
    }

    public function importProgress($block_uuid)
    {
        $block = Block::where('uuid', $block_uuid)->firstOrFail();

        return response()->json(['progress' => $block->import_properties_progress ?? 0]);
    }

    public function fileUploadProgress(Request $request)
    {
        $ids = explode(',', $request->ids);

        return Attachment::whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->map(fn ($item) => [
                'progress' => $item->progress,
            ]);
    }
}
