{{-- MODAL: Edit Basic Information --}}
<div class="modal fade" id="edit_basic_property" tabindex="-1" aria-labelledby="editBasicInfoModalLabel"
     aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow p-4">

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="editBasicInfoModalLabel">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          Edit Basic Information
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" action="{{ route('property.store') }}">
        @csrf
        <input type="hidden" name="section" value="property">
        <input type="hidden" name="property_id" value="{{ $property->id }}">
        <input type="hidden" name="request_type" value="property_info">
        <input type="hidden" name="block_id" value="{{ $property->block_id }}">
        <div class="modal-body pt-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.05em;">Property
                Name</label>
              <input type="text" name="name" class="form-control"
                     value="{{ old('name', $property->name) }}" placeholder="e.g. Plot A-12">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted required"
                     style="letter-spacing:.05em;">Property No.</label>
              <input type="text" name="property_no" class="form-control" required
                     value="{{ old('property_no', $property->property_no) }}" placeholder="e.g. A-12">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted required"
                     style="letter-spacing:.05em;">Category</label>
              <select name="category" id="category" required class="form-select">
                @foreach(['residential','commercial','other'] as $cat)
                  <option value="{{ $cat }}" @selected(strtolower($property->category) === $cat)>
                    {{ ucfirst($cat) }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted required "
                     style="letter-spacing:.05em;">Type</label>
              <select name="type" id="type" class="form-select" required
                      data-old="{{ old('type', $property->type) }}">
                <option value="" disabled>Select category first…</option>
              </select>
            </div>

            {{-- Construction Status: hidden for plots via JS --}}
            <div class="col-md-6 cons-wrapper {{ $property->type === 'plot' ? 'd-none' : '' }}"
                 id="constructed_wrapper">
              <label class="form-label fw-semibold small text-uppercase text-muted required "
                     style="letter-spacing:.05em;">Construction Status</label>
              <div class="d-flex gap-3" id="">
                <label for="cons_constructed" id="label_constructed" class="const_labels">
                  <input type="radio" id="cons_constructed" name="construction_status" value="constructed"
                         @checked($property->construction_status === "constructed") hidden>

                  <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="ti tabler-check fs-4 text-success"></i>
                    <span class="small fw-semibold">Constructed</span>
                  </div>
                </label>
                <label for="cons_in_progress" id="label_progress" class="const_labels">
                  <input type="radio" id="cons_in_progress" name="construction_status" value="in_progress"
                         @checked($property->construction_status === "in_progress") hidden>
                  <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="ti tabler-clock fs-4 text-primary"></i>
                    <span class="small fw-semibold text-primary">In Progress</span>
                  </div>
                </label>
                <label for="cons_pending" id="label_pending" class="const_labels">
                  <input type="radio" id="cons_pending" name="construction_status"
                         @checked($property->construction_status === "pending")
                         value="pending" hidden>
                  <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="ti tabler-loader fs-4 text-warning"></i>
                    <span class="small fw-semibold text-primary">Pending</span>
                  </div>
                </label>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted required"
                     style="letter-spacing:.05em;">Street</label>
              <input type="text" name="street" class="form-control" required
                     value="{{ old('street', $property->street) }}" placeholder="Enter street">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.05em;">Nearest
                Landmark</label>
              <input type="text" name="landmark" class="form-control"
                     value="{{ old('landmark', $property->landmark) }}" placeholder="Imtiyaz mall etc">
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
<div class="modal fade" id="edit_dimensions_modal" tabindex="-1" aria-labelledby="editDimensionsModalLabel"
     aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="editDimensionsModalLabel">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          Edit Property Dimensions
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" action="{{ route('property.store') }}" id="edit_dimensions_form">
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
          <input type="hidden" name="section" value="construction">
          <input type="hidden" name="property_id" value="{{ $property->id }}">
          <input type="hidden" name="block_id" value="{{ $property->block_id }}">
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
                    <span
                      class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle floor_icon">
                      <i class="ti tabler-building" style="font-size:.85rem;"></i>
                    </span>
                    {{ $floor_types->firstWhere('id', $floor->floor_type)?->title }}
                  </span>
                </div>

                <div class="p-3">
                  <input type="hidden" name="{{ $fPrefix }}[id]" value="{{ $floor->id }}">

                  {{-- Floor Type select --}}
                  <div class="row g-3 mb-3">
                    <div class="col-md-4">
                      <label class="form-label fw-semibold small text-uppercase text-muted required">Floor Type</label>

                      <select name="{{ $fPrefix }}[floor_type]" class="form-select" required>
                        <option value="" disabled {{ $floor->floor_type ? '' : 'selected' }}>
                          Select Floor Type
                        </option>
                        @foreach($floor_types as $floor_type)
                          <option value="{{ $floor_type->id }}" @selected($floor->floor_type == $floor_type->id)>
                            {{ ucwords($floor_type->title) }}
                          </option>
                        @endforeach
                      </select>


                      {{--                      <select name="{{ $fPrefix }}[floor_type]" class="form-select" required>--}}
                      {{--                        <option value=""--}}
                      {{--                                disabled {{ old($fPrefix . '.floor_type', $floor->floor_type ?? '') == '' ? 'selected' : '' }}>--}}
                      {{--                          Select Floor Type--}}
                      {{--                        </option>--}}

                      {{--                        @foreach($floor_types as $floor_type)--}}
                      {{--                          <option value="{{ $floor_type->id }}">--}}
                      {{--                            {{ ucwords($floor_type->title) }}--}}
                      {{--                          </option>--}}
                      {{--                        @endforeach--}}
                      {{--                      </select>--}}
                    </div>
                  </div>

                  {{-- Units section --}}
                  @if($property->category !== 'residential')
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
                        <p class="small fw-semibold text-uppercase text-muted mb-2"
                           style="font-size:.7rem;letter-spacing:.05em;">Units</p>

                        <div class="floor-units-container">
                          @foreach($floor->units as $ui => $unit)
                            @php $uPrefix = "{$fPrefix}[units][$ui]"; @endphp

                            <div class="unit-item border rounded-3 mb-2 overflow-hidden"
                                 data-unit-id="{{ $unit->id }}">

                              <div class="align-items-center px-3 py-2 border-bottom ">
                                <span class="d-flex align-items-center gap-2 fw-semibold text-success card_title">
                                  <span
                                    class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle title_icon">
                                    <i class="ti tabler-home" style="font-size:.78rem;"></i>
                                  </span>
                                  Unit — {{ $unit->unit_name }}
                                  @if($unit->unit_type)
                                    <span class="badge bg-label-secondary align-items-center ms-1 w-auto"
                                          style="font-size:.6rem;">{{ ucwords($unit_types->firstWhere('id', $unit->unit_type)?->title)  }}</span>
                                  @endif
                                </span>
                              </div>

                              <div class="p-3">
                                <input type="hidden" name="{{ $uPrefix }}[id]" value="{{ $unit->id }}">
                                <div class="row g-3 mb-3">
                                  <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-uppercase text-muted required">Unit
                                      Name</label>
                                    <input type="text" name="{{ $uPrefix }}[unit_name]"
                                           value="{{ $unit->unit_name }}"
                                           class="form-control" placeholder="e.g. Suite A" required>
                                  </div>
                                  <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-uppercase text-muted required">Unit
                                      Type</label>
                                    <select name="{{ $uPrefix }}[unit_type]" class="form-select" required>

                                      <option value=""
                                              disabled
                                        {{ old($uPrefix . '.unit_type', $unit->unit_type ?? '') == '' ? 'selected' : '' }}>
                                        Select Type
                                      </option>

                                      @foreach($unit_types as $unit_type)
                                        <option value="{{ $unit_type->id }}"
                                          @selected(old($uPrefix . '.unit_type', $unit->unit_type ?? '') == $unit_type->id)>
                                          {{ ucwords($unit_type->title) }}
                                        </option>
                                      @endforeach
                                    </select>
                                  </div>
                                </div>


                                @php
                                  $isSingleRoom = $unit->rooms->isEmpty() && $unit->dimensions->isNotEmpty();
                                @endphp

                                <div class="form-check mt-2 mb-3">
                                  <input class="form-check-input no_rooms_check" type="checkbox"
                                         name="{{ $uPrefix }}[no_rooms]" value="1" disabled
                                    {{ $isSingleRoom ? 'checked' : '' }}>
                                  <label class="form-check-label fw-semibold">
                                    This unit consists of a single room only and has no further room divisions.
                                  </label>
                                </div>

                                {{-- Amenities + Dimensions (visible when single room) --}}
                                <div class="unit-amenities-section mt-2 {{ $isSingleRoom ? '' : 'd-none' }}">
                                  <label
                                    class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
                                  @php $selectedAmenities = json_decode($unit->amenities ?? '[]', true); @endphp
                                  <div class="d-flex flex-wrap gap-3 mb-3">
                                    @foreach($amenities as $amenity)
                                      <label
                                        class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer">
                                        <input class="form-check-input mt-0" type="checkbox"
                                               name="{{ $uPrefix }}[amenities][]" value="{{ $amenity->id }}"
                                          @checked(in_array($amenity->id, $selectedAmenities))>
                                        <span class="small">{{ ucwords($amenity->title) }}</span>
                                      </label>
                                    @endforeach
                                  </div>
                                </div>

                                <div class="unit-dimension-section {{ $isSingleRoom ? '' : 'd-none' }}">
                                  <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Unit
                                    Dimensions</label>
                                  <div class="mb-3 dim-block" data-prefix="{{ $uPrefix }}">
                                    <div class="dim-rows">
                                      @foreach($unit->dimensions as $di => $dim)
                                        @php $converted = app()->make(\App\Http\Controllers\PropertiesController::class)->convertForUser($dim->size, $dim->unit); @endphp
                                        <div class="dimension-row row g-3 mb-2 align-items-end rounded-3 p-2 mx-0">
                                          <input type="hidden" name="{{ $uPrefix }}[dimensions][{{ $di }}][id]"
                                                 value="{{ $dim->id }}">
                                          <div class="col-md-4">
                                            <label
                                              class="form-label fw-semibold small text-uppercase text-muted required">Side
                                              Name</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-end-0"><i
                                                    class="ti tabler-ruler text-muted"></i></span>
                                              <input type="text" name="{{ $uPrefix }}[dimensions][{{ $di }}][name]"
                                                     value="{{ $dim->name }}" required
                                                     class="form-control border-start-0"
                                                     placeholder="e.g. Length, Width">
                                            </div>
                                          </div>
                                          <div class="col-md-4">
                                            <label
                                              class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-end-0"><i
                                                    class="ti tabler-number text-muted"></i></span>
                                              <input type="number" name="{{ $uPrefix }}[dimensions][{{ $di }}][size]"
                                                     value="{{ round($converted, 2) }}" step="0.01" required
                                                     class="form-control border-start-0" placeholder="e.g 40">
                                            </div>
                                          </div>
                                          <div class="col-md-3">
                                            <label
                                              class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
                                            <select name="{{ $uPrefix }}[dimensions][{{ $di }}][unit]"
                                                    class="form-select" required>
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
                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim"
                                            style="font-size:.72rem;">
                                      <i class="ti tabler-plus me-1"></i> Add Dimension
                                    </button>
                                  </div>
                                </div>

                                {{-- Rooms (visible when not single room) --}}
                                <div class="unit-rooms-section mt-3 {{ $isSingleRoom ? 'd-none' : '' }}">
                                  <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms in
                                    this unit</p>
                                  <div class="unit-rooms-container">
                                    @foreach($unit->rooms as $ri => $room)
                                      @php $rPrefix = "{$uPrefix}[rooms][$ri]"; @endphp
                                      @include('components.property.edit_room_card', ['rPrefix' => $rPrefix, 'room' => $room])
                                    @endforeach
                                  </div>
                                  <button type="button"
                                          class="btn btn-sm btn-add-unit-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                                          data-room-count="{{ $unit->rooms->count() }}">
                                    <i class="ti tabler-plus me-1"></i> Add Room
                                  </button>
                                </div>


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
                  @if(!$floor->units->count() )
                    <div class="floor-rooms-section mt-3">
                      <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms on this floor</p>
                      <div class="floor-rooms-container">
                        @foreach($floor->rooms as $ri => $room)
                          @php $rPrefix = "{$fPrefix}[rooms][$ri]"; @endphp
                          @include('components.property.edit_room_card', [
                            'rPrefix'   => $rPrefix,
                            'room'      => $room,
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
                  @endif

                </div>
              </div>
            @endforeach

          </div>
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

@php unset($rPrefix, $uPrefix, $fPrefix, $room, $unit, $floor, $fi, $ui, $ri, $di, $dim, $p); @endphp
@include('components.property.templates.dimension')
@include('components.property.templates.room')
@include('components.property.templates.unit')
@include('components.property.templates.floor')


