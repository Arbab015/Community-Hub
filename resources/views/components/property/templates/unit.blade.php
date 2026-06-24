@php
  $p        = $uPrefix  ?? '__PREFIX__[units][__UNIT__]';
  $unitData = $unit     ?? [];
  $noRooms  = $noRooms  ?? false;
@endphp

@if(!empty($modalMode))
  {{-- ── Modal mode: flat field names, pre-filled from Eloquent $unit object ── --}}
  @php
    $modalUnit    = $unit instanceof \App\Models\PropertyUnit ? $unit : null;
    $isEditModal  = $modalUnit !== null;
    $modalUnitDims = $isEditModal ? $modalUnit->dimensions : collect();
    $modalNoRooms  = $isEditModal && $modalUnitDims->count() > 0;
    $raw3 = $modalUnit?->amenities ?? '[]';
    $modalUnitAmens = array_map('intval', is_array($raw3) ? $raw3 : (json_decode($raw3, true) ?? []));
  @endphp

  {{-- Unit Name & Type --}}
  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Name</label>
      <input type="text" name="unit_name" class="form-control"
             value="{{ $modalUnit?->unit_name ?? '' }}"
             placeholder="e.g. Suite A" required>
    </div>
    <div class="col-md-6">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Type</label>
      <select name="unit_type" class="form-select" required>
        <option value="" disabled selected>Select type</option>
        @foreach($unit_types as $unit_type)
          <option value="{{ $unit_type->id }}"
            {{ $isEditModal && (int)$modalUnit->unit_type === (int)$unit_type->id ? 'selected' : '' }}>
            {{ ucwords($unit_type->title) }}
          </option>
        @endforeach
      </select>
    </div>
  </div>

  {{-- No-rooms toggle --}}
  <div class="form-check mt-3 mb-2">
    <input class="form-check-input no_rooms_check" type="checkbox"
           name="no_rooms" value="1"
      {{ $modalNoRooms ? 'checked' : '' }}>
    <label class="form-check-label fw-semibold">This unit consists of a single room only and has no further
      room divisions.</label>
  </div>

  {{-- Amenities (no_rooms only) --}}
  <div class="unit-amenities-section mt-2 {{ $modalNoRooms ? '' : 'd-none' }}">
    <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
    <div class="d-flex flex-wrap gap-3 mb-3">
      @foreach($amenities as $amenity)
        <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer">
          <input class="form-check-input mt-0" type="checkbox"
                 name="amenities[]" value="{{ $amenity->id }}"
            {{ in_array((int)$amenity->id, $modalUnitAmens) ? 'checked' : '' }}>
          <span class="small">{{ ucwords($amenity->title) }}</span>
        </label>
      @endforeach
    </div>
  </div>

  {{-- Unit Dimensions (no_rooms only) --}}
  <div class="unit-dimension-section {{ $modalNoRooms ? '' : 'd-none' }}">
    <label class="small form-label fw-semibold text-uppercase text-muted">Unit Dimensions</label>
    <div class="mb-3 dim-block" data-prefix="modal_unit">
      <div class="dim-rows">
        @foreach($modalUnitDims as $di => $dim)
          @include('components.property.templates.dimension', ['modalMode' => true, 'di' => $di, 'dim' => $dim])
        @endforeach
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">
        <i class="ti tabler-plus me-1"></i> Add Dimension
      </button>
    </div>
  </div>

  {{-- Rooms section --}}
  <div class="unit-rooms-section mt-3 {{ $modalNoRooms ? 'd-none' : '' }}">
    <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms in this unit</p>

    @if($isEditModal)
      {{-- Edit mode: show existing rooms with Edit buttons that open room_modal --}}
      <div class="unit-rooms-list mb-2">
        @forelse($modalUnit->rooms as $existingRoom)
          @php
            $rTypeTitle = $room_types->firstWhere('id', $existingRoom->room_type)?->title ?? $existingRoom->room_type;
          @endphp
          <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2 mb-2">
            <span class="d-flex align-items-center gap-2 small fw-semibold">
              <i class="ti tabler-door text-muted"></i>
              {{ ucwords($rTypeTitle) }}
            </span>
            <button type="button"
                    class="btn btn-sm btn-outline-primary open-room-modal-from-unit"
                    data-room-id="{{ $existingRoom->id }}"
                    data-floor-id="{{ $modalUnit->floor_id }}"
                    data-unit-id="{{ $modalUnit->id }}">
              <i class="ti tabler-pencil me-1"></i> Edit Room
            </button>
          </div>
        @empty
          <p class="text-muted small fst-italic mb-2">No rooms yet.</p>
        @endforelse
      </div>
      {{-- Add new room from unit modal --}}
      <button type="button"
              class="btn btn-sm btn-outline-secondary open-room-modal-from-unit"
              data-room-id=""
              data-floor-id="{{ $modalUnit->floor_id }}"
              data-unit-id="{{ $modalUnit->id }}">
        <i class="ti tabler-plus me-1"></i> Add Room
      </button>
    @else
      {{-- Add mode: dynamic room container driven by edit_construction.js --}}
      <div id="um_rooms_container"></div>
      <button type="button"
              class="btn btn-sm btn-add-unit-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
              data-room-count="0">
        <i class="ti tabler-plus me-1"></i> Add Room
      </button>
    @endif
  </div>

