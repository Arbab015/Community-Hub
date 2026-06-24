<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        //        dd($request->all());
        $isEdit = $request->input('section') === 'edit';
        $request->validate([
            'floor_id' => 'required|exists:floors,id',
            'room_id' => $isEdit ? 'required|exists:rooms,id' : 'nullable',
            'room_type' => 'required|string|max:50',
            'dimensions' => 'required|array|min:2|max:8',
            'dimensions.*.name' => 'required|string|max:20|distinct',
            'dimensions.*.size' => 'required|numeric|min:0|max:999999.9999',
            'dimensions.*.unit' => 'required|string|in:feet,meter,yard,inch',
            'amenities' => 'nullable|array',
            'amenities.*' => 'integer|exists:property_attributes,id',
        ]);

        try {
            $attributes = [
                'room_type' => $request->room_type,
                'amenities' => json_encode($request->input('amenities', [])),
            ];

            if ($isEdit) {
                $room = Room::findOrFail($request->room_id);
                abort_unless((int) $room->floor_id === (int) $request->floor_id, 403, 'Room does not belong to this floor.');
                $room->update($attributes);
            } else {
                $room = Room::create(array_merge($attributes, [
                    'floor_id' => $request->floor_id,
                    'unit_id' => null,
                ]));
            }

            $this->syncDimensions($room, $request->input('dimensions', []));

            return back()->with('success', $isEdit ? 'Room updated successfully.' : 'Room added successfully.');

        } catch (QueryException $e) {
            if ($e->getCode() == '22003') {
                return back()->withErrors(['size' => 'Size exceeds allowed limit (max: 999999.9999)']);
            }
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function syncDimensions(Room $room, array $dimensions): void
    {
        $submittedIds = [];

        foreach ($dimensions as $dim) {
            if (empty($dim['name'])) {
                continue;
            }

            $size = PropertiesController::convertForSystem((float) $dim['size'], $dim['unit']);
            $dimId = ! empty($dim['id']) ? (int) $dim['id'] : null;

            if ($dimId && $existing = $room->dimensions()->find($dimId)) {
                $existing->update(['name' => $dim['name'], 'size' => $size, 'unit' => $dim['unit']]);
                $submittedIds[] = $dimId;
            } else {
                $new = $room->dimensions()->create(['name' => $dim['name'], 'size' => $size, 'unit' => $dim['unit']]);
                $submittedIds[] = $new->id;
            }
        }

        $room->dimensions()->whereNotIn('id', $submittedIds)->delete();
    }

    /** Render the room modal partial (used by JS fetch). */
    public function getDimensions(Room $room)
    {
        return response()->json($room->load('dimensions'));
    }
}
