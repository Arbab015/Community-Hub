{{-- MODAL: Edit Basic Information --}}
<div class="modal fade" id="edit_basic_property" tabindex="-1" aria-labelledby="editBasicInfoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow p-4" >

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="editBasicInfoModalLabel">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          Edit Basic Information
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" action="{{ route('property.store') }}">
        @csrf
        <input type="hidden" name="section"      value="property">
        <input type="hidden" name="property_id"  value="{{ $property->id }}">
        <input type="hidden" name="request_type" value="property_info">
        <input type="hidden" name="block_id"     value="{{ $property->block_id }}">
        <div class="modal-body pt-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.05em;">Property Name</label>
              <input type="text" name="name" class="form-control"
                     value="{{ old('name', $property->name) }}" placeholder="e.g. Plot A-12">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.05em;">Property No.</label>
              <input type="text" name="property_no" class="form-control"
                     value="{{ old('property_no', $property->property_no) }}" placeholder="e.g. A-12">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.05em;">Category</label>
              <select name="category" id="category" class="form-select">
                @foreach(['residential','commercial','other'] as $cat)
                  <option value="{{ $cat }}" @selected(strtolower($property->category) === $cat)>
                    {{ ucfirst($cat) }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.05em;">Type</label>
              <select name="type" id="type" class="form-select"
                      data-old="{{ old('type', $property->type) }}">
                <option value="" disabled>Select category first…</option>
              </select>
            </div>

            {{-- Construction Status: hidden for plots via JS --}}
            <div class="col-md-6 cons-wrapper {{ $property->type === 'plot' ? 'd-none' : '' }}" id="constructed_wrapper">
              <label class="form-label fw-semibold small text-uppercase text-muted"  style="letter-spacing:.05em;">Construction Status</label>
              <div class="d-flex gap-3" id="">
                <label for="cons_constructed" id="label_constructed" class="const_labels">
                  <input type="radio" id="cons_constructed" name="const_status" value="constructed" @checked($property->const_status === "constructed") hidden >

                  <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="ti tabler-check fs-4 text-success"></i>
                    <span class="small fw-semibold">Constructed</span>
                  </div>
                </label>
                <label for="cons_in_progress" id="label_progress" class="const_labels">
                  <input type="radio" id="cons_in_progress" name="const_status" value="in_progress" @checked($property->const_status === "in_progress") hidden >
                  <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="ti tabler-clock fs-4 text-primary"></i>
                    <span class="small fw-semibold text-primary">In Progress</span>
                  </div>
                </label>
                <label for="cons_pending" id="label_pending" class="const_labels">
                  <input type="radio" id="cons_pending" name="const_status"  @checked($property->const_status === "pending")
                          value="pending" hidden>
                  <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="ti tabler-loader fs-4 text-warning"></i>
                    <span class="small fw-semibold text-primary">Pending</span>
                  </div>
                </label>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.05em;">Address</label>
              <input type="text" name="address" class="form-control"
                     value="{{ old('address', $property->address) }}" placeholder="Street address">
            </div>

          </div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- MODAL: Edit Property Dimensions  --}}
<div class="modal fade" id="edit_dimensions_modal" tabindex="-1" aria-labelledby="editDimensionsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="editDimensionsModalLabel">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          Edit Property Dimensions
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form  method="POST" action="{{ route('property.store') }}" id="edit_dimensions_form">
        @csrf
        <input type="hidden" name="section" value="property">
        <input type="hidden" name="property_id" value="{{ $property->id }}">
        <input type="hidden" name="request_type" value="property_dimension">
        <input type="hidden" name="block_id" value="{{ $property->block_id }}">
        <div class="modal-body pt-3">
          @include('components.property.dimensions')
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

{{-- MODAL: Edit Construction Details --}}