@elseif(isset($uPrefix))
  {{-- ── Blade old-data render ── --}}
  <div class="unit-item border rounded-3 mb-2 overflow-hidden">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom unit_header">
      <span class="d-flex align-items-center gap-2 fw-semibold text-success card_title">
        <span
          class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle title_icon">
          <i class="ti tabler-home" style="font-size:.78rem;"></i>
        </span>
        Unit {{ isset($ui) ? $ui + 1 : '' }}
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
                 value="{{ $unitData['unit_name'] ?? '' }}"
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
              <option value="{{ $unit_type->id }}"
                {{ ($unitData['unit_type'] ?? '') == $unit_type->id ? 'selected' : '' }}>
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
               name="{{ $p }}[no_rooms]" value="1"
          {{ $noRooms ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold">This unit consists of a single room only and has no further
          room divisions.</label>
      </div>

      <div class="unit-amenities-section mt-2 {{ $noRooms ? '' : 'd-none' }}">
        <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
        @php $raw = $unitData['amenities'] ?? [];
        $unitAmenities = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw; @endphp
        <div class="d-flex flex-wrap gap-3 mb-3">
          @foreach($amenities as $amenity)
            <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer">
              <input class="form-check-input mt-0" type="checkbox"
                     name="{{ $p }}[amenities][]"
                     value="{{ $amenity->id }}"
                {{ in_array($amenity->id, $unitAmenities) ? 'checked' : '' }}>
              <span class="small">{{ ucwords($amenity->title) }}</span>
            </label>
          @endforeach
        </div>
      </div>

      <div class="unit-dimension-section {{ $noRooms ? '' : 'd-none' }}">
        <label class="small form-label fw-semibold text-uppercase text-muted">Unit Dimensions</label>
        <div class="mb-3 dim-block" data-prefix="{{ $p }}">
          <div class="dim-rows">
            @php $errBase = isset($fi, $ui) ? "floors.$fi.units.$ui" : null; @endphp
            @foreach($unitData['dimensions'] ?? [] as $di => $dim)
              @include('components.property.templates.dimension', compact('p','di','dim','errBase'))
            @endforeach
          </div>
          <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">
            <i class="ti tabler-plus me-1"></i> Add Dimension
          </button>
        </div>
      </div>

      <div class="unit-rooms-section mt-3 {{ $noRooms ? 'd-none' : '' }}">
        <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms in this unit</p>
        <div class="unit-rooms-container">
          @foreach($unitData['rooms'] ?? [] as $ri => $room)
            @php $rPrefix = "{$p}[rooms][$ri]"; @endphp
            @include('components.property.templates.room', compact('rPrefix','ri','room','fi','ui'))
          @endforeach
        </div>
        <button type="button"
                class="btn btn-sm btn-add-unit-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                data-room-count="{{ count($unitData['rooms'] ?? []) }}">
          <i class="ti tabler-plus me-1"></i> Add Room
        </button>
      </div>
    </div>
  </div>

@else
  {{-- ── JS template ── --}}
  <template id="tpl-unit">
    <div class="unit-item border rounded-3 mb-2 overflow-hidden">
      <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom unit_header">
        <span class="d-flex align-items-center gap-2 fw-semibold text-success card_title">
          <span
            class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle title_icon">
            <i class="ti tabler-home" style="font-size:.78rem;"></i>
          </span>
          Unit
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
            <input type="text" name="__PREFIX__[units][__UNIT__][unit_name]"
                   value="" class="form-control" placeholder="e.g. Suite A" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Type</label>
            <select name="__PREFIX__[units][__UNIT__][unit_type]" class="form-select" required>
              <option value="" disabled selected>Select type</option>
              @foreach($unit_types as $unit_type)
                <option value="{{ $unit_type->id }}">{{ ucwords($unit_type->title) }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="form-check mt-3 mb-2">
          <input class="form-check-input no_rooms_check" type="checkbox"
                 name="__PREFIX__[units][__UNIT__][no_rooms]" value="1">
          <label class="form-check-label fw-semibold">This unit consists of a single room only and has no further
            room divisions.</label>
        </div>

        <div class="unit-amenities-section mt-2 d-none">
          <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
          <div class="d-flex flex-wrap gap-3 mb-3">
            @foreach($amenities as $amenity)
              <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer">
                <input class="form-check-input mt-0" type="checkbox"
                       name="__PREFIX__[units][__UNIT__][amenities][]"
                       value="{{ $amenity->id }}">
                <span class="small">{{ ucwords($amenity->title) }}</span>
              </label>
            @endforeach
          </div>
        </div>

        <div class="unit-dimension-section d-none">
          <label class="small form-label fw-semibold text-uppercase text-muted">Unit Dimensions</label>
          <div class="mb-3 dim-block" data-prefix="__PREFIX__[units][__UNIT__]">
            <div class="dim-rows"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">
              <i class="ti tabler-plus me-1"></i> Add Dimension
            </button>
          </div>
        </div>

        <div class="unit-rooms-section mt-3">
          <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms in this unit</p>
          <div class="unit-rooms-container"></div>
          <button type="button"
                  class="btn btn-sm btn-add-unit-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                  data-room-count="0">
            <i class="ti tabler-plus me-1"></i> Add Room
          </button>
        </div>
      </div>
    </div>
  </template>
@endif
