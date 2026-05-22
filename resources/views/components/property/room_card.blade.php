@php
  use App\Helpers\GetArea;
//  dd($room_types);
@endphp
<div class="border rounded-3 overflow-hidden">
  <div class="room-header-bg d-flex align-items-center justify-content-between px-2 py-2">
    <div class="d-flex align-items-center gap-2">
      <div class="room-dot"></div>
      @php $room_type_id = is_array($room) ? ($room['room_type'] ?? null) : $room->room_type; @endphp
      <span
        class="room-name text-dark fw-semibold">{{ ucwords($room_types->firstWhere('id', $room_type_id)?->title) }}</span>
    </div>

    @if(!is_array($room))
      <a href="{{ route('property.destroy', ['section'=>'room', $room->uuid]) }}"
         onclick="confirmDelete(event, null)"
         class="btn-del-icon text-decoration-none" title="Delete room">
        <i class="fa-regular fa-trash-can"></i>
      </a>
    @endif

  </div>
  <div class="p-2">
    <div class="d-flex flex-wrap gap-1 mb-2">
      @php
        $room_amenities = is_array($room) ? ($room['amenities'] ?? []) : json_decode($room->amenities ?? '[]', true);
      @endphp
      @foreach($amenities->whereIn('id', $room_amenities) as $a)
        <span class="amenity-chip d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1">
       <i class="ti tabler-check text-success"></i> {{ ucwords($a->title) }}
       </span>
      @endforeach
    </div>
    @php $dimensions = is_array($room) ? [] : $room->dimensions; @endphp
    @if(count($dimensions))
      <div class="d-flex flex-column gap-1">
        <div class="d-flex flex-wrap gap-1">
          @foreach($dimensions as $dim)
            @php $val = app()->make(\App\Http\Controllers\PropertiesController::class)->convertForUser($dim->size, $dim->unit); @endphp
            <span class="dim-side-chip d-inline-flex align-items-center gap-1 rounded px-2 py-1">
              <span class="dim-side-name">{{ ucfirst($dim->name) }}</span>
              {{ round($val, 2) }}
              <span class="dim-side-unit ">{{ $dim->unit }}</span>
            </span>
          @endforeach
        </div>
        <span class="area-chip d-inline-flex align-items-center gap-1 rounded px-2 py-1 align-self-start">
          <span class="area-val">{{ GetArea::calculate($room->dimensions) }}</span>
          <span class="area-unit">sq ft</span>
        </span>
      </div>
    @endif
  </div>
</div>
