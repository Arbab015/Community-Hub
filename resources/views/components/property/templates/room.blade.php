@php
  $p        = $rPrefix ?? '__PREFIX__[rooms][__ROOM__]';
  $roomData = $room    ?? [];
  // $fi, $ui (optional, only for unit-rooms), $ri — set when called from old-data loop
  $raw = $roomData['amenities'] ?? [];
$roomAmenities = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
  // Error key base differs: unit-room vs floor-room
  $errBase = isset($fi, $ui, $ri)
      ? "floors.$fi.units.$ui.rooms.$ri"
      : (isset($fi, $ri) ? "floors.$fi.rooms.$ri" : null);
@endphp

@isset($rPrefix)
  <div class="room-item border rounded-3 mb-2 overflow-hidden">
    @else
      <template id="tpl-room">
        <div class="room-item border rounded-3 mb-2 overflow-hidden">
          @endisset

          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom room_header">
    <span class="d-flex align-items-center gap-2 fw-semibold text-primary card_title">
      <span
        class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle card_title title_icon">
        <i class="ti tabler-door"></i>
      </span>
      Room @isset($ri)
        {{ $ri + 1 }}
      @endisset
    </span>
            <button type="button"
                    class="btn btn-sm btn-remove-room d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_item">
              <i class="ti tabler-trash" style="font-size:.75rem;"></i> Remove
            </button>
          </div>

          <div class="p-3">
            <div class="row g-3 mb-3">
              <div class="col-md-5">
                <label class="form-label fw-semibold small text-uppercase text-muted required">Room Type</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="ti tabler-door text-muted"></i></span>
                  <select name="{{ $p }}[room_type]"
                          class="form-select border-start-0 @isset($errBase) @error($errBase.'.room_type') is-invalid @enderror @endisset"
                          required>
                    <option value="" disabled selected>Select type</option>
                    @foreach($room_types as $room_type)
                      <option value="{{ $room_type->id }}" @isset($rPrefix)
                        {{ ($roomData['room_type'] ?? '') == $room_type->id ? 'selected' : '' }}
                        @endisset>
                        {{ ucwords($room_type->title) }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @isset($errBase)
                  @error($errBase.'.room_type')
                  <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
                  @enderror
                @endisset
              </div>
            </div>

            <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
            <div class="d-flex flex-wrap gap-3 mb-3">
              @foreach($amenities as $amenity)
                <label
                  class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer">
                  <input class="form-check-input mt-0" type="checkbox"
                         name="{{ $p }}[amenities][]"
                         value="{{ $amenity->id }}" @isset($rPrefix)
                    {{ in_array($amenity->id, $roomAmenities) ? 'checked' : '' }}
                    @endisset>
                  <span class="small">{{ ucwords($amenity->title) }}</span>
                </label>
              @endforeach
            </div>

            <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Room Dimensions</label>
            <div class="dim-block">
              <div class="dim-rows">
                @isset($rPrefix)
                  @foreach($roomData['dimensions'] ?? [] as $di => $dim)
                    @include('components.property.templates.dimension', compact('p','di','dim','errBase'))
                  @endforeach
                @endisset
              </div>
              <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">
                <i class="ti tabler-plus me-1"></i> Add Dimension
              </button>
            </div>
          </div>

          @isset($rPrefix)
        </div>
      @else
  </div>
  </template>
@endisset
