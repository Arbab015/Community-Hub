@php
  $amenities = [];
  if($room->has_attached_bathroom) $amenities[] = ['icon'=>'tabler-bath',           'label'=>'Bathroom'];
  if($room->has_attached_ac)       $amenities[] = ['icon'=>'tabler-air-conditioning','label'=>'AC'];
  if($room->has_attached_balcony)  $amenities[] = ['icon'=>'tabler-building-arch',  'label'=>'Balcony'];
  if($room->has_attached_wardrobe) $amenities[] = ['icon'=>'tabler-hanger',         'label'=>'Wardrobe'];
  use App\Helpers\GetArea;
@endphp
<div class="border rounded-3 overflow-hidden">
  <div class="room-header-bg d-flex align-items-center justify-content-between px-2 py-2">
    <div class="d-flex align-items-center gap-2">
      <div class="room-dot"></div>
      <span class="room-name text-dark fw-semibold">{{ ucfirst(str_replace('_',' ',$room->room_type)) }}</span>
    </div>
    <a href="{{ route('property.destroy', ['section'=>'room', $room->uuid]) }}"
       onclick="confirmDelete(event, null)"
       class="btn-del-icon text-decoration-none" title="Delete room">
      <i class="fa-regular fa-trash-can"></i>
    </a>
  </div>
  <div class="p-2">
    <div class="d-flex flex-wrap gap-1 mb-2">
      @foreach($amenities as $a)
        <span class="amenity-chip d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1">
          <i class="ti {{ $a['icon'] }}"></i>{{ $a['label'] }}
        </span>
      @endforeach
    </div>
    @if($room->dimensions->count())
      <div class="d-flex flex-column gap-1">
        <div class="d-flex flex-wrap gap-1">
          @foreach($room->dimensions as $dim)
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
