<div class="room-item border rounded-3 mb-2 overflow-hidden">
  <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom room_header">
    <span class="d-flex align-items-center gap-2 fw-semibold text-primary card_title">
      <span
        class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle card_title title_icon">
        <i class="ti tabler-door"></i>
      </span>
      {{ $room_types->firstWhere('id', $room->room_type)?->title }}
    </span>

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
            @foreach($room_types as $room_type)
              <option value="{{ $room_type->id }}"
                @selected(old($rPrefix . '.room_type', $room->room_type ?? null) == $room_type->id)>
                {{ ucwords($room_type->title) }}
              </option>
            @endforeach
          </select>
        </div>
      </div>
    </div>

    {{-- Amenities --}}
    <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
    @php
      $selectedAmenities = json_decode($room->amenities, true) ?? [];
    @endphp

    <div class="d-flex flex-wrap gap-3 mb-3">
      @foreach($amenities as $amenity)
        <label
          class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer">

          <input class="form-check-input mt-0"
                 type="checkbox"
                 name="{{ $rPrefix }}[amenities][]"
                 value="{{ $amenity->id }}"
            @checked(in_array($amenity->id, $selectedAmenities))>
          <span class="small">{{ ucwords($amenity->title) }}</span>
        </label>
      @endforeach
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
                <option value="inch" @selected($dim->unit === 'inch')>Inch</option>
                <option value="feet" @selected($dim->unit === 'feet')>Feet</option>
                <option value="meter" @selected($dim->unit === 'meter')>Meter</option>
                <option value="yard" @selected($dim->unit === 'yard')>Yard</option>
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
