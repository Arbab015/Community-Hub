@extends('layouts/layoutMaster')

@section('title', 'Add Property')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/tagify/tagify.scss',
    'resources/assets/vendor/libs/dropzone/dropzone.scss',
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/jquery-repeater/jquery-repeater.js',
    'resources/assets/vendor/libs/tagify/tagify.js',
    'resources/assets/vendor/libs/dropzone/dropzone.js',
  ])
@endsection

@section('page-script')
  @vite(['resources/assets/js/app-ecommerce-product-add.js', 'resources/assets/js/forms-file-upload.js'])
@endsection

@section('content')

  {{-- ── PAGE HEADER ── --}}
  <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Property Management</p>
      <h3 class="text-dark fw-bold mb-2">Add New Property</h3>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}" class="text-dark opacity-75 text-decoration-none">Home</a>
          </li>
          <li class="breadcrumb-item">
            <a href="{{ route('blocks.index') }}" class="text-dark opacity-75 text-decoration-none">Blocks</a>
          </li>
          <li class="breadcrumb-item">
            <a href="{{ route('blocks.view', $block->uuid) }}" class="text-dark opacity-75 text-decoration-none">Block Details</a>
          </li>
          <li class="breadcrumb-item active text-dark opacity-50">Add Property</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-building-estate text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  {{-- ── ALERTS ── --}}
  @if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
      <i class="ti tabler-circle-check-filled"></i> {{ session('success') }}
    </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
      <i class="ti tabler-alert-circle-filled"></i> {{ session('error') }}
    </div>
  @endif

  <div class="alert alert-primary alert-dismissible d-flex gap-3 mb-4" role="alert">
    <i class="ti tabler-info-circle-filled fs-4 flex-shrink-0 mt-1"></i>
    <div>
      <strong>Before you begin — please note:</strong>
      <ul class="mb-0 mt-1 small">
        <li>Select a <strong>Property Category</strong> first; the type list updates automatically.</li>
        <li>Provide complete <strong>dimensions for all sides</strong> (length, width, right, front).</li>
        <li>If construction is complete, mark <strong>"Construction Completed"</strong> to unlock extra fields.</li>
        <li>Ensure all required fields are filled to avoid incomplete records.</li>
      </ul>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>

  <div class="col-12">
    <ul class="nav nav-pills mb-4" role="tablist">
      <li class="nav-item">
        <a class="nav-link {{ request('tab', 'property') === 'property' ? 'active' : '' }}"
           type="button">
          Property Basic
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request('tab') === 'documents' ? 'active' : '' }}"
            type="button">
          Documents & Media
        </a>
      </li>
      <li class="nav-item {{$property?->is_constructed ? "d-block" : "d-none"}}" id="const_tab">
        <a class="nav-link {{ request('tab') === 'construction' ? 'active' : '' }}"
           type="button">
          Construction Details
        </a>
      </li>
    </ul>
  </div>

  <div class="card border-0 shadow-sm rounded-3">
    <div class="tab-content p-0">

      {{-- PROPERTY BASIC TAB --}}
      <div class="tab-pane fade {{ request('tab', 'property') === 'property' ? 'show active' : '' }}" id="property">
        <form id="property_form" method="post" action="{{ route('property.store') }}">
          @csrf

          @if(isset($property))
            <input type="hidden" name="property_id" value="{{ $property->id }}">
          @endif

          <input type="hidden" name="block_id" value="{{ $block->id }}">
          <input type="hidden" name="section" value="property">

          {{-- STEP 01 — CATEGORY --}}
          <div class="card-body border-bottom p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
              <span class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">01</span>
              <h5 class="mb-0 fw-bold">Property Category</h5>
            </div>

            <div class="row g-3" id="categoryGrid">
              <div class="col-md-4">
                <label for="cat_residential" class="category-card d-block border rounded-3 text-center p-4 h-100 user-select-none" style="cursor:pointer;">
                  <input type="radio" id="cat_residential" name="category" value="residential"
                         {{ old('category', $property->category ?? '') == 'residential' ? 'checked' : '' }} hidden>
                  <div class="bg-success bg-opacity-10 rounded-3 d-inline-flex align-items-center justify-content-center mb-3 category_pic">
                    <i class="ti tabler-home-2 fs-3 text-success"></i>
                  </div>
                  <h6 class="fw-bold mb-1">Residential</h6>
                  <p class="text-muted small mb-3">Plots, Houses & more</p>
                  <span class="radio-dot d-inline-block border border-2 border-secondary rounded-circle radio_circle"></span>
                </label>
              </div>

              <div class="col-md-4">
                <label for="cat_commercial" class="category-card d-block border rounded-3 text-center p-4 h-100 user-select-none" style="cursor:pointer;">
                  <input type="radio" id="cat_commercial" name="category" value="commercial"
                         {{ old('category', $property->category ?? '') == 'commercial' ? 'checked' : '' }} hidden>
                  <div class="bg-primary bg-opacity-10 rounded-3 d-inline-flex align-items-center justify-content-center mb-3 category_pic">
                    <i class="ti tabler-building-skyscraper fs-3 text-primary"></i>
                  </div>
                  <h6 class="fw-bold mb-1">Commercial</h6>
                  <p class="text-muted small mb-3">Buildings, Plazas & more</p>
                  <span class="radio-dot d-inline-block border border-2 border-secondary rounded-circle radio_circle"></span>
                </label>
              </div>

              <div class="col-md-4">
                <label for="cat_other" class="category-card d-block border rounded-3 text-center p-4 h-100 user-select-none" style="cursor:pointer;">
                  <input type="radio" id="cat_other" name="category" value="other"
                         {{ old('category', $property->category ?? '') == 'other' ? 'checked' : '' }} hidden>
                  <div class="bg-warning bg-opacity-10 rounded-3 d-inline-flex align-items-center justify-content-center mb-3 category_pic">
                    <i class="ti tabler-map-2 fs-3 text-warning"></i>
                  </div>
                  <h6 class="fw-bold mb-1">Other</h6>
                  <p class="text-muted small mb-3">Mosque, Park, Hospital & more</p>
                  <span class="radio-dot d-inline-block border border-2 border-secondary rounded-circle radio_circle"></span>
                </label>
              </div>
            </div>

            <div id="category_error" class="text-danger small mt-2 d-none">
              <i class="ti tabler-alert-triangle me-1"></i>Please select a property category.
            </div>
          </div>

          {{-- STEP 02 — PROPERTY DETAILS --}}
          <div class="card-body border-bottom p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
              <span class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">02</span>
              <h5 class="mb-0 fw-bold">Property Details</h5>
            </div>

            <div class="row g-4">
              <div class="col-md-4">
                <label for="name" class="form-label fw-semibold small text-uppercase text-muted">Property Name</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="ti tabler-tag text-muted"></i></span>
                  <input type="text" id="name" name="name" value="{{ old('name', $property->name ?? '') }}"
                         class="form-control border-start-0 @error('name') is-invalid @enderror"
                         placeholder="e.g. Gillani Market">
                </div>
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-4">
                <label for="property_no" class="form-label fw-semibold small text-uppercase text-muted required">Property No</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="ti tabler-hash text-muted"></i></span>
                  <input type="text" id="property_no" name="property_no" value="{{ old('property_no', $property->property_no ?? '') }}"
                         class="form-control border-start-0 @error('property_no') is-invalid @enderror"
                         required placeholder="e.g. A-101">
                </div>
                @error('property_no')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-4">
                <label for="type" class="form-label fw-semibold small text-uppercase text-muted required">Type</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="ti tabler-list text-muted"></i></span>
                  <select id="type" name="type"
                          class="form-select border-start-0 @error('type') is-invalid @enderror"
                          required data-old="{{ old('type', $property->type ?? '') }}">
                    <option value="" selected disabled>Select category first…</option>
                  </select>
                </div>
                @error('type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-8">
                <label for="address" class="form-label fw-semibold small text-uppercase text-muted required">Address</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="ti tabler-map-pin text-muted"></i></span>
                  <input type="text" id="address" name="address" value="{{ old('address', $property->address ?? '') }}"
                         class="form-control border-start-0 @error('address') is-invalid @enderror"
                         required placeholder="Street, Block, City">
                </div>
                @error('address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-4 @if(!isset($property) || $property->is_constructed == false) d-none @endif" id="constructed_wrapper">
                <label class="form-label fw-semibold small text-uppercase text-muted required">Construction Status</label>
                <div class="d-flex gap-3">
                  <label for="cons_yes" id="label_cons_yes" class="flex-fill border rounded-3 text-center p-3 user-select-none cursor-pointer">
                    <input type="radio" id="cons_yes" name="is_constructed"
                           {{ old('is_constructed', $property->is_constructed ?? '') == "1" ? 'checked' : '' }} value="1" hidden>
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <i class="ti tabler-check fs-4 text-success"></i>
                      <span class="small fw-semibold">Completed</span>
                    </div>
                  </label>
                  <label for="cons_no" id="label_cons_no" class="flex-fill border border-primary rounded-3 text-center p-3 bg-primary bg-opacity-10 user-select-none cursor-pointer">
                    <input type="radio" id="cons_no" name="is_constructed"
                           {{ old('is_constructed', $property->is_constructed ?? '') == "0" ? 'checked' : '' }} value="0" hidden>
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <i class="ti tabler-clock fs-4 text-primary"></i>
                      <span class="small fw-semibold text-primary">In Progress</span>
                    </div>
                  </label>
                </div>
              </div>
            </div>
          </div>

          {{-- STEP 03 — DIMENSIONS --}}
          @include('components.property.dimensions')

          <div class="card-body p-4 float-end">
            <button type="submit" id="form-submit-btn" class="btn btn-primary px-4 fw-bold">
              <i class="ti tabler-arrow-right me-1"></i>Save & Continue
            </button>
          </div>
        </form>
      </div>

      {{-- DOCUMENTS & MEDIA TAB --}}
      <div class="tab-pane fade {{ request('tab') === 'documents' ? 'show active' : '' }}" id="doc_&_media">
        <form method="post" action="{{ route('property.store') }}" enctype="multipart/form-data" id="upload_media_property">
          @csrf
          <input type="hidden" name="block_id" value="{{ $block->id }}">
          <input type="hidden" name="section" value="documents">
          <input type="hidden" name="property_id" value="{{ $property->id ?? '' }}">

          <div class="card-body border-bottom p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
              <span class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">04</span>
              <h5 class="mb-0 fw-bold">Documents & Media</h5>
            </div>

            <div class="row g-4">
              <div class="col-md-12">
                <label class="form-label fw-semibold small text-uppercase text-muted">Main Picture</label>
                <div class="dropzone needsclick dz-clickable @error('main_pic') is-invalid @enderror" id="dropzone-basic">
                  <div class="dz-message needsclick">
                    <i class="bx bx-upload" style="font-size: 3rem; color: #999;"></i>
                    <h6 class="m-0">Drop files here or click to upload</h6>
                    <span class="note needsclick text-muted d-block mt-0">(Upload main picture for the property)</span>
                  </div>
                </div>
                @error('main_pic')<div class="text-danger mt-2">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-12">
                <label class="form-label fw-semibold small text-uppercase text-muted">Related Documents</label>
                <div class="dropzone needsclick dz-clickable dropzone_multi @error('documents') is-invalid @enderror" id="dropzone-multi">
                  <div class="dz-message needsclick">
                    <i class="bx bx-upload p-0" style="font-size: 3rem; color: #999;"></i>
                    <h6 class="m-0 pb-1">Drop files here or click to upload</h6>
                    <span class="note needsclick text-muted d-block mt-0">(Upload multiple documents related to the property)</span>
                  </div>
                </div>
                @error('documents')<div class="text-danger mt-2">{{ $message }}</div>@enderror
              </div>
            </div>
          </div>

          <div class="card-body p-4 d-flex justify-content-between">
            <a href="{{ route('property.create', ['block' => $block->id, 'uuid' => $property->uuid ?? '', 'tab' => 'property']) }}"
               class="btn btn-outline-secondary px-4">
              <i class="ti tabler-arrow-left me-1"></i> Previous
            </a>
            <button type="submit" class="btn btn-primary px-4 fw-bold">
              <i class="ti @if($property?->is_constructed) tabler-arrow-right @else tabler-device-floppy @endif me-1" id="btn_icon"></i>
              <span id="save_btn">{{ $property?->is_constructed ? 'Save & Continue' : 'Save Documents' }}</span>
            </button>
          </div>
        </form>
      </div>

      {{--  CONSTRUCTION DETAILS TAB  --}}
      <div class="tab-pane fade {{ request('tab') === 'construction' ? 'show active' : '' }}" id="const_details">
        <form method="post" action="{{ route('property.store') }}" id="construction_form">
          @csrf
          <input type="hidden" name="block_id" value="{{ $block->id }}">
          <input type="hidden" name="section" value="construction">
          <input type="hidden" name="property_id" value="{{ $property->id ?? '' }}">

          <div class="card-body border-bottom p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
              <span class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">05</span>
              <h5 class="mb-0 fw-bold">Floors with Details</h5>
            </div>

            <div id="floors-container"></div>
            <button type="button" id="btn-add-floor"
                    class="btn btn-sm d-inline-flex align-items-center gap-1 mt-2 rounded-pill px-3 py-2 fw-semibold"
                    style="font-size:.78rem; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border:none; box-shadow:0 2px 8px rgba(102,126,234,.35);">
              <i class="ti tabler-plus me-1"></i> Add Floor
            </button>
          </div>

          <div class="card-body p-4 d-flex justify-content-between">
            <a href="{{ route('property.create', ['block' => $block->id, 'uuid' => $property->uuid ?? '', 'tab' => 'documents']) }}"
               class="btn btn-outline-secondary px-4">
              <i class="ti tabler-arrow-left me-1"></i> Previous
            </a>
            <button type="submit" class="btn btn-primary px-4 fw-bold">
              <i class="ti tabler-device-floppy me-1"></i> Save Construction
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  {{-- TEMPLATES — rendered by Blade, cloned by JS
       Hidden from view; JS clones these and re-indexes names --}}

  {{-- TEMPLATE: single dimension row (inline style) --}}
  <template id="tpl-dim-row">
    <div class="dimension-row row g-3 mb-2 align-items-end rounded-3 p-2 mx-0">
      <div class="col-md-4">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="ti tabler-ruler text-muted"></i></span>
          <input type="text" name="__PREFIX__[dimensions][__DIM__][name]"
                 class="form-control border-start-0" placeholder="e.g. Length, Width, Front, Right">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="ti tabler-number text-muted"></i></span>
          <input type="number" name="__PREFIX__[dimensions][__DIM__][size]" step="0.01"
                 class="form-control border-start-0" placeholder="e.g. 40, 12.21">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
        <select name="__PREFIX__[dimensions][__DIM__][unit]" class="form-select">
          <option value="" disabled selected>Select Unit</option>
          <option value="feet">Feet</option>
          <option value="square_feet">Square Feet</option>
          <option value="meter">Meter</option>
          <option value="yard">Yard</option>
          <option value="marla">Marla</option>
          <option value="kanal">Kanal</option>
        </select>
      </div>
      <div class="col-md-1">
        <button type="button" class="btn btn-remove-dim" title="Remove">
          <i class="ti tabler-x icon-lg text-danger"></i>
        </button>
      </div>
    </div>
  </template>

  {{-- TEMPLATE: room card --}}
  <template id="tpl-room">
    <div class="room-item border rounded-3 mb-2 overflow-hidden">
      <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom room_header">
        <span class="d-flex align-items-center gap-2 fw-semibold text-primary card_title">
          <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle card_title title_icon">
            <i class="ti tabler-door" ></i>
          </span>
          Room
        </span>
        <button type="button" class="btn btn-sm btn-remove-room d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_item">
          <i class="ti tabler-trash" style="font-size:.75rem;"></i> Remove
        </button>
      </div>
      <div class="p-3">
        <div class="row g-3 mb-3">
          <div class="col-md-5">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Room Type</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-door text-muted"></i></span>
              <select name="__PREFIX__[rooms][__ROOM__][room_type]" class="form-select border-start-0" required>
                <option value="" disabled selected>Select type</option>
                <option value="bedroom">Bedroom</option>
                <option value="guest_room">Guest Room</option>
                <option value="drawing_room">Drawing Room</option>
                <option value="dining_room">Dining Room</option>
                <option value="kitchen">Kitchen</option>
                <option value="bathroom">Bathroom</option>
                <option value="washroom">Washroom</option>
                <option value="store_room">Store Room</option>
                <option value="servant_quarter">Servant Quarter</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>
        </div>
        <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
        <div class="d-flex flex-wrap gap-3 mb-3">
          <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none" style="cursor:pointer;">
            <input class="form-check-input mt-0" type="checkbox" name="__PREFIX__[rooms][__ROOM__][has_attached_bathroom]" value="1">
            <i class="ti tabler-bath text-muted"></i><span class="small">Attached Bathroom</span>
          </label>
          <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none" style="cursor:pointer;">
            <input class="form-check-input mt-0" type="checkbox" name="__PREFIX__[rooms][__ROOM__][has_attached_ac]" value="1">
            <i class="ti tabler-air-conditioning text-muted"></i><span class="small">AC</span>
          </label>
          <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none" style="cursor:pointer;">
            <input class="form-check-input mt-0" type="checkbox" name="__PREFIX__[rooms][__ROOM__][has_attached_balcony]" value="1">
            <i class="ti tabler-building-arch text-muted"></i><span class="small">Balcony</span>
          </label>
          <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none" style="cursor:pointer;">
            <input class="form-check-input mt-0" type="checkbox" name="__PREFIX__[rooms][__ROOM__][has_attached_wardrobe]" value="1">
            <i class="ti tabler-hanger text-muted"></i><span class="small">Wardrobe</span>
          </label>
        </div>
        <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Room Dimensions</label>
        {{-- dim-block injected by JS using tpl-dim-row --}}
        <div class="dim-block">
          <div class="dim-rows"></div>
          <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">
            <i class="ti tabler-plus me-1"></i> Add Dimension
          </button>
        </div>
      </div>
    </div>
  </template>

  {{-- TEMPLATE: unit card (commercial) --}}
  <template id="tpl-unit">
    <div class="unit-item border rounded-3 mb-2 overflow-hidden">
      <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom unit_header">
        <span class="d-flex align-items-center gap-2 fw-semibold text-success card_title">
          <span class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle title_icon">
            <i class="ti tabler-home" style="font-size:.78rem;"></i>
          </span>
          Unit
        </span>
        <button type="button" class="btn btn-sm btn-remove-unit d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_item"
                >
          <i class="ti tabler-trash" style="font-size:.75rem;"></i> Remove
        </button>
      </div>
      <div class="p-3">
        <div class="row g-3 mb-3">
          <div class="col-md-2">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Unit No</label>
            <input type="number" name="__PREFIX__[units][__UNIT__][unit_no]" class="form-control" placeholder="101">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Name</label>
            <input type="text" name="__PREFIX__[units][__UNIT__][unit_name]" class="form-control" placeholder="e.g. Suite A">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Type</label>
            <select name="__PREFIX__[units][__UNIT__][unit_type]" class="form-select">
              <option value="" disabled selected>Select type</option>
              <option value="apartment">Apartment</option>
              <option value="office">Office</option>
              <option value="shop">Shop</option>
              <option value="studio">Studio</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>
        <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title" >Rooms in this unit</p>
        <div class="unit-rooms-container"></div>
        <button type="button" class="btn btn-sm btn-add-unit-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                data-room-count="0">
          <i class="ti tabler-plus me-1"></i> Add Room
        </button>
      </div>
    </div>
  </template>

  {{-- TEMPLATE: floor card --}}
  <template id="tpl-floor">
    <div class="floor-item border rounded-3 mb-3 overflow-hidden shadow-sm">
      <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom floor_header"
           >
        <span class="d-flex align-items-center gap-2 fw-bold text-primary floor_title"
              >
          <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle floor_icon"
                >
            <i class="ti tabler-building" style="font-size:.85rem;"></i>
          </span>
          Floor
        </span>
        <button type="button" class="btn btn-sm btn-remove-floor d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_floor">
          <i class="ti tabler-trash" style="font-size:.8rem;"></i> Remove Floor
        </button>
      </div>
      <div class="p-3">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Floor Type</label>
            <select name="__PREFIX__[floor_type]" class="form-select @error('__PREFIX__[floor_type]') is-invalid @enderror" required>
              <option value="" disabled selected>Select Floor Type</option>
              <option value="basement">Basement</option>
              <option value="ground">Ground Floor</option>
              <option value="first floor">1st Floor</option>
              <option value="second floor">2nd Floor</option>
              <option value="third floor">3rd Floor</option>
              <option value="fourth floor">4th Floor</option>
              <option value="fifth floor">5th Floor</option>
              <option value="top floor">Top Floor</option>
            </select>
            @error('__PREFIX__[floor_type]')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>
        </div>
        <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Floor Dimensions</label>
        <div class="mb-3 dim-block">
          <div class="dim-rows"></div>
          <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">
            <i class="ti tabler-plus me-1"></i> Add Dimension
          </button>
        </div>
        {{-- units-section: shown only for commercial, toggled by has-units-check --}}
        <div class="units-section-wrapper d-none">
          <div class="form-check mt-3 mb-2">
            <input class="form-check-input has-units-check" id="has_units" type="checkbox" name="__PREFIX__[has_units]" value="1">
            <label class="form-check-label fw-semibold" for="has_units">
              This floor has units (apartments, offices, shops, etc.)
            </label>
          </div>
          <div class="units-section d-none mt-2">
            <p class="small fw-semibold text-uppercase text-muted mb-2" style="font-size:.7rem;letter-spacing:.05em;">Units</p>
            <div class="floor-units-container"></div>
            <button type="button" class="btn btn-sm btn-add-unit d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_unit_btn"
                    data-unit-count="0">
              <i class="ti tabler-plus me-1"></i> Add Unit
            </button>
          </div>
        </div>
        <div class="floor-rooms-section mt-3">
          <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title" >Rooms on this floor</p>
          <div class="floor-rooms-container"></div>
          <button type="button" class="btn btn-sm btn-add-floor-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                  data-room-count="0"
                  >
            <i class="ti tabler-plus me-1"></i> Add Room
          </button>
        </div>
      </div>
    </div>
  </template>

@endsection

@push('scripts')
  <script>
    $(function () {

      // PROPERTY BASIC TAB — category, type, construction radios
      const types = {
        residential: ['Plot', 'House', 'Other'],
        commercial:  ['Plot', 'Building', 'Plaza', 'Other'],
        other:       ['Plot', 'Mosque', 'Temple', 'Hospital', 'Park', 'School', 'Govt-Office', 'Other']
      };
      const type_select    = document.getElementById('type');
      const category_error = document.getElementById('category_error');
      const cons_wrapper   = document.getElementById('constructed_wrapper');

      document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', () => {
          document.querySelectorAll('.category-card').forEach(c => {
            c.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');
            const dot = c.querySelector('.radio-dot');
            dot.classList.remove('bg-primary', 'border-primary');
            dot.classList.add('border-secondary');
          });
          card.classList.add('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');
          const dot = card.querySelector('.radio-dot');
          dot.classList.add('bg-primary', 'border-primary');
          dot.classList.remove('border-secondary');
          card.querySelector('input[type="radio"]').checked = true;
          const val = card.querySelector('input').value;
          type_select.innerHTML = '<option value="" selected disabled>Select Type</option>';
          types[val].forEach(t => {
            const o = document.createElement('option');
            o.value = t.toLowerCase(); o.text = t;
            if (o.value === type_select.dataset.old) o.selected = true;
            type_select.appendChild(o);
          });
          category_error.classList.add('d-none');
        });
      });

      type_select.addEventListener('change', function () {
        const is_plot = this.value === 'plot';
        cons_wrapper.classList.toggle('d-none', is_plot);
        if (is_plot) { document.getElementById('cons_no').checked = true; styleConstructionRadios(); updateSubmitBtn(); }
      });

      function styleConstructionRadios() {
        const yes = document.getElementById('cons_yes').checked;
        const ly = document.getElementById('label_cons_yes');
        const ln = document.getElementById('label_cons_no');
        ly.className = ln.className = 'flex-fill border rounded-3 text-center p-3 user-select-none';
        ly.style.cursor = ln.style.cursor = 'pointer';
        yes ? ly.classList.add('border-success','bg-success','bg-opacity-10')
          : ln.classList.add('border-primary','bg-primary','bg-opacity-10');
      }

      function updateSubmitBtn() {
        const c = document.getElementById('cons_yes').checked;
        const constTab = document.getElementById('const_tab');
        if (c) {
          constTab.classList.remove('d-none');
        } else {
          constTab.classList.add('d-none');
        }
        // document.getElementById('save_btn').textContent = c ? 'Save & Continue' : 'Save Documents';
        // document.getElementById('btn_icon').className = c ? 'ti tabler-arrow-right me-1' : 'ti tabler-device-floppy me-1';
      }

      document.querySelectorAll('input[name="is_constructed"]').forEach(r =>
        r.addEventListener('change', () => { styleConstructionRadios(); updateSubmitBtn(); })
      );
      styleConstructionRadios();

      document.getElementById('property_form').addEventListener('submit', function (e) {
        if (!document.querySelector('input[name="category"]:checked')) {
          e.preventDefault();
          category_error.classList.remove('d-none');
          document.getElementById('categoryGrid').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });

      const preSelected = document.querySelector('input[name="category"]:checked');
      if (preSelected) preSelected.closest('.category-card').click();

      // CONSTRUCTION TAB — clone templates, re-index names
      const isCommercial = {{ isset($property) && $property->category === 'commercial' ? 'true' : 'false' }};
      let floorCount = 0;

      // Clone a <template> by id, replace __PLACEHOLDER__ tokens, return DOM node

      function cloneTpl(id, replacements) {
        const tpl  = document.getElementById(id);
        const node = tpl.content.cloneNode(true);
        // serialise → replace → re-parse so all name attrs are updated
        const div = document.createElement('div');
        div.appendChild(node);
        let html = div.innerHTML;
        Object.entries(replacements).forEach(([k, v]) => {
          html = html.replaceAll(k, v);
        });
        div.innerHTML = html;
        return div.firstElementChild;
      }

      // Add a blank dimension row into a .dim-block
      function addDimRow($dimBlock, prefix) {
        const idx  = $dimBlock.find('.dim-rows .dimension-row').length;
        const node = cloneTpl('tpl-dim-row', { '__PREFIX__': prefix, '__DIM__': idx });
        $dimBlock.find('.dim-rows').append(node);
      }

      // Add a room into a container
      function addRoom($container, prefix, roomIdx) {
        const node = cloneTpl('tpl-room', { '__PREFIX__': prefix, '__ROOM__': roomIdx });
        const $room = $(node);
        // seed first dim row for room
        addDimRow($room.find('.dim-block'), prefix + '[rooms][' + roomIdx + ']');
        $container.append($room);
      }

      // Add a unit into a floor
      function addUnit($container, floorPrefix, unitIdx) {
        const uPrefix = floorPrefix + '[units][' + unitIdx + ']';
        const node    = cloneTpl('tpl-unit', { '__PREFIX__': floorPrefix, '__UNIT__': unitIdx });
        $container.append(node);
      }

      // Add a floor
      function addFloor() {
        const fIdx   = floorCount++;
        const fPrefix = 'floors[' + fIdx + ']';
        const node   = cloneTpl('tpl-floor', { '__PREFIX__': fPrefix });
        const $floor = $(node);
        $floor.attr('data-floor-idx', fIdx).attr('data-floor-prefix', fPrefix);
        // seed first dim row for floor
        addDimRow($floor.find('.dim-block').first(), fPrefix);
        // show units section only for commercial
        if (isCommercial) $floor.find('.units-section-wrapper').removeClass('d-none');
        // store prefix on add-room / add-unit buttons
        $floor.find('.btn-add-floor-room').attr('data-floor-prefix', fPrefix).attr('data-room-count', 0);
        $floor.find('.btn-add-unit').attr('data-floor-prefix', fPrefix).attr('data-unit-count', 0);
        $('#floors-container').append($floor);
      }

      // ── EVENTS ────────────────────────────────────────────
      $('#btn-add-floor').on('click', function () { addFloor(); });

      $(document).on('click', '.btn-remove-floor', function (e) {
        e.stopPropagation();
        $(this).closest('.floor-item').remove();
      });

      $(document).on('click', '.btn-add-floor-room', function (e) {
        e.stopPropagation();
        const $btn    = $(this);
        const prefix  = $btn.data('floor-prefix');
        const roomIdx = parseInt($btn.attr('data-room-count'));
        addRoom($btn.prev('.floor-rooms-container'), prefix, roomIdx);
        $btn.attr('data-room-count', roomIdx + 1);
      });

      $(document).on('click', '.btn-add-unit-room', function (e) {
        e.stopPropagation();
        const $btn    = $(this);
        const uPrefix = $btn.closest('.unit-item').find('[name$="[unit_no]"]').attr('name').replace('[unit_no]','');
        const roomIdx = parseInt($btn.attr('data-room-count'));
        addRoom($btn.prev('.unit-rooms-container'), uPrefix, roomIdx);
        $btn.attr('data-room-count', roomIdx + 1);
      });

      $(document).on('click', '.btn-remove-room', function (e) {
        e.stopPropagation();
        $(this).closest('.room-item').remove();
      });

      $(document).on('click', '.btn-add-unit', function (e) {
        e.stopPropagation();
        const $btn    = $(this);
        const fPrefix = $btn.data('floor-prefix');
        const unitIdx = parseInt($btn.attr('data-unit-count'));
        addUnit($btn.prev('.floor-units-container'), fPrefix, unitIdx);
        $btn.attr('data-unit-count', unitIdx + 1);
      });

      $(document).on('click', '.btn-remove-unit', function (e) {
        e.stopPropagation();
        $(this).closest('.unit-item').remove();
      });

      $(document).on('change', '.has-units-check', function () {
        const $floor = $(this).closest('.floor-item');
        const checked = $(this).is(':checked');
        $floor.find('.units-section').toggleClass('d-none', !checked);
        $floor.find('.floor-rooms-section').toggleClass('d-none', checked);
      });

      $(document).on('click', '.btn-add-dim', function (e) {
        e.stopPropagation();
        const $btn   = $(this);
        const $block = $btn.closest('.dim-block');
        // derive prefix from existing dim row names, or from closest floor/room/unit
        const existing = $block.find('[name*="[dimensions]"]').first();
        if (!existing.length) return;
        const prefix = existing.attr('name').replace(/\[dimensions\]\[\d+\]\[.*?\]$/, '');
        addDimRow($block, prefix);
      });

      $(document).on('click', '.btn-remove-dim', function (e) {
        e.stopPropagation();
        const $rows = $(this).closest('.dim-rows');
        if ($rows.find('.dimension-row').length > 1) $(this).closest('.dimension-row').remove();
      });

      // seed one floor on load
      addFloor();
    });



    function confirmOnSave(event) {
      event.preventDefault();
      const el = event.currentTarget;
      const form = el.closest('form');
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, upload it!'
      }).then(result => {
        if (!result.isConfirmed) return;
        if (form) {
          form.submit();
        }
      });
    }
  </script>
