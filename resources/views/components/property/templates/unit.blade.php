@php
  $p        = $uPrefix  ?? '__PREFIX__[units][__UNIT__]';
  $unitData = $unit     ?? [];
  $noRooms  = $noRooms  ?? false;
  // For error keys: only available when $fi and $ui are set
@endphp

@isset($uPrefix)
  <div class="unit-item border rounded-3 mb-2 overflow-hidden">
    @else
      <template id="tpl-unit">
        <div class="unit-item border rounded-3 mb-2 overflow-hidden">
          @endisset

          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom unit_header">
    <span class="d-flex align-items-center gap-2 fw-semibold text-success card_title">
      <span
        class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle title_icon">
        <i class="ti tabler-home" style="font-size:.78rem;"></i>
      </span>
      Unit @isset($ui)
        {{ $ui + 1 }}
      @endisset
    </span>
            <button type="button"
                    class="btn btn-sm btn-remove-unit d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_item">
              <i class="ti tabler-trash" style="font-size:.75rem;"></i> Remove
            </button>
          </div>

          <div class="p-3">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Name</label>
                <input type="text" name="{{ $p }}[unit_name]"
                       value="@isset($uPrefix){{ $unitData['unit_name'] ?? '' }}@endisset"
                       class="form-control @isset($fi,$ui) @error("floors.$fi.units.$ui.unit_name") is-invalid @enderror @endisset"
                       placeholder="e.g. Suite A" required>
                @isset($fi,$ui)
                  @error("floors.$fi.units.$ui.unit_name")
                  <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
                  @enderror
                @endisset
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Type</label>
                <select name="{{ $p }}[unit_type]"
                        class="form-select @isset($fi,$ui) @error("floors.$fi.units.$ui.unit_type") is-invalid @enderror @endisset"
                        required>
                  <option value="" disabled selected>Select type</option>
                  @foreach($unit_types as $unit_type)
                    <option value="{{ $unit_type->id }}" @isset($uPrefix)
                      {{ ($unitData['unit_type'] ?? '') == $unit_type->id ? 'selected' : '' }}
                      @endisset>
                      {{ ucwords($unit_type->title) }}
                    </option>
                  @endforeach
                </select>
                @isset($fi,$ui)
                  @error("floors.$fi.units.$ui.unit_type")
                  <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
                  @enderror
                @endisset
              </div>
            </div>

            <div class="form-check mt-3 mb-2">
              <input class="form-check-input no_rooms_check" type="checkbox"
                     name="{{ $p }}[no_rooms]" value="1" @isset($uPrefix)
                {{ $noRooms ? 'checked' : '' }}
                @endisset>
              <label class="form-check-label fw-semibold">This unit consists of a single room only and has no further
                room divisions.</label>
            </div>

            <div
              class="unit-amenities-section mt-2 @isset($uPrefix) {{ $noRooms ? '' : 'd-none' }} @else d-none @endisset">
              <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
              @php $raw = $unitData['amenities'] ?? [];
$unitAmenities = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw; @endphp
              <div class="d-flex flex-wrap gap-3 mb-3">
                @foreach($amenities as $amenity)
                  <label
                    class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer">
                    <input class="form-check-input mt-0" type="checkbox"
                           name="{{ $p }}[amenities][]"
                           value="{{ $amenity->id }}" @isset($uPrefix)
                      {{ in_array($amenity->id, $unitAmenities) ? 'checked' : '' }}
                      @endisset>
                    <span class="small">{{ ucwords($amenity->title) }}</span>
                  </label>
                @endforeach
              </div>
            </div>

            <div class="unit-dimension-section @isset($uPrefix) {{ $noRooms ? '' : 'd-none' }} @else d-none @endisset">
              <label class="small form-label fw-semibold text-uppercase text-muted">Unit Dimensions</label>
              <div class="mb-3 dim-block" data-prefix="{{ $p }}">
                <div class="dim-rows">
                  @isset($uPrefix)
                    @php $errBase = isset($fi, $ui) ? "floors.$fi.units.$ui" : null; @endphp
                    @foreach($unitData['dimensions'] ?? [] as $di => $dim)
                      @include('components.property.templates.dimension', compact('p','di','dim','errBase'))
                    @endforeach
                  @endisset
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim"
                        style="font-size:.72rem;">
                  <i class="ti tabler-plus me-1"></i> Add Dimension
                </button>
              </div>
            </div>

            <div class="unit-rooms-section mt-3 @isset($uPrefix) {{ $noRooms ? 'd-none' : '' }} @endisset">
              <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms in this unit</p>
              <div class="unit-rooms-container">
                @isset($uPrefix)
                  @foreach($unitData['rooms'] ?? [] as $ri => $room)
                    @php $rPrefix = "{$p}[rooms][$ri]"; @endphp
                    @include('components.property.templates.room', compact('rPrefix','ri','room','fi','ui'))
                  @endforeach
                @endisset
              </div>
              <button type="button"
                      class="btn btn-sm btn-add-unit-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                      data-room-count="@isset($uPrefix){{ count($unitData['rooms'] ?? []) }}@else 0 @endisset">
                <i class="ti tabler-plus me-1"></i> Add Room
              </button>
            </div>
          </div>

          @isset($uPrefix)
        </div>
      @else
  </div>
  </template>
@endisset
