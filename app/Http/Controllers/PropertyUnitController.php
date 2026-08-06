<?php

namespace App\Http\Controllers;

use App\Models\Floor;
use App\Models\InstallmentTemplate;
use App\Models\PaymentTemplate;
use App\Models\PropertyAttribute;
use App\Models\PropertyUnit;
use App\Models\Room;
use App\Services\FileServices;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class PropertyUnitController extends Controller
{
    public function unitFromSociety($user_type, $society_uuid, $unit_uuid)
    {
        return $this->show($unit_uuid, null, $user_type, $society_uuid);
    }

    public function show($uuid, $isOwner = null, $user_type = null, $society_uuid = null)
    {
        $unit = PropertyUnit::where('uuid', $uuid)
            ->with([
                'floor.property.block.society',
                'dimensions',
                'attachment',
                'attachments',
                'rooms.dimensions',
                'listing',
                'plan.paymentTemplate',
                'plan.installmentAttachments.installmentTemplate',
            ])
            ->firstOrFail();
        $society = $unit->floor->property->block->society ?? null;

        $property_attributes = PropertyAttribute::orderBy('created_at', 'asc')->get();

        $floor_types = $property_attributes->where('type', 'floor_type');
        $unit_types = $property_attributes->where('type', 'unit_type');
        $room_types = $property_attributes->where('type', 'room_type');
        $amenities = $property_attributes->where('type', 'amenity');

        $paymentsPlans = PaymentTemplate::where('owner_id', Auth()->id())->get();
        $installmentTemplates = InstallmentTemplate::where('owner_id', Auth()->id())->get();

        return view(
            'content.property_management.blocks.properties.unit_details',
            compact('unit', 'society', 'user_type', 'floor_types', 'isOwner', 'unit_types', 'paymentsPlans', 'installmentTemplates', 'room_types', 'amenities')
        );
    }

    public function update(Request $request)
    {
        $section = $request->input('section');

        // Unit may not exist yet (creating a new one) — resolve via floor_id instead.
        $unit = $request->filled('unit_id') ? PropertyUnit::find($request->unit_id) : null;
        $floor = $unit ? $unit->floor : Floor::find($request->floor_id);

        if (! $floor) {
            return back()->with('error', 'Floor not found.');
        }

        $property_id = $floor->property_id;

        // QUICK RENAME (unit_details page "Edit Unit" modal — name/type only)
        if ($section === 'basic') {
            $request->validate([
                'unit_id' => 'required|exists:property_units,id',
                'unit_name' => [
                    'nullable', 'string', 'max:50',
                    function ($attribute, $value, $fail) use ($property_id, $unit) {
                        $floorIds = Floor::where('property_id', $property_id)->pluck('id');
                        $exists = PropertyUnit::whereIn('floor_id', $floorIds)
                            ->where('id', '!=', $unit->id)
                            ->whereRaw('LOWER(unit_name) = ?', [strtolower($value)])
                            ->exists();
                        if ($exists) {
                            $fail('The unit name has already been taken.');
                        }
                    },
                ],
                'unit_type' => 'nullable|string|max:50',
            ]);
        }

        // ADD UNIT (unit_modal, "Add Unit") — basic info + either a single-space
        // unit (dimensions + amenities on the unit) or a set of inline rooms,
        // each of which now always carries its own dimensions.
        if ($section === 'unit') {
            $request->validate([
                'floor_id' => 'required|exists:floors,id',
                'unit_name' => [
                    'required', 'string', 'max:50',
                    function ($attribute, $value, $fail) use ($property_id) {
                        $floorIds = Floor::where('property_id', $property_id)->pluck('id');
                        $exists = PropertyUnit::whereIn('floor_id', $floorIds)
                            ->whereRaw('LOWER(unit_name) = ?', [strtolower($value)])
                            ->exists();
                        if ($exists) {
                            $fail('The unit name has already been taken.');
                        }
                    },
                ],
                'unit_type' => 'required|string|max:50',
                'no_rooms' => 'nullable|boolean',
                'amenities' => 'nullable|array',
                'amenities.*' => 'integer|exists:property_attributes,id',

                'dimensions' => 'required_if:no_rooms,1|array|min:2|max:8',
                'dimensions.*.name' => 'required_if:no_rooms,1|string|max:20|distinct',
                'dimensions.*.size' => ['required_if:no_rooms,1', 'numeric', 'min:0', 'max:999999.9999'],
                'dimensions.*.unit' => 'required_if:no_rooms,1|string|in:feet,meter,yard,inch',

                'rooms' => 'nullable|array',
                'rooms.*.room_type' => 'required_with:rooms|string|max:50',
                'rooms.*.amenities' => 'nullable|array',
                'rooms.*.amenities.*' => 'integer|exists:property_attributes,id',
                'rooms.*.dimensions' => 'required_with:rooms|array|min:2|max:8',
                'rooms.*.dimensions.*.name' => 'required_with:rooms|string|max:20',
                'rooms.*.dimensions.*.size' => ['required_with:rooms', 'numeric', 'min:0', 'max:999999.9999'],
                'rooms.*.dimensions.*.unit' => 'required_with:rooms|string|in:feet,meter,yard,inch',
            ], [], [
          'rooms.*.room_type' => 'room type',
          'rooms.*.dimensions' => 'room dimensions',
          'rooms.*.dimensions.*.name' => 'dimension name',
          'rooms.*.dimensions.*.size' => 'dimension size',
          'rooms.*.dimensions.*.unit' => 'dimension unit',
      ]);
        }

        // EDIT UNIT (unit_modal, "Edit Unit" when the unit has — or is switched
        // back to having — rooms). Updates basic info and fully syncs the
        // submitted rooms[] (create / update / delete stale ones).
        if ($section === 'unit_full') {
            $request->validate([
                'unit_id' => 'required|exists:property_units,id',
                'unit_name' => [
                    'required', 'string', 'max:50',
                    function ($attribute, $value, $fail) use ($property_id, $unit) {
                        $floorIds = Floor::where('property_id', $property_id)->pluck('id');
                        $exists = PropertyUnit::whereIn('floor_id', $floorIds)
                            ->where('id', '!=', $unit->id)
                            ->whereRaw('LOWER(unit_name) = ?', [strtolower($value)])
                            ->exists();
                        if ($exists) {
                            $fail('The unit name has already been taken.');
                        }
                    },
                ],
                'unit_type' => 'required|string|max:50',

                'rooms' => 'required|array|min:1',
                'rooms.*.id' => [
                    'nullable', 'integer', 'exists:rooms,id',
                    function ($attribute, $value, $fail) use ($unit) {
                        $room = Room::find($value);
                        if ($room && (int) $room->unit_id !== (int) $unit->id) {
                            $fail('One of the submitted rooms does not belong to this unit.');
                        }
                    },
          ],
                'rooms.*.room_type' => 'required|string|max:50',
                'rooms.*.amenities' => 'nullable|array',
                'rooms.*.amenities.*' => 'integer|exists:property_attributes,id',
                'rooms.*.dimensions' => 'required|array|min:2|max:8',
                'rooms.*.dimensions.*.id' => 'nullable|integer|exists:dimensions,id',
                'rooms.*.dimensions.*.name' => 'required|string|max:20',
                'rooms.*.dimensions.*.size' => ['required', 'numeric', 'min:0', 'max:999999.9999'],
                'rooms.*.dimensions.*.unit' => 'required|string|in:feet,meter,yard,inch',
            ], [], [
          'rooms.*.room_type' => 'room type',
          'rooms.*.dimensions' => 'room dimensions',
          'rooms.*.dimensions.*.name' => 'dimension name',
          'rooms.*.dimensions.*.size' => 'dimension size',
          'rooms.*.dimensions.*.unit' => 'dimension unit',
      ]);
        }

        // DOCUMENTS
        if ($section === 'documents') {
            $request->validate([
                'unit_id' => 'required|exists:property_units,id',
                'main_pic' => 'nullable|image|max:20000',
                'documents.*' => match ($request->input('request_type')) {
                    'document' => ['file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:20000'],
                    'media' => ['file', 'mimes:jpg,jpeg,png,gif,svg,mp4,mov,avi,webm,mkv', 'max:20000'],
                    default => ['file', 'mimes:jpg,jpeg,png,gif,svg,mp4,mov,avi,webm,mkv,pdf,doc,docx,xls,xlsx', 'max:20000'],
                },
            ]);
        }

        // SPECIFICATIONS (no-rooms unit: dimensions + amenities on the unit itself)
        if ($section === 'specifications') {
            $request->validate([
                'unit_id' => 'required|exists:property_units,id',
                'dimensions' => 'required|array|min:2|max:8',
                'dimensions.*.name' => 'required|string|max:20|distinct',
                'dimensions.*.size' => ['required', 'numeric', 'min:0', 'max:999999.9999'],
                'dimensions.*.unit' => 'required|string|in:feet,meter,yard,inch',
                'amenities' => 'nullable|array',
                'amenities.*' => 'integer|exists:property_attributes,id',
            ]);
        }

        // ADD ROOM
        if ($section === 'add_room') {
            $request->validate([
                'unit_id' => 'required|exists:property_units,id',
                'room_type' => 'required|string|max:50',
                'dimensions' => 'required|array|min:2|max:8',
                'dimensions.*.name' => ['required', 'string', 'max:20', 'distinct'],
                'dimensions.*.size' => ['required', 'numeric', 'min:0', 'max:999999.9999'],
                'dimensions.*.unit' => 'required|string|in:feet,meter,yard,inch',
                'amenities' => 'nullable|array',
                'amenities.*' => 'integer|exists:property_attributes,id',
            ]);
        }

        // EDIT ROOM
        if ($section === 'edit_room') {
            $request->validate([
                'unit_id' => 'required|exists:property_units,id',
                'room_id' => 'required|exists:rooms,id',
                'room_type' => 'required|string|max:50',
                'dimensions' => 'required|array|min:2|max:8',
                'dimensions.*.name' => ['required', 'string', 'max:20', 'distinct'],
                'dimensions.*.size' => ['required', 'numeric', 'min:0', 'max:999999.9999'],
                'dimensions.*.unit' => 'required|string|in:feet,meter,yard,inch',
                'amenities' => 'nullable|array',
                'amenities.*' => 'integer|exists:property_attributes,id',
            ]);

            $room = Room::findOrFail($request->room_id);
            if ((int) $room->unit_id !== (int) $unit->id) {
                abort(403, 'This room does not belong to the given unit.');
            }
        }

        try {

            // BASIC
            if ($section === 'basic') {
                $unit->update([
                    'unit_name' => $request->unit_name,
                    'unit_type' => $request->unit_type,
                ]);

                return back()->with('success', 'Unit updated successfully.');
            }

            // ADD UNIT — single-space (dimensions + amenities) or with inline rooms
            // (each room created with its own dimensions via syncDimensions).
            if ($section === 'unit') {
                $unit = PropertyUnit::create([
                    'floor_id' => $floor->id,
                    'unit_name' => $request->unit_name,
                    'unit_type' => $request->unit_type,
                    'amenities' => json_encode([]),
                ]);

                if ($request->boolean('no_rooms')) {
                    $this->syncDimensions($unit, $request->input('dimensions', []));
                    $unit->update(['amenities' => json_encode($request->input('amenities', []))]);
                } else {
                    foreach ($request->input('rooms', []) as $room_data) {
                        if (empty($room_data['room_type'])) {
                            continue;
                        }

                        $room = $unit->rooms()->create([
                            'floor_id' => $unit->floor_id,
                            'room_type' => $room_data['room_type'],
                            'amenities' => json_encode($room_data['amenities'] ?? []),
                        ]);

                        $this->syncDimensions($room, $room_data['dimensions'] ?? []);
                    }
                }

                return back()->with('success', 'Unit added successfully.');
            }

            // EDIT UNIT (has rooms) — update basic info, clear unit-level specs,
            // and fully sync the submitted rooms (create / update / delete stale ones).
            if ($section === 'unit_full') {
                $unit->update([
                    'unit_name' => $request->unit_name,
                    'unit_type' => $request->unit_type,
                    'amenities' => json_encode([]),
                ]);
                $unit->dimensions()->delete();

                $this->syncRooms($unit, $request->input('rooms', []));

                return back()->with('success', 'Unit updated successfully.');
            }

            // DOCUMENTS
            if ($section === 'documents') {
                $newAttachmentIds = [];
                $mainPicId = null;

                if ($request->hasFile('main_pic')) {
                    $ids = app(FileServices::class)->compressAndStore($request->file('main_pic'), $unit, true, true);
                    $mainPicId = $ids[0] ?? null;
                    $newAttachmentIds = array_merge($newAttachmentIds, is_array($ids) ? $ids : []);
                }

                if ($request->hasFile('documents')) {
                    $ids = app(FileServices::class)->compressAndStore($request->file('documents'), $unit, false);
                    $newAttachmentIds = array_merge($newAttachmentIds, is_array($ids) ? $ids : []);
                }

                if ($request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Files uploaded successfully.',
                        'attachment_ids' => $newAttachmentIds,
                        'main_pic_id' => $mainPicId,
                    ]);
                }

                return back()->with('success', 'Files uploaded successfully.');
            }

            // SPECIFICATIONS
            if ($section === 'specifications') {
                $this->syncDimensions($unit, $request->input('dimensions', []));
                $unit->update(['amenities' => json_encode($request->input('amenities', []))]);

                return back()->with('success', 'Specifications updated successfully.');
            }

            // ADD ROOM
            if ($section === 'add_room') {
                $room = $unit->rooms()->create([
                    'floor_id' => $unit->floor_id,
                    'room_type' => $request->room_type,
                    'amenities' => json_encode($request->input('amenities', [])),
                ]);

                $this->syncDimensions($room, $request->input('dimensions', []));

                return back()->with('success', 'Room added successfully.');
            }

            // EDIT ROOM
            if ($section === 'edit_room') {
                $room = Room::findOrFail($request->room_id);

                $room->update([
                    'room_type' => $request->room_type,
                    'amenities' => json_encode($request->input('amenities', [])),
                ]);

                $this->syncDimensions($room, $request->input('dimensions', []));

                return back()->with('success', 'Room updated successfully.');
            }

            return back()->with('error', 'Invalid section.');

        } catch (QueryException $e) {
            if ($e->getCode() == '22003') {
                return back()->withErrors(['size' => 'Size exceeds allowed limit (max: 999999.9999)']);
            }
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Create/update/delete a model's dimensions to match exactly what was submitted.
     * Shared by unit & room specification updates.
     */
    private function syncDimensions($model, array $dimensions): void
    {
        $submittedIds = [];

        foreach ($dimensions as $dim) {
            if (empty($dim['name'])) {
                continue;
            }

            $converted = PropertiesController::convertForSystem((float) $dim['size'], $dim['unit']);
            $dimId = ! empty($dim['id']) ? (int) $dim['id'] : null;

            if ($dimId && $existing = $model->dimensions()->find($dimId)) {
                $existing->update(['name' => $dim['name'], 'size' => $converted, 'unit' => $dim['unit']]);
                $submittedIds[] = $dimId;
            } else {
                $new = $model->dimensions()->create(['name' => $dim['name'], 'size' => $converted, 'unit' => $dim['unit']]);
                $submittedIds[] = $new->id;
            }
        }

        $model->dimensions()->whereNotIn('id', $submittedIds)->delete();
    }

    /**
     * Create/update/delete a unit's rooms (and each room's dimensions) to match
     * exactly what was submitted from the "Edit Unit" (unit_full) inline room cards.
     */
    private function syncRooms(PropertyUnit $unit, array $rooms): void
    {
        $submittedRoomIds = [];

        foreach ($rooms as $room_data) {
            if (empty($room_data['room_type'])) {
                continue;
            }

            $roomId = ! empty($room_data['id']) ? (int) $room_data['id'] : null;

            if ($roomId && $room = $unit->rooms()->find($roomId)) {
                $room->update([
                    'room_type' => $room_data['room_type'],
                    'amenities' => json_encode($room_data['amenities'] ?? []),
                ]);
            } else {
                $room = $unit->rooms()->create([
                    'floor_id' => $unit->floor_id,
                    'room_type' => $room_data['room_type'],
                    'amenities' => json_encode($room_data['amenities'] ?? []),
                ]);
            }

            $submittedRoomIds[] = $room->id;

            $this->syncDimensions($room, $room_data['dimensions'] ?? []);
        }

        $unit->rooms()->whereNotIn('id', $submittedRoomIds)->delete();
    }

    public function destroy($uuid, $user_type = null)
    {
        try {
            $unit = PropertyUnit::where('uuid', $uuid)->firstOrFail();
            $society = $unit->floor->property->block->society;
            $propertyUuid = $unit->floor->property->uuid;

            // Unit attachments
            $attachmentIds = $unit->attachments()->pluck('id')->toArray();
            if (! empty($attachmentIds)) {
                app(FileServices::class)->deleteByIds($attachmentIds);
            }

            // Unit's own dimensions (no-rooms unit specs)
            $unit->dimensions()->delete();

            // Unit's listing + inquiries
            if ($unit->listing) {
                $unit->listing->inquiries()->delete();
                $unit->listing->delete();
            }

            // Rooms — no attachments on rooms, just clear their dimensions before deleting
            foreach ($unit->rooms as $room) {
                $room->dimensions()->delete();
                $room->delete();
            }

            $unit->delete();

            if ($user_type) {
                return redirect()->route('society.property.details', [$user_type, $society->uuid, $propertyUuid])
                    ->with('success', 'Unit has been deleted successfully.');
            } else {
                return redirect()->route('property.details', [$propertyUuid])
                    ->with('success', 'Unit has been deleted successfully.');
            }

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function clone(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|exists:property_units,id',
            'prefix' => 'required|string|max:50',
            'start' => 'required|integer|min:1',
            'end' => 'required|integer|min:1|gte:start',
        ]);

        $count = $request->end - $request->start + 1;

        if ($count > 20) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum 20 units allowed per clone operation.',
            ], 422);
        }

        // Generate all unit names up front
        $names = [];
        for ($i = $request->start; $i <= $request->end; $i++) {
            $names[] = $request->prefix.'-'.$i;
        }

        // Load source unit
        $source = PropertyUnit::with(['rooms.dimensions', 'dimensions'])->findOrFail($request->unit_id);
        $floor = $source->floor;
        $property_id = $floor->property_id;

        // Check all names against existing units in the same property
        $floorIds = Floor::where('property_id', $property_id)->pluck('id');

        $conflicts = PropertyUnit::whereIn('floor_id', $floorIds)
            ->whereIn('unit_name', $names)
            ->pluck('unit_name')
            ->toArray();

        if (! empty($conflicts)) {
            return response()->json([
                'success' => false,
                'message' => 'Some unit names already exist in this property.',
                'conflicts' => $conflicts,
            ], 422);
        }

        // All clear — clone
        foreach ($names as $name) {
            $newUnit = PropertyUnit::create([
                'floor_id' => $floor->id,
                'unit_name' => $name,
                'unit_type' => $source->unit_type,
                'amenities' => $source->amenities,
            ]);

            // Clone dimensions (unit-level, for no-room units)
            foreach ($source->dimensions as $dim) {
                $newUnit->dimensions()->create([
                    'name' => $dim->name,
                    'size' => $dim->size,
                    'unit' => $dim->unit,
                ]);
            }

            // Clone rooms with their dimensions
            foreach ($source->rooms as $room) {
                $newRoom = $newUnit->rooms()->create([
                    'floor_id' => $floor->id,
                    'room_type' => $room->room_type,
                    'amenities' => $room->amenities,
                ]);

                foreach ($room->dimensions as $dim) {
                    $newRoom->dimensions()->create([
                        'name' => $dim->name,
                        'size' => $dim->size,
                        'unit' => $dim->unit,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($names).' unit(s) cloned successfully.',
        ]);
    }
}
