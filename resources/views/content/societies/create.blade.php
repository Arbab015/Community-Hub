@extends('layouts.layoutMaster')

@section('title', 'Add New Society')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss', 'resources/assets/vendor/libs/dropzone/dropzone.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/flatpickr/flatpickr.scss', 'resources/assets/vendor/libs/typeahead-js/typeahead.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss', 'resources/assets/vendor/libs/notyf/notyf.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/dropzone/dropzone.js', 'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js', 'resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/flatpickr/flatpickr.js', 'resources/assets/vendor/libs/typeahead-js/typeahead.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js', 'resources/assets/vendor/libs/notyf/notyf.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/form-validation.js', 'resources/assets/js/forms-file-upload.js', 'resources/assets/js/ui-toasts.js'])
@endsection

@section('content')

  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Society Management</p>
      <h4 class="mb-1">Societies</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="{{ route('dashboard.analytics') }}">Home</a></li>
          <li class="breadcrumb-item"><a href="{{ route('societies.index', $slug) }}">Societies</a></li>
          <li class="breadcrumb-item active">Create</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-building-community text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
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

  {{-- TAB NAV --}}
  @php
    $activeTab = request('tab', 'basic');
    $hasBasicErrors = $errors->hasAny(['name','country','city','address','postal_code','marla_size']);
    if ($hasBasicErrors) $activeTab = 'basic';
    elseif ($errors->hasAny(['main_pic','documents'])) $activeTab = 'documents';
  @endphp

  <div class="col-12">
    <ul class="nav nav-pills flex-column flex-sm-row gap-1 border rounded-3 p-1 bg-light mb-4">
      <li class="nav-item">
        <button class="nav-link {{ $activeTab === 'basic' ? 'active' : '' }} w-100 text-start"
                data-bs-toggle="pill" data-bs-target="#tab_basic">
          <i class="ti tabler-building-community me-2 d-none d-sm-inline-block"></i> Society Details
        </button>
      </li>
      <li class="nav-item" id="documents_tab_nav">
        <button
          class="nav-link {{ $activeTab === 'documents' ? 'active' : '' }} w-100 text-start {{ !isset($society) ? 'disabled' : '' }}"
          data-bs-toggle="pill" data-bs-target="#tab_documents">
          <i class="ti tabler-files me-2 d-none d-sm-inline-block"></i> Documents & Media
        </button>
      </li>
    </ul>
  </div>

  <div class="card border-0 shadow-sm rounded-3">
    <div class="tab-content p-0">

      {{-- TAB 1: SOCIETY BASIC INFO --}}
      <div class="tab-pane fade {{ $activeTab === 'basic' ? 'show active' : '' }}" id="tab_basic">
        <form method="POST" action="{{ route('society.store', $slug) }}" id="create_society_form"
              enctype="multipart/form-data">
          @csrf

          @if(isset($society))
            <input type="hidden" name="society_id" value="{{$society->id}}">
          @endif

          <div class="card-body border-bottom p-4">
            <div class="alert alert-warning alert-dismissible mb-4" role="alert">
              <h5 class="alert-heading mb-1">Ensure that these requirements are met</h5>
              <span>
                <i class="ti tabler-point"></i> Related documents are important documents connected to the society's identity,
                such as agreements, registration papers, and other official records.<br>
                <i class="ti tabler-point"></i> Property marla size must be in <strong>Square Feet's</strong>.
              </span>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <div class="d-flex align-items-center gap-2 mb-4">
              <span
                class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">01</span>
              <h5 class="mb-0 fw-bold">Society Details</h5>
            </div>

            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label fw-semibold small text-uppercase text-muted required" for="name">Society
                  Name</label>
                <input type="text" id="name" name="name" required
                       class="form-control @error('name') is-invalid @enderror"
                       placeholder="Enter registered name of your society"
                       value="{{ old('name', $society->name ?? '') }}" />
                @error('name')
                <div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold small text-uppercase text-muted required"
                       for="country">Country</label>
                <select id="country" name="country" required
                        class="form-select select2 @error('country') is-invalid @enderror"
                        data-allow-clear="true">
                  <option value="">Select Society Country</option>
                  @foreach(['Pakistan','Australia','Bangladesh','Belarus','Brazil','Canada','China','France','Germany','India','Indonesia','Israel','Italy','Japan','Korea','Mexico','Philippines','Russia','South Africa','Thailand','Turkey','Ukraine','United Arab Emirates','United Kingdom','United States'] as $country)
                    <option
                      value="{{ $country }}" {{ old('country', $society->country ?? '') == $country ? 'selected' : '' }}>
                      {{ $country }}
                    </option>
                  @endforeach
                </select>
                @error('country')
                <div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold small text-uppercase text-muted required" for="city">City</label>
                <input type="text" id="city" name="city" required
                       class="form-control @error('city') is-invalid @enderror"
                       placeholder="Enter your society city"
                       value="{{ old('city', $society->city ?? '') }}" />
                @error('city')
                <div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold small text-uppercase text-muted required"
                       for="address">Address</label>
                <input type="text" id="address" name="address" required
                       class="form-control @error('address') is-invalid @enderror"
                       placeholder="Enter complete society address"
                       value="{{ old('address', $society->address ?? '') }}" />
                @error('address')
                <div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold small text-uppercase text-muted required" for="postal_code">Postal
                  Code</label>
                <input type="text" id="postal_code" name="postal_code" required
                       class="form-control @error('postal_code') is-invalid @enderror"
                       placeholder="Enter Postal code"
                       inputmode="numeric"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                       value="{{ old('postal_code', $society->postal_code ?? '') }}" />
                @error('postal_code')
                <div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold small text-uppercase text-muted required" for="marla_size">Property
                  Marla Size</label>
                <input type="number" id="marla_size" name="marla_size" required step="0.01"
                       class="form-control @error('marla_size') is-invalid @enderror"
                       placeholder="Enter marla size in square feet's"
                       value="{{ old('marla_size', $society->marla_size ?? '') }}" />
                @error('marla_size')
                <div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>
            </div>
          </div>

          <div class="card-body p-4 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary px-4 fw-bold">
              <i class="ti tabler-arrow-right me-1"></i> Save & Continue
            </button>
          </div>
        </form>
      </div>

      {{-- TAB 2: DOCUMENTS & MEDIA --}}
      <div class="tab-pane fade {{ $activeTab === 'documents' ? 'show active' : '' }}" id="tab_documents">
        <form method="POST"
              action="{{ route('society.store', [$slug, $society->uuid ?? '']) }}"
              enctype="multipart/form-data"
              id="upload_media_property">
          @csrf
          <input type="hidden" name="request_type" value="document">

          <div class="card-body border-bottom p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
              <span
                class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">02</span>
              <h5 class="mb-0 fw-bold">Documents & Media <span class="text-muted fw-normal small">(optional)</span></h5>
            </div>

            <div class="row g-4">
              <div class="col-md-12">
                <label class="form-label fw-semibold small text-uppercase text-muted">Main Picture</label>
                <div class="dropzone needsclick dz-clickable @error('main_pic') border-danger @enderror"
                     id="dropzone-basic">
                  <div class="dz-message needsclick">
                    <i class="bx bx-upload" style="font-size: 3rem; color: #999;"></i>
                    <h6 class="m-0">Drop files here or click to upload</h6>
                    <span class="note needsclick text-muted d-block mt-0">(Upload main picture for the society)</span>
                    <span class="note needsclick text-muted d-block mt-0">(Main picture must be an Image)</span>
                  </div>
                </div>
                @error('main_pic')
                <div class="text-danger mt-2 d-flex align-items-center gap-1"><i
                    class="ti tabler-alert-circle"></i> {{ $message }}</div>@enderror
              </div>

              <div class="col-md-12">
                <label class="form-label fw-semibold small text-uppercase text-muted">Related Documents</label>
                <div class="dropzone needsclick dz-clickable dropzone_multi @error('documents') border-danger @enderror"
                     id="dropzone-multi">
                  <div class="dz-message needsclick">
                    <i class="bx bx-upload p-0" style="font-size: 3rem; color: #999;"></i>
                    <h6 class="m-0 pb-1">Drop files here or click to upload</h6>
                    <span class="note needsclick text-muted d-block mt-0">(Upload multiple documents related to the society)</span>
                    <span class="note needsclick text-muted d-block mt-0">(Types: jpg, jpeg, png, gif, svg, mp4, mov, avi, webm, mkv, pdf, doc, docx, xls, xlsx)</span>
                  </div>
                </div>
                @error('documents')
                <div class="text-danger mt-2 d-flex align-items-center gap-1"><i
                    class="ti tabler-alert-circle"></i> {{ $message }}</div>@enderror
              </div>
            </div>

            <button type="button" id="upload_files_btn"
                    class="btn btn-sm btn-primary d-none d-flex align-items-center gap-1 mt-3">
              <i class="ti tabler-upload me-1"></i> Save Files
            </button>
          </div>

          <div class="card-body p-4 d-flex justify-content-between">
            <a href="{{ route('society.create', [$slug, $society->uuid]) }}"
               class="btn btn-outline-secondary px-4">
              <i class="ti tabler-arrow-left me-1"></i> Previous
            </a>
            <a href="{{ route('societies.index', $slug) }}"
               id="save_documents_btn"
               class="btn btn-primary px-4 fw-bold">
              <i class="ti tabler-device-floppy me-1"></i> Finish
            </a>
          </div>
        </form>
      </div>

    </div>
  </div>

@endsection

@push('scripts')
  <script>

    @if(($society?->attachments && $society->attachments->count()) || $society?->attachment)
      @if($society?->attachments && $society->attachments->count())
      window.existingDocuments = {!! json_encode(
            $society->attachments->map(function ($file) {
                   return [
                     'id' => $file->id,
                     'name' => $file->name,
                     'url' => asset('storage/' . $file->link),
                     'size' => $file->size ?? 0,
                   ];
                 })->values()
               ) !!};
    @endif
      @if($society?->attachment)
      window.existingMainPic = {!! json_encode([
                   'id' => $society->attachment->id,
                   'name' => $society->attachment->name,
                   'url' => asset('storage/' . $society->attachment->link),
                   'size' => $society->attachment->size ?? 0,
                 ]) !!};
    @endif
    @endif


    // After basic info saved successfully, redirect to documents tab
    @if(isset($society) && $activeTab === 'documents')
    document.addEventListener('DOMContentLoaded', function() {
      const docTab = document.querySelector('[data-bs-target="#tab_documents"]');
      if (docTab) {
        docTab.classList.remove('disabled');
      }
    });
    @endif
  </script>
@endpush
