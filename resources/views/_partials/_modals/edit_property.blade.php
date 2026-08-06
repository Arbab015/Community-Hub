{{-- MODAL: Edit Basic Information --}}
<div class="modal fade" id="edit_basic_property" tabindex="-1" aria-labelledby="editBasicInfoModalLabel"
     aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow p-4">

      <div class="modal-header px-0 border-0 pb-0">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="editBasicInfoModalLabel">
          <span class="d-inline-block bg-primary rounded hr_bar"></span>
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
        <div class="modal-body px-0 pt-3 basic_info_modal">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold small text-uppercase text-muted">Property
                Name</label>
              <input type="text" name="name" class="form-control"
                     value="{{ old('name', $property->name) }}"
                     placeholder="e.g. Plot A-12, Imtiyaz mart, Faisal bank...">
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label fw-semibold small text-uppercase text-muted required">Property No.</label>
              <input type="text" name="property_no" class="form-control" required
                     value="{{ old('property_no', $property->property_no) }}" placeholder="e.g. A-12">
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label fw-semibold small text-uppercase text-muted required">Category</label>
              <select name="category" id="category" required class="form-select">
                @foreach(['residential','commercial','other'] as $cat)
                  <option value="{{ $cat }}" @selected(strtolower($property->category) === $cat)>
                    {{ ucfirst($cat) }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label fw-semibold small text-uppercase text-muted required">Type</label>
              <select name="type" id="type" class="form-select" required
                      data-old="{{ old('type', $property->type) }}">
                <option value="" disabled>Select category first…</option>
              </select>
            </div>

            {{-- Construction Status --}}
            <div class="col-12 col-lg-6 cons-wrapper {{ $property->type === 'plot' ? 'd-none' : '' }}"
                 id="constructed_wrapper">
              <label class="form-label fw-semibold small text-uppercase text-muted required"
                     style="letter-spacing:.05em;">Construction Status</label>
              <div class="d-flex flex-wrap gap-2">
                <label for="cons_constructed" id="label_constructed" class="const_labels flex-shrink-0">
                  <input type="radio" id="cons_constructed" name="construction_status" value="constructed"
                         @checked($property->construction_status === "constructed") hidden>
                  <div class="const-inner d-flex align-items-center justify-content-center">
                    <i class="ti tabler-check const-icon text-success"></i>
                    <span class="fw-semibold const-text">Constructed</span>
                  </div>
                </label>
                <label for="cons_in_progress" id="label_progress" class="const_labels flex-shrink-0">
                  <input type="radio" id="cons_in_progress" name="construction_status" value="in_progress"
                         @checked($property->construction_status === "in_progress") hidden>
                  <div class="const-inner d-flex align-items-center justify-content-center">
                    <i class="ti tabler-clock const-icon text-primary"></i>
                    <span class="fw-semibold text-primary const-text">In Progress</span>
                  </div>
                </label>
                <label for="cons_pending" id="label_pending" class="const_labels flex-shrink-0">
                  <input type="radio" id="cons_pending" name="construction_status"
                         @checked($property->construction_status === "pending") value="pending" hidden>
                  <div class="const-inner d-flex align-items-center justify-content-center">
                    <i class="ti tabler-loader const-icon text-warning"></i>
                    <span class="fw-semibold text-primary const-text">Pending</span>
                  </div>
                </label>
              </div>
            </div>


            <div class="col-12 col-lg-6">
              <label class="form-label fw-semibold small text-uppercase text-muted">Nearest
                Landmark</label>
              <input type="text" name="landmark" class="form-control"
                     value="{{ old('landmark', $property->landmark) }}" placeholder="Imtiyaz mall etc">
            </div>

            <div class="col-12 col-lg-6">
              <label class="form-label fw-semibold small text-uppercase text-muted required"
                     style="letter-spacing:.05em;">Street</label>
              <input type="text" name="street" class="form-control" required
                     value="{{ old('street', $property->street) }}" placeholder="Enter street">
            </div>


            <div class="col-12 border-top pt-3 mt-3">
              <label class="form-label fw-semibold small text-uppercase text-muted mb-3">Property Features</label>
              <div class="d-flex flex-wrap gap-2">

                <label
                  class="feature-chip feature-chip-corner {{ old('is_corner', $property->is_corner) ? 'active' : '' }}">
                  <input type="checkbox" class="feature-chip-input" id="modal_is_corner" name="is_corner" value="1"
                         hidden
                    {{ old('is_corner', $property->is_corner) ? 'checked' : '' }}>
                  <span class="feature-chip-icon"><i class="ti tabler-corner-down-right-double"></i></span>
                  <span class="feature-chip-text">Corner Property</span>
                  <i class="ti tabler-circle-check-filled feature-chip-check"></i>
                </label>

                <label
                  class="feature-chip feature-chip-boulevard {{ old('is_main_boulevard_facing', $property->is_main_boulevard_facing) ? 'active' : '' }}">
                  <input type="checkbox" class="feature-chip-input" id="modal_is_main_boulevard_facing"
                         name="is_main_boulevard_facing" value="1" hidden
                    {{ old('is_main_boulevard_facing', $property->is_main_boulevard_facing) ? 'checked' : '' }}>
                  <span class="feature-chip-icon"><i class="ti tabler-road-2"></i></span>
                  <span class="feature-chip-text">Main Boulevard Facing</span>
                  <i class="ti tabler-circle-check-filled feature-chip-check"></i>
                </label>

                <label
                  class="feature-chip feature-chip-park {{ old('is_park_facing', $property->is_park_facing) ? 'active' : '' }}">
                  <input type="checkbox" class="feature-chip-input" id="modal_is_park_facing" name="is_park_facing"
                         value="1" hidden
                    {{ old('is_park_facing', $property->is_park_facing) ? 'checked' : '' }}>
                  <span class="feature-chip-icon"><i class="ti tabler-trees"></i></span>
                  <span class="feature-chip-text">Park Facing</span>
                  <i class="ti tabler-circle-check-filled feature-chip-check"></i>
                </label>

              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer border-0 p-0 mt-2 d-flex flex-column gap-2 ">
          <button type="button" class="btn btn-outline-secondary w-100 me-0" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4 w-100 ms-0">
            <i class="fa-solid fa-floppy-disk me-2"></i> Save Changes
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
        <div class="modal-body pt-3 " id="dimension_modal_body">
          @include('components.property.dimensions')
        </div>

        <div class="modal-footer border-0 d-flex flex-column pt-0 gap-1">
          <button type="button" class="btn btn-outline-secondary w-100 me-0" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4 w-100 ms-0">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

{{-- MODAL: Edit Floor --}}
<div class="modal fade" id="edit_floor_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom px-4 py-3">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          Edit Floor
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('property.store') }}" id="edit_floor_form">
        @csrf
        <input type="hidden" name="section" value="construction">
        <input type="hidden" name="property_id" value="{{ $property->id }}">
        <input type="hidden" name="block_id" value="{{ $property->block_id }}">
        <input type="hidden" name="request_type" value="property_const">
        <div class="modal-body px-4 py-3" id="edit_floor_body">
          {{-- injected per floor --}}
        </div>
        <div class="modal-footer border-top px-4 py-3">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save Floor
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- MODAL: Add Room / Edit Room --}}
@include('_partials._modals.room')

