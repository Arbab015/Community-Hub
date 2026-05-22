@php
  $p         = $fPrefix ?? '__PREFIX__';
  $floorData = $floor    ?? [];
  $idx       = $fi       ?? null;
  $hasUnits  = $hasUnits ?? false;
@endphp

@isset($fPrefix)
  {{-- Blade old-data render: real div, no <template> wrapper --}}
  <div class="floor-item border rounded-3 mb-3 overflow-hidden shadow-sm">
    @else
      {{-- JS template: wrapped in <template> so browser ignores it until cloned --}}
      <template id="tpl-floor">
        <div class="floor-item border rounded-3 mb-3 overflow-hidden shadow-sm">
          @endisset

          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom floor_header">
    <span class="d-flex align-items-center gap-2 fw-bold text-primary floor_title">
      <span
        class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle floor_icon">
        <i class="ti tabler-building" style="font-size:.85rem;"></i>
      </span>
      Floor @isset($fi)
        {{ $fi + 1 }}
      @endisset
    </span>
            <button type="button"
                    class="btn btn-sm btn-remove-floor d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_floor">
              <i class="ti tabler-trash" style="font-size:.8rem;"></i> Remove Floor
            </button>
          </div>

          <div class="p-3">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold small text-uppercase text-muted required">Floor Type</label>
                <select name="{{ $p }}[floor_type]"
                        class="form-select @isset($fi) @error("floors.$fi.floor_type") is-invalid @enderror @endisset"
                        required>
                  <option value="" disabled selected>Select Floor Type</option>
                  @foreach($floor_types as $floor_type)
                    <option value="{{ $floor_type->id }}" @isset($fPrefix)
                      {{ ($floorData['floor_type'] ?? '') == $floor_type->id ? 'selected' : '' }}
                      @endisset>
                      {{ ucwords($floor_type->title) }}
                    </option>
                  @endforeach
                </select>
                @isset($fi)
                  @error("floors.$fi.floor_type")
                  <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
                  @enderror
                @endisset
              </div>
            </div>

            <div
              class="units-section-wrapper @isset($fPrefix) {{ isset($property) && $property->category !== 'residential' ? '' : 'd-none' }} @else d-none @endisset">
              <div class="form-check mt-3 mb-2">
                <input class="form-check-input has_units_check" type="checkbox"
                       name="{{ $p }}[has_units]" value="1" @isset($fPrefix)
                  {{ $hasUnits ? 'checked' : '' }}
                  @endisset>
                <label class="form-check-label fw-semibold">This floor has units (apartments, offices, shops,
                  etc.)</label>
              </div>
              <div class="units-section mt-2 @isset($fPrefix) {{ $hasUnits ? '' : 'd-none' }} @else d-none @endisset">
                <p class="small fw-semibold text-uppercase text-muted mb-2"
                   style="font-size:.7rem;letter-spacing:.05em;">Units</p>
                <div class="floor-units-container">
                  @isset($fPrefix)
                    @foreach($floorData['units'] ?? [] as $ui => $unit)
                      @php $uPrefix = "{$p}[units][$ui]"; $noRooms = !empty($unit['no_rooms']); @endphp
                      @include('components.property.templates.unit', compact('uPrefix','ui','unit','noRooms','fi'))
                    @endforeach
                  @endisset
                </div>
                <button type="button"
                        class="btn btn-sm btn-add-unit d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_unit_btn"
                        data-floor-prefix="{{ $p }}"
                        data-unit-count="@isset($fPrefix){{ count($floorData['units'] ?? []) }}@else 0 @endisset">
                  <i class="ti tabler-plus me-1"></i> Add Unit
                </button>
              </div>
            </div>

            <div
              class="floor-rooms-section mt-3 @isset($fPrefix) {{ empty($floorData['rooms']) ? 'd-none' : '' }} @endisset">
              <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms on this floor</p>
              <div class="floor-rooms-container">
                @isset($fPrefix)
                  @foreach($floorData['rooms'] ?? [] as $ri => $room)
                    @php $rPrefix = "{$p}[rooms][$ri]"; @endphp
                    @include('components.property.templates.room', compact('rPrefix','ri','room','fi'))
                  @endforeach
                @endisset
              </div>
              <button type="button"
                      class="btn btn-sm btn-add-floor-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                      data-floor-prefix="{{ $p }}"
                      data-room-count="@isset($fPrefix){{ count($floorData['rooms'] ?? []) }}@else 0 @endisset">
                <i class="ti tabler-plus me-1"></i> Add Room
              </button>
            </div>
          </div>

          @isset($fPrefix)
        </div>
      @else
  </div>
  </template>
@endisset
