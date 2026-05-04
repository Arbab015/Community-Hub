{{--
  Partial: resources/views/components/property/_edit_room_card.blade.php
  Variables:
    $rPrefix   – form name prefix  e.g. "floors[0][rooms][0]"
    $room      – Eloquent Room model
    $deleteUrl – route('room.destroy', $room->id)
--}}
<div class="room-item border rounded-3 mb-2 overflow-hidden">
  <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom room_header">
    <span class="d-flex align-items-center gap-2 fw-semibold text-primary card_title">
      <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle card_title title_icon">
        <i class="ti tabler-door"></i>
      </span>
      {{ ucfirst(str_replace('_', ' ', $room->room_type)) }}
    </span>
{{--    <button type="button"--}}
{{--            class="btn btn-sm btn-delete-room d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_item"--}}
{{--            data-url="{{ $deleteUrl }}"--}}
{{--    >--}}
{{--      <i class="ti tabler-trash" style="font-size:.75rem;"></i> Delete--}}
{{--    </button>--}}
  </div>
  <div class="p-3">
    <input type="hidden" name="{{ $rPrefix }}[id]" value="{{ $room->id }}">

    {{-- Room Type --}}
    <div class="row g-3 mb-3">
      <div class="col-md-5">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Room Type</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="ti tabler-door text-muted"></i></span>
          <select name="{{ $rPrefix }}[room_type]" class="form-select border-start-0" required>
            <option value="" disabled selected>Select type</option>
            <option value="bedroom"         @selected($room->room_type === 'bedroom')>Bedroom</option>
            <option value="guest_room"      @selected($room->room_type === 'guest_room')>Guest Room</option>
            <option value="drawing_room"    @selected($room->room_type === 'drawing_room')>Drawing Room</option>
            <option value="dining_room"     @selected($room->room_type === 'dining_room')>Dining Room</option>
            <option value="kitchen"         @selected($room->room_type === 'kitchen')>Kitchen</option>
            <option value="bathroom"        @selected($room->room_type === 'bathroom')>Bathroom</option>
            <option value="washroom"        @selected($room->room_type === 'washroom')>Washroom</option>
            <option value="store_room"      @selected($room->room_type === 'store_room')>Store Room</option>
            <option value="office_room"     @selected($room->room_type === 'office_room')>Office Room</option>
            <option value="servant_quarter" @selected($room->room_type === 'servant_quarter')>Servant Quarter</option>
            <option value="other"           @selected($room->room_type === 'other')>Other</option>
          </select>
        </div>
      </div>
    </div>

    {{-- Amenities --}}
    <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
    <div class="d-flex flex-wrap gap-3 mb-3">
      <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer" >
        <input class="form-check-input mt-0" type="checkbox" name="{{ $rPrefix }}[has_attached_bathroom]" value="1" @checked($room->has_attached_bathroom)>
        <i class="ti tabler-bath text-muted"></i><span class="small">Attached Bathroom</span>
      </label>
      <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer" >
        <input class="form-check-input mt-0" type="checkbox" name="{{ $rPrefix }}[has_attached_ac]" value="1" @checked($room->has_attached_ac)>
        <i class="ti tabler-air-conditioning text-muted"></i><span class="small">AC</span>
      </label>
      <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer" >
        <input class="form-check-input mt-0" type="checkbox" name="{{ $rPrefix }}[has_attached_balcony]" value="1" @checked($room->has_attached_balcony)>
        <i class="ti tabler-building-arch text-muted"></i><span class="small">Balcony</span>
      </label>
      <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer" >
        <input class="form-check-input mt-0" type="checkbox" name="{{ $rPrefix }}[has_attached_wardrobe]" value="1" @checked($room->has_attached_wardrobe)>
        <i class="ti tabler-hanger text-muted"></i><span class="small">Wardrobe</span>
      </label>
    </div>

    {{-- Room Dimensions --}}
    <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Room Dimensions</label>
    <div class="dim-block">
      <div class="dim-rows">
        @foreach($room->dimensions as $di => $dim)
          @php
            $converted = app()->make(\App\Http\Controllers\PropertiesController::class)->convertForUser($dim->size, $dim->unit);
          @endphp
          <div class="dimension-row row g-3 mb-2 align-items-end rounded-3 p-2 mx-0">
            <input type="hidden" name="{{ $rPrefix }}[dimensions][{{ $di }}][id]" value="{{ $dim->id }}">
            <div class="col-md-4">
              <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="ti tabler-ruler text-muted"></i></span>
                <input type="text" name="{{ $rPrefix }}[dimensions][{{ $di }}][name]"
                       value="{{ $dim->name }}" required
                       class="form-control border-start-0" placeholder="e.g. Length, Width, Front, Right">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="ti tabler-number text-muted"></i></span>
                <input type="number" name="{{ $rPrefix }}[dimensions][{{ $di }}][size]"
                       value="{{ round($converted, 2) }}" step="0.01" required
                       class="form-control border-start-0" placeholder="e.g 40, 12.21">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
              <select name="{{ $rPrefix }}[dimensions][{{ $di }}][unit]" class="form-select" required>
                <option value="" disabled selected>Select Unit</option>
                <option value="feet"  @selected($dim->unit === 'feet')>Feet</option>
                <option value="meter" @selected($dim->unit === 'meter')>Meter</option>
                <option value="yard"  @selected($dim->unit === 'yard')>Yard</option>
              </select>
            </div>
            <div class="col-md-1">
              <button type="button" class="btn btn-remove-dim" title="Remove">
                <i class="ti tabler-x icon-lg text-danger"></i>
              </button>
            </div>
          </div>
        @endforeach
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">
        <i class="ti tabler-plus me-1"></i> Add Dimension
      </button>
    </div>
  </div>
</div>