{{-- MODAL: Add Unit / Edit Unit --}}
<div class="modal fade" id="unit_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom px-4 py-3">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="unit_modal_title">
          <span class="d-inline-block bg-primary rounded hr_bar"></span>
          Add Unit
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('property.store') }}" id="unit_form">
        @csrf
        <input type="hidden" name="section" value="unit">
        <input type="hidden" name="unit_id" id="unit_modal_unit_id" value="">
        <input type="hidden" name="floor_id" id="unit_modal_floor_id" value="">

        <div class="modal-body px-4 py-3 overflow-y-auto" id="unit_modal_body" style="max-height: 70vh;">
          {{-- injected --}}
        </div>

        <div class="modal-footer border-top px-4 py-3">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save Unit
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


{{-- MODAL: Add New Floor --}}
<div class="modal fade" id="add_floor_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">

      <div class="modal-header border-bottom px-4 py-3">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-1 flex-wrap">
          <span class="d-inline-block bg-primary rounded flex-shrink-0 hr_bar"></span>
          Add New Floor —
          <span class="text-muted badge bg-label-info ms-1">{{ $property->property_no }}</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body px-4 py-3">
        <form method="POST" action="{{ route('property.store') }}" id="add_floor_form">
          @csrf
          <input type="hidden" name="section" value="construction">
          <input type="hidden" name="property_id" value="{{ $property->id }}">
          <input type="hidden" name="block_id" value="{{ $property->block_id }}">
          <input type="hidden" name="request_type" value="property_const">
          <div id="add-floor-container"></div>
        </form>
      </div>

      <div class="modal-footer border-top px-4 py-3">
        <button type="submit" form="add_floor_form" class="btn btn-sm btn-primary px-4 w-100 fw-bold">
          <i class="fa-solid fa-floppy-disk me-1"></i> Save Floor
        </button>
      </div>

    </div>
  </div>
</div>


<div class="modal fade" id="clone_unit_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom px-4 py-3">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          Clone Unit
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body px-4 py-3">
        <input type="hidden" id="clone_unit_id" value="">

        <div class="mb-3">
          <label class="form-label fw-semibold small text-uppercase text-muted">Unit Name Prefix</label>
          <input type="text" id="clone_prefix" class="form-control" placeholder="e.g. apart">
          <div class="form-text">Unit names will be: prefix-start → prefix-end</div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label fw-semibold small text-uppercase text-muted">Start No.</label>
            <input type="number" id="clone_start" class="form-control" min="1" placeholder="e.g. 1">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small text-uppercase text-muted">End No.</label>
            <input type="number" id="clone_end" class="form-control" min="1" placeholder="e.g. 5">
          </div>
        </div>

        <!-- Preview -->
        <div id="clone_preview" class="d-none">
          <p class="small fw-semibold text-muted text-uppercase mb-1">Preview (<span id="clone_count">0</span> units)
          </p>
          <div id="clone_preview_names" class="d-flex flex-wrap gap-1"></div>
        </div>

        <div id="clone_error" class="alert alert-danger d-none mt-2 mb-0 py-2 small"></div>
      </div>

      <div class="modal-footer border-top px-4 py-3">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary fw-bold" id="clone_submit_btn">
          <span class="spinner-border spinner-border-sm me-1 d-none" id="clone_spinner"></span>
          Clone Units
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