@endpush

@push('styles')
  <style>
    .detail_section { width:32px; height:32px; font-size:.72rem; }
    .category_pic   { width:56px; height:56px; }
    .radio_circle   { width:18px; height:18px; }
    .room_header    { background: linear-gradient(135deg,#f8f9ff 0%,#eef1fb 100%); }
    .room_header > span {
      font-size:          .72rem;
      letter-spacing:     .06em;
      text-transform:     uppercase;
    }

    .room_header > span >span{
      width:22px;height:22px;
    }

    .remove_item{
      font-size:.7rem;
      color:#dc3545;
      background:rgba(220,53,69,.08);
      border:none;
    }
.unit_header{
  background:linear-gradient(135deg,#f0fdf8 0%,#e6f7f0 100%);
}

.unit_header >span {
  font-size:.72rem;
  letter-spacing:.06em;
  text-transform:uppercase;
}
.unit_header >span >span {
  width:22px;height:22px;
}
.rooms_title {
  font-size: .7rem;
  letter-spacing: .05em;
}

.add_room_btn{
  font-size:.72rem;
  border:1px dashed #adb5bd;
  color:#6c757d;
  background:transparent;
}
.floor_header{
  background:linear-gradient(135deg,#eef1fb 0%,#e4eaff 100%);
}

.floor_title{
  font-size:.8rem;
  letter-spacing:.05em;
  text-transform:uppercase;
}
.floor_icon{
  width:26px;
  height:26px;
}
.remove_floor{
  font-size:.72rem;
  color:#dc3545;
  background:rgba(220,53,69,.08);border:none;
}

.add_unit_btn{
  font-size:.72rem;
  color:#dc3545;
  background:rgba(220,53,69,.08);
  border:none;
}

  </style>
@endpush