<div class="modal fade" id="edit_construction_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">

      <div class="modal-header border-bottom px-4 py-3">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-1">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          Edit Construction Details
          — <span class="text-muted badge bg-label-info   ms-1">  {{ $property->property_no }}</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body px-4 py-3">
        <form method="POST" action="{{ route('property.store') }}" id="construction_form">
          @csrf
          <input type="hidden" name="section"     value="construction">
          <input type="hidden" name="property_id" value="{{ $property->id }}">
          <input type="hidden" name="block_id"    value="{{ $property->block_id }}">
          <input type="hidden" name="request_type" value="property_const">

          <div id="edit-floors-container">

            @foreach($property->floors as $fi => $floor)
              @php $fPrefix = "floors[$fi]"; @endphp

              <div class="floor-item border rounded-3 mb-3 overflow-hidden shadow-sm"
                   data-floor-id="{{ $floor->id }}"
                   data-floor-prefix="{{ $fPrefix }}">

                {{-- Floor header --}}
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom floor_header">
                  <span class="d-flex align-items-center gap-2 fw-bold text-primary floor_title">
                    <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle floor_icon">
                      <i class="ti tabler-building" style="font-size:.85rem;"></i>
                    </span>
                    {{ ucfirst($floor->floor_type) }}
                  </span>
{{--                  <button type="button"--}}
{{--                          class="btn btn-sm btn-delete-floor d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_floor"--}}
{{--                          data-url="{{ route('floor.destroy', $floor->id) }}">--}}{{-->--}}
{{--                    <i class="ti tabler-trash" style="font-size:.8rem;"></i> Delete Floor--}}
{{--                  </button>--}}
                </div>

                <div class="p-3">
                  <input type="hidden" name="{{ $fPrefix }}[id]" value="{{ $floor->id }}">

                  {{-- Floor Type select --}}
                  <div class="row g-3 mb-3">
                    <div class="col-md-4">
                      <label class="form-label fw-semibold small text-uppercase text-muted required">Floor Type</label>
                      <select name="{{ $fPrefix }}[floor_type]" class="form-select" required>
                        <option value="" disabled selected>Select Floor Type</option>
                        <option value="basement"     @selected(strtolower($floor->floor_type) === 'basement')>Basement</option>
                        <option value="ground"       @selected(strtolower($floor->floor_type) === 'ground')>Ground Floor</option>
                        <option value="first floor"  @selected(strtolower($floor->floor_type) === 'first floor')>1st Floor</option>
                        <option value="second floor" @selected(strtolower($floor->floor_type) === 'second floor')>2nd Floor</option>
                        <option value="third floor"  @selected(strtolower($floor->floor_type) === 'third floor')>3rd Floor</option>
                        <option value="fourth floor" @selected(strtolower($floor->floor_type) === 'fourth floor')>4th Floor</option>
                        <option value="fifth floor"  @selected(strtolower($floor->floor_type) === 'fifth floor')>5th Floor</option>
                        <option value="top floor"    @selected(strtolower($floor->floor_type) === 'top floor')>Top Floor</option>
                      </select>
                    </div>
                  </div>

                  {{-- Units section (commercial only) --}}
                  @if($property->category === 'commercial')
                    <div class="units-section-wrapper">
                      <div class="form-check mt-3 mb-2">
                        @php
                        $has_units = $floor->units && $floor->units->count();
                        @endphp
                        @if($has_units)
                        <input type="hidden" name="{{ $fPrefix }}[has_units]" value="1">
                        @endif
                        <input class="form-check-input has_units_check" type="checkbox"
                               name="{{ $fPrefix }}[has_units]" value="1" disabled
                          @checked($has_units)>
                        <label class="form-check-label fw-semibold">
                          This floor has units (apartments, offices, shops, etc.)
                        </label>
                      </div>

                      <div class="units-section mt-2 @if(!($floor->units && $floor->units->count())) d-none @endif">
                        <p class="small fw-semibold text-uppercase text-muted mb-2" style="font-size:.7rem;letter-spacing:.05em;">Units</p>

                        <div class="floor-units-container">
                          @foreach($floor->units as $ui => $unit)
                            @php $uPrefix = "{$fPrefix}[units][$ui]"; @endphp

                            <div class="unit-item border rounded-3 mb-2 overflow-hidden"
                                 data-unit-id="{{ $unit->id }}">

                              <div class="align-items-center px-3 py-2 border-bottom ">
                                <span class="d-flex align-items-center gap-2 fw-semibold text-success card_title">
                                  <span class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle title_icon">
                                    <i class="ti tabler-home" style="font-size:.78rem;"></i>
                                  </span>
                                  Unit — {{ $unit->unit_name }}
                                  @if($unit->unit_type)
                                    <span class="badge bg-label-secondary align-items-center ms-1 w-auto" style="font-size:.6rem;">{{ ucfirst($unit->unit_type) }}</span>
                                  @endif
                                </span>
{{--                                <button type="button"--}}
{{--                                        class="btn btn-sm btn-delete-unit d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_item"--}}
{{--                                        data-url="{{ route('unit.destroy', $unit->id) }}"--}}{{-->--}}
{{--                                  <i class="ti tabler-trash" style="font-size:.75rem;"></i> Delete Unit--}}
{{--                                </button>--}}
                              </div>

                              <div class="p-3">
                                <input type="hidden" name="{{ $uPrefix }}[id]" value="{{ $unit->id }}">
                                <div class="row g-3 mb-3">
                                  <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Name</label>
                                    <input type="text" name="{{ $uPrefix }}[unit_name]"
                                           value="{{ $unit->unit_name }}"
                                           class="form-control" placeholder="e.g. Suite A" required>
                                  </div>
                                  <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Type</label>
                                    <select name="{{ $uPrefix }}[unit_type]" class="form-select" required>
                                      <option value="" disabled selected>Select type</option>
                                      <option value="apartment" @selected($unit->unit_type === 'apartment')>Apartment</option>
                                      <option value="office"    @selected($unit->unit_type === 'office')>Office</option>
                                      <option value="shop"      @selected($unit->unit_type === 'shop')>Shop</option>
                                      <option value="studio"    @selected($unit->unit_type === 'studio')>Studio</option>
                                      <option value="other"     @selected($unit->unit_type === 'other')>Other</option>
                                    </select>
                                  </div>
                                </div>

                                <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms in this unit</p>
                                <div class="unit-rooms-container">
                                  @foreach($unit->rooms as $ri => $room)
                                    @php $rPrefix = "{$uPrefix}[rooms][$ri]"; @endphp
                                    @include('components.property.edit_room_card', [
                                      'rPrefix'   => $rPrefix,
                                      'room'      => $room,
//                                      'deleteUrl' => route('room.destroy', $room->id),
                                    ])
                                  @endforeach
                                </div>
                                <button type="button"
                                        class="btn btn-sm btn-add-unit-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                                        data-room-count="{{ $unit->rooms->count() }}">
                                  <i class="ti tabler-plus me-1"></i> Add Room
                                </button>
                              </div>
                            </div>
                          @endforeach
                        </div>

                        <button type="button"
                                class="btn btn-sm btn-add-unit d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_unit_btn"
                                data-floor-prefix="{{ $fPrefix }}"
                                data-unit-count="{{ $floor->units->count() }}">
                          <i class="ti tabler-plus me-1"></i> Add Unit
                        </button>
                      </div>
                    </div>
                  @endif

                  {{-- Rooms directly on floor --}}
                  <div class="floor-rooms-section mt-3">
                    <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms on this floor</p>
                    <div class="floor-rooms-container">
                      @foreach($floor->rooms as $ri => $room)
                        @php $rPrefix = "{$fPrefix}[rooms][$ri]"; @endphp
                        @include('components.property.edit_room_card', [
                          'rPrefix'   => $rPrefix,
                          'room'      => $room,
//                         'deleteUrl' => route('room.destroy', $room->id),
                        ])
                      @endforeach
                    </div>
                    <button type="button"
                            class="btn btn-sm btn-add-floor-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                            data-floor-prefix="{{ $fPrefix }}"
                            data-room-count="{{ $floor->rooms->count() }}">
                      <i class="ti tabler-plus me-1"></i> Add Room
                    </button>
                  </div>

                </div>
              </div>
            @endforeach

          </div>{{-- /#edit-floors-container --}}

          <button type="button" id="edit-btn-add-floor"
                  class="btn btn-sm d-inline-flex align-items-center gap-1 mt-2 rounded-pill px-3 py-2 fw-semibold"
                  style="font-size:.78rem; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border:none; box-shadow:0 2px 8px rgba(102,126,234,.35);">
            <i class="ti tabler-plus me-1"></i> Add Floor
          </button>

        </form>
      </div>

      <div class="modal-footer border-top px-4 py-3">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="construction_form" class="btn btn-primary px-4 fw-bold">
          <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
        </button>
      </div>

    </div>
  </div>
</div>

@include('components.property.templates.dimension')
@include('components.property.templates.room')
@include('components.property.templates.unit')
@include('components.property.templates.floor')



