@extends('layouts/layoutMaster')

@section('title', 'Add Property')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/notyf/notyf.scss',
    'resources/assets/vendor/libs/dropzone/dropzone.scss',
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/jquery-repeater/jquery-repeater.js','resources/assets/vendor/libs/notyf/notyf.js',
    'resources/assets/vendor/libs/dropzone/dropzone.js',
  ])
@endsection

@section('page-script')
  @vite(['resources/assets/js/repeater.js',  'resources/assets/js/common_property.js','resources/assets/js/create_property.js', 'resources/assets/js/forms-file-upload.js', 'resources/assets/js/ui-toasts.js'])
@endsection

@section('content')

  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Property Management</p>
      <h3 class="text-dark fw-bold mb-2">Add New Property</h3>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}" class="text-dark opacity-75 text-decoration-none">Home
            </a>
          </li>
          <li class="breadcrumb-item"><a href="{{ route('blocks.index', "society_blocks") }}"
                                         class="text-dark opacity-75 text-decoration-none">Blocks</a></li>
          <li class="breadcrumb-item"><a href="{{ route('block.view', ['uuid' => $block->uuid]) }}"
                                         class="text-dark opacity-75 text-decoration-none">Block Details</a></li>
          <li class="breadcrumb-item active text-dark opacity-50">Add Property</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-building-estate text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  {{-- ALERTS --}}
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

  {{-- GLOBAL VALIDATION SUMMARY (shows which tab has errors) --}}
  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible d-flex gap-3 mb-3" role="alert">
      <i class="ti tabler-alert-circle-filled fs-4 flex-shrink-0 mt-1"></i>
      <div>
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-1 small">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="alert alert-primary alert-dismissible d-flex gap-3 mb-4" role="alert">
    <i class="ti tabler-info-circle-filled fs-4 flex-shrink-0 mt-1"></i>
    <div>
      <strong>Before you begin — please note:</strong>
      <ul class="mb-0 mt-1 small">
        <li>Please select a <strong>Property Category</strong> first; the property type list will update automatically
          based on your selection.
        </li>
        <li>Ensure complete <strong>dimensions for the property and all rooms</strong> are provided, including accurate
          measurements for each side (e.g., length, width, front, right, etc.).
        </li>
        <li>If the construction is completed, please select <strong>"Constructed"</strong> to enable additional fields.
        </li>
        <li>Ensure that all required fields are properly filled to avoid incomplete records.</li>
        <li>Please upload only one image as the main property picture.</li>
        <li>A maximum of <strong>six files</strong> can be uploaded at a time in the documents section.</li>
      </ul>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>

  {{-- TAB NAV --}}
  @php
    $activeTab = request('tab', 'property');
    $hasPropertyErrors = $errors->hasAny(['category','property_no','name','type','address','is_constructed','dimensions']);
    $hasDocErrors      = $errors->hasAny(['main_pic','documents']);
    $hasConstErrors    = $errors->hasAny(['floors']);
    // If there are errors on the current section, redirect to that section tab
    if ($hasPropertyErrors) $activeTab = 'property';
    elseif ($hasDocErrors)  $activeTab = 'documents';
    elseif ($hasConstErrors) $activeTab = 'construction';
  @endphp

  <div class="col-12">
    <ul class="nav nav-pills mb-4" role="tablist">
      <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'property' ? 'active' : '' }} position-relative" type="button">
          Property Basic
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'documents' ? 'active' : '' }} position-relative" type="button">
          Documents & Media
        </a>
      </li>
      <li class="nav-item {{ $property?->construction_status === "pending" ?  'd-none': 'd-block'  }}" id="const_tab">
        <a class="nav-link {{ $activeTab === 'construction' ? 'active' : '' }} position-relative" type="button">
          Construction Details
        </a>
      </li>
    </ul>
  </div>

  <div class="card border-0 shadow-sm rounded-3">
    <div class="tab-content p-0">

      {{--  PROPERTY BASIC TAB --}}
      <div class="tab-pane fade {{ $activeTab === 'property' ? 'show active' : '' }}" id="property">
        <form id="property_form" method="post" action="{{ route('property.store') }}">
          @csrf
          @if(isset($property))
            <input type="hidden" name="property_id" value="{{ $property->id }}">
          @endif
          <input type="hidden" name="block_id" value="{{ $block->id }}">
          <input type="hidden" name="section" value="property">

          {{-- STEP 01 CATEGORY --}}
          <div class="card-body border-bottom p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
              <span
                class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">01</span>
              <h5 class="mb-0 fw-bold">Property Category</h5>
            </div>

            <div class="row g-3" id="categoryGrid">
              <div class="col-md-4">
                <label for="cat_residential"
                       class="category-card d-block border rounded-3 text-center p-4 h-100 user-select-none {{ old('category', $property->category ?? '') == 'residential' ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : '' }}"
                       style="cursor:pointer;">
                  <input type="radio" id="cat_residential" name="category" value="residential"
                         {{ old('category', $property->category ?? '') == 'residential' ? 'checked' : '' }} hidden>
                  <div
                    class="bg-success bg-opacity-10 rounded-3 d-inline-flex align-items-center justify-content-center mb-3 category_pic">
                    <i class="ti tabler-home-2 fs-3 text-success"></i>
                  </div>
                  <h6 class="fw-bold mb-1">Residential</h6>
                  <p class="text-muted small mb-3">Plots, Houses & more</p>
                  <span
                    class="radio-dot d-inline-block border border-2 rounded-circle radio_circle {{ old('category', $property->category ?? '') == 'residential' ? 'bg-primary border-primary' : 'border-secondary' }}"></span>
                </label>
              </div>
              <div class="col-md-4">
                <label for="cat_commercial"
                       class="category-card d-block border rounded-3 text-center p-4 h-100 user-select-none {{ old('category', $property->category ?? '') == 'commercial' ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : '' }}"
                       style="cursor:pointer;">
                  <input type="radio" id="cat_commercial" name="category" value="commercial"
                         {{ old('category', $property->category ?? '') == 'commercial' ? 'checked' : '' }} hidden>
                  <div
                    class="bg-primary bg-opacity-10 rounded-3 d-inline-flex align-items-center justify-content-center mb-3 category_pic">
                    <i class="ti tabler-building-skyscraper fs-3 text-primary"></i>
                  </div>
                  <h6 class="fw-bold mb-1">Commercial</h6>
                  <p class="text-muted small mb-3">Buildings, Plazas & more</p>
                  <span
                    class="radio-dot d-inline-block border border-2 rounded-circle radio_circle {{ old('category', $property->category ?? '') == 'commercial' ? 'bg-primary border-primary' : 'border-secondary' }}"></span>
                </label>
              </div>
              <div class="col-md-4">
                <label for="cat_other"
                       class="category-card d-block border rounded-3 text-center p-4 h-100 user-select-none {{ old('category', $property->category ?? '') == 'other' ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : '' }}"
                       style="cursor:pointer;">
                  <input type="radio" id="cat_other" name="category" value="other"
                         {{ old('category', $property->category ?? '') == 'other' ? 'checked' : '' }} hidden>
                  <div
                    class="bg-warning bg-opacity-10 rounded-3 d-inline-flex align-items-center justify-content-center mb-3 category_pic">
                    <i class="ti tabler-map-2 fs-3 text-warning"></i>
                  </div>
                  <h6 class="fw-bold mb-1">Other</h6>
                  <p class="text-muted small mb-3">Mosque, Park, Hospital & more</p>
                  <span
                    class="radio-dot d-inline-block border border-2 rounded-circle radio_circle {{ old('category', $property->category ?? '') == 'other' ? 'bg-primary border-primary' : 'border-secondary' }}"></span>
                </label>
              </div>
            </div>

            @error('category')
            <div class="text-danger small mt-2">
              <i class="ti tabler-alert-triangle me-1"></i>{{ $message }}
            </div>
            @enderror

          </div>

          {{-- STEP 02 — PROPERTY DETAILS --}}
          <div class="card-body border-bottom p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
              <span
                class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">02</span>
              <h5 class="mb-0 fw-bold">Property Details</h5>
            </div>

            <div class="row g-4">
              <div class="col-md-4">
                <label for="name" class="form-label fw-semibold small text-uppercase text-muted ">Property Name</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="ti tabler-tag text-muted"></i></span>
                  <input type="text" id="name" name="name"
                         value="{{ old('name', $property->name ?? '') }}"
                         class="form-control border-start-0 @error('name') is-invalid @enderror"
                         placeholder="e.g. Gillani Market">
                </div>
                @error('name')
                <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}
                </div>@enderror
              </div>

              <div class="col-md-4">
                <label for="property_no" class="form-label fw-semibold small text-uppercase text-muted required">Property
                  No</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="ti tabler-hash text-muted"></i></span>
                  <input type="text" id="property_no" name="property_no"
                         value="{{ old('property_no', $property->property_no ?? '') }}"
                         class="form-control border-start-0 @error('property_no') is-invalid @enderror"
                         required placeholder="e.g. A-101">
                </div>
                @error('property_no')
                <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}
                </div>@enderror
              </div>

              <div class="col-md-4">
                <label for="type" class="form-label fw-semibold small text-uppercase text-muted required">Type</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="ti tabler-list text-muted"></i></span>
                  <select id="type" name="type"
                          class="form-select border-start-0 @error('type') is-invalid @enderror"
                          required
                          data-old="{{ old('type', $property->type ?? '') }}">
                    <option value="" selected disabled>Select category first…</option>
                    {{-- Options populated by JS based on selected category --}}
                  </select>
                </div>
                @error('type')
                <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}
                </div>@enderror
              </div>

              <div class="col-md-6">
                <label for="street"
                       class="form-label fw-semibold small text-uppercase text-muted required">Street</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="ti tabler-road text-muted"></i></span>
                  <input type="text" id="street" name="street"
                         value="{{ old('street', $property->street ?? '') }}"
                         class="form-control border-start-0 @error('street') is-invalid @enderror"
                         required placeholder="Street-7">
                </div>
                @error('street')
                <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}
                </div>@enderror
              </div>

              <div class="col-md-6">
                <label for="landmark" class="form-label fw-semibold small text-uppercase text-muted">Nearest
                  Landmark</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i
                      class="ti tabler-map-pin text-muted"></i></span>
                  <input type="text" id="landmark" name="landmark"
                         value="{{ old('landmark', $property->landmark ?? '') }}"
                         class="form-control border-start-0 @error('landmark') is-invalid @enderror"
                         placeholder="Opposite of dubai plaza..">
                </div>
                @error('landmark')
                <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}
                </div>@enderror
              </div>


              <div class="col-md-6 @if(!isset($property) )d-none @else property_exist @endif " id="constructed_wrapper">
                <label class="form-label fw-semibold small text-uppercase text-muted required">Construction
                  Status</label>
                <div class="d-flex gap-3">
                  <label for="cons_constructed" id="label_constructed" class="const_labels">
                    <input type="radio" id="cons_constructed" name="construction_status"
                           {{ old('construction_status', $property->construction_status ?? '') == "constructed" ? 'checked' : '' }} value="constructed"
                           hidden>
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <i class="ti tabler-check fs-4 text-success"></i>
                      <span class="small fw-semibold">Constructed</span>
                    </div>
                  </label>
                  <label for="cons_in_progress" id="label_progress" class="const_labels">
                    <input type="radio" id="cons_in_progress" name="construction_status"
                           {{ old('construction_status', $property->construction_status ?? '') == "in_progress" ? 'checked' : '' }} value="in_progress"
                           hidden>
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <i class="ti tabler-clock fs-4 text-primary"></i>
                      <span class="small fw-semibold text-primary">In Progress</span>
                    </div>
                  </label>
                  <label for="cons_pending" id="label_pending" class="const_labels">
                    <input type="radio" id="cons_pending" name="construction_status"
                           {{ old('construction_status', $property->construction_status ?? '') == "pending" ? 'checked' : '' }} value="pending"
                           hidden>
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <i class="ti tabler-loader fs-4 text-warning"></i>
                      <span class="small fw-semibold text-primary">Pending</span>
                    </div>
                  </label>
                </div>
                @error('construction_status')
                <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}
                </div>@enderror
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
      </div>{{-- /.tab-pane #property --}}


      {{--  DOCUMENTS & MEDIA TAB--}}
      <div class="tab-pane fade {{ $activeTab === 'documents' ? 'show active' : '' }}" id="doc_&_media">
        <form method="post" action="{{ route('property.store') }}" enctype="multipart/form-data"
              id="upload_media_property">
          @csrf
          <input type="hidden" name="block_id" value="{{ $block->id }}">
          <input type="hidden" name="section" value="documents">
          <input type="hidden" name="property_id" value="{{ $property->id ?? '' }}">

          <div class="card-body border-bottom p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
              <span
                class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">04</span>
              <h5 class="mb-0 fw-bold">Documents & Media (optional)</h5>
            </div>

            <div class="row g-4 mb-3">
              <div class="col-md-12">
                <label class="form-label fw-semibold small text-uppercase text-muted">
                  Main Picture
                </label>
                <div class="dropzone needsclick dz-clickable @error('main_pic') is-invalid border-danger @enderror"
                     id="dropzone-basic">
                  <div class="dz-message needsclick">
                    <i class="bx bx-upload" style="font-size: 3rem; color: #999;"></i>
                    <h6 class="m-0">Drop files here or click to upload</h6>
                    <span class="note needsclick text-muted d-block mt-0">(Upload main picture for the property)</span>
                    <span class="note needsclick text-muted d-block mt-0">(Main picture must be an Image)</span>
                  </div>
                </div>
                @error('main_pic')
                <div class="text-danger mt-2 d-flex align-items-center gap-1">
                  <i class="ti tabler-alert-circle"></i> {{ $message }}
                </div>
                @enderror
              </div>

              <div class="col-md-12">
                <label class="form-label fw-semibold small text-uppercase text-muted">Add Related Documents</label>
                <div
                  class="dropzone needsclick dz-clickable dropzone_multi @error('documents') is-invalid border-danger @enderror"
                  id="dropzone-multi">
                  <div class="dz-message needsclick">
                    <i class="bx bx-upload p-0" style="font-size: 3rem; color: #999;"></i>
                    <h6 class="m-0 pb-1">Drop files here or click to upload</h6>
                    <span class="note needsclick text-muted d-block mt-0">(Upload multiple documents related to the property)</span>
                    <span class="note needsclick text-muted d-block mt-0">(Document be of types: jpg, jpeg, png, gif,svg, mp4, mov, avi, webm, mkv, pdf, doc, docx, xls, xlsx)</span>

                  </div>
                </div>
                @error('documents')
                <div class="text-danger mt-2 d-flex align-items-center gap-1">
                  <i class="ti tabler-alert-circle"></i> {{ $message }}
                </div>
                @enderror
              </div>
            </div>

            <button type="button" id="upload_files_btn"
                    class="btn btn-sm btn-primary d-none d-flex align-items-center gap-1">
              <i class="ti tabler-upload me-1"></i> Save Files
            </button>
          </div>


          <div class="card-body p-4 d-flex justify-content-between">
            <a
              href="{{ route('property.create', ['block_uuid' => $block->uuid, 'uuid' => $property->uuid ?? '', 'tab' => 'property']) }}"
              class="btn btn-outline-secondary px-4">
              <i class="ti tabler-arrow-left me-1"></i> Previous
            </a>

            <a class="btn btn-primary px-4 fw-bold" id="save_documents_btn"
               href="{{ $property?->construction_status !== "pending" ? route('property.create', ['block_uuid' => $block->uuid, 'uuid' => $property->uuid ?? '', 'tab' => 'construction']) :  route('block.view', ['uuid' => $block->uuid , "isPending"=> "yes"]) }}">
              <i
                class="ti @if($property?->construction_status === "pending")tabler-device-floppy @else tabler-arrow-right @endif me-1"
                id="btn_icon"></i>
              <span>{{ $property?->construction_status === "pending" ? 'Save Documents' : 'Save & Continue'}}</span>
            </a>

          </div>
        </form>
      </div>

      {{--     CONSTRUCTION DETAILS TAB --}}
      <div class="tab-pane fade {{ $activeTab === 'construction' ? 'show active' : '' }}" id="const_details">
        <form method="post" action="{{ route('property.store') }}" id="construction_form">
          @csrf
          <input type="hidden" name="block_id" value="{{ $block->id }}">
          <input type="hidden" name="section" value="construction">
          <input type="hidden" name="property_id" value="{{ $property->id ?? '' }}">

          <div class="card-body border-bottom p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
              <span
                class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">05</span>
              <h5 class="mb-0 fw-bold">Floors with Details</h5>
            </div>

            {{-- SERVER-SIDE RE-RENDER on validation failure --}}
            @php $oldFloors = old('floors', []); @endphp

            <div id="floors-container">
              @foreach($oldFloors as $fi => $floor)
                @php $fPrefix = "floors[$fi]"; $hasUnits = !empty($floor['has_units']); @endphp
                @include('components.property.templates.floor', compact('fPrefix', 'fi', 'floor', 'hasUnits'))
              @endforeach
            </div>

            <button type="button" id="btn-add-floor"
                    class="btn btn-sm d-inline-flex align-items-center gap-1 mt-2 rounded-pill px-3 py-2 fw-semibold"
                    style="font-size:.78rem; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border:none; box-shadow:0 2px 8px rgba(102,126,234,.35);">
              <i class="ti tabler-plus me-1"></i> Add Floor
            </button>
          </div>

          <div class="card-body p-4 d-flex justify-content-between">
            <a
              href="{{ route('property.create', ['block_uuid' => $block->uuid, 'uuid' => $property->uuid ?? '', 'tab' => 'documents']) }}"
              class="btn btn-outline-secondary px-4">
              <i class="ti tabler-arrow-left me-1"></i> Previous
            </a>
            <button type="submit" class="btn btn-primary px-4 fw-bold">
              <i class="ti tabler-device-floppy me-1"></i> Save Construction
            </button>
          </div>
        </form>
      </div>{{-- /.tab-pane #const_details --}}

    </div>{{-- /.tab-content --}}
  </div>{{-- /.card --}}

  {{-- JS templates: unset loop vars first so templates render as <template> tags, not visible divs --}}
  @php unset($fPrefix, $fi, $floor, $hasUnits, $rPrefix, $uPrefix, $ri, $ui, $room, $unit, $p, $di, $dim); @endphp
  @include('components.property.templates.dimension')
  @include('components.property.templates.room')
  @include('components.property.templates.unit')
  @include('components.property.templates.floor')

@endsection

@push('scripts')
  <script>
    @if(($property?->attachments && $property->attachments->count()) || $property?->attachment)
      @if($property?->attachments && $property->attachments->count())
      window.existingDocuments = {!! json_encode(
            $property->attachments->map(function ($file) {
                   return [
                     'id' => $file->id,
                     'name' => $file->name,
                     'url' => asset('storage/' . $file->link),
                     'size' => $file->size ?? 0,
                   ];
                 })->values()
               ) !!};
    @endif
      @if($property?->attachment)
      window.existingMainPic = {!! json_encode([
                   'id' => $property->attachment->id,
                   'name' => $property->attachment->name,
                   'url' => asset('storage/' . $property->attachment->link),
                   'size' => $property->attachment->size ?? 0,
                 ]) !!};
    @endif
      @endif
      window.isResidential = {{ isset($property) && $property->category === 'residential' ? 'true' : 'false' }};
    window.initialFloorCount = {{ count(old('floors', [])) }};
  </script>
@endpush
