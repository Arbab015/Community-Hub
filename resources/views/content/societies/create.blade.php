@extends('layouts.layoutMaster')

@section('title', 'Add New Society')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss', 'resources/assets/vendor/libs/dropzone/dropzone.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/flatpickr/flatpickr.scss', 'resources/assets/vendor/libs/typeahead-js/typeahead.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/dropzone/dropzone.js', 'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js', 'resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/flatpickr/flatpickr.js', 'resources/assets/vendor/libs/typeahead-js/typeahead.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js'])
@endsection

<!-- Page Scripts -->
@section('page-script')
  @vite(['resources/assets/js/form-validation.js', 'resources/assets/js/forms-file-upload.js'])
@endsection

@section('content')

  <h4 class="mb-1">Create Society</h4>
  <nav aria-label="breadcrumb " class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('societies.index', $slug) }}">Societies</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Create</li>
    </ol>
  </nav>

  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0"><strong>Create Your Society</strong></h5>
        </div>
        @if (session('success'))
          <div class="alert alert-success">
            {{ session('success') }}
          </div>
        @endif

        {{-- Error Message (for general errors) --}}
        @if (session('error'))
          <div class="alert alert-danger">
            {{ session('error') }}
          </div>
        @endif

        <div class="card-body">
          <form method="post" action="{{ route('society.store', $slug) }}" id="create_society_form"
            enctype="multipart/form-data" class="row g-6 validation_form">
            @csrf
            <div class="alert alert-warning alert-dismissible mb-0" role="alert">
              <h5 class="alert-heading mb-1">Ensure that these requirements are met</h5>
              <span>Related documents are important documents connected to the society's identity, <br> such as
                agreements,
                registration papers, and other official records.</span>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <!-- Account Details -->
            <div class="col-12">
              <h6 class="fw-bolder">1. Society Details</h6>
              <hr class="mt-0" />
            </div>
            <div class="col-md-6 form-control-validation mt-1">
              <label class="form-label fw-bolder required" for="name">Society Name</label>
              <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" required
                placeholder="Enter registered name of your society" name="name" value="{{ old('name') }}" />
              @error('name')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation mt-1">
              <label class="form-label fw-bolder required" for="country">Country</label>
              <select id="country" name="country" class="form-select select2 @error('country') is-invalid @enderror"
                Required data-allow-clear="true">
                <option value="">Select Society Country
                </option>
                <option value="Pakistan" {{ old('country') == 'Pakistan' ? 'selected' : '' }}>
                  Pakistan</option>
                <option value="Australia" {{ old('country') == 'Australia' ? 'selected' : '' }}>
                  Australia</option>
                <option value="Bangladesh" {{ old('country') == 'Bangladesh' ? 'selected' : '' }}>
                  Bangladesh</option>
                <option value="Belarus" {{ old('country') == 'Belarus' ? 'selected' : '' }}>Belarus
                </option>
                <option value="Brazil" {{ old('country') == 'Brazil' ? 'selected' : '' }}>Brazil
                </option>
                <option value="Canada" {{ old('country') == 'Canada' ? 'selected' : '' }}>Canada
                </option>
                <option value="China" {{ old('country') == 'China' ? 'selected' : '' }}>China
                </option>
                <option value="France" {{ old('country') == 'France' ? 'selected' : '' }}>France
                </option>
                <option value="Germany" {{ old('country') == 'Germany' ? 'selected' : '' }}>
                  Germany
                </option>
                <option value="India" {{ old('country') == 'India' ? 'selected' : '' }}>India
                </option>
                <option value="Indonesia" {{ old('country') == 'Indonesia' ? 'selected' : '' }}>
                  Indonesia</option>
                <option value="Israel" {{ old('country') == 'Israel' ? 'selected' : '' }}>Israel
                </option>
                <option value="Italy" {{ old('country') == 'Italy' ? 'selected' : '' }}>Italy
                </option>
                <option value="Japan" {{ old('country') == 'Japan' ? 'selected' : '' }}>Japan
                </option>
                <option value="Korea" {{ old('country') == 'Korea' ? 'selected' : '' }}>Korea,
                  Republic of</option>
                <option value="Mexico" {{ old('country') == 'Mexico' ? 'selected' : '' }}>Mexico
                </option>
                <option value="Philippines" {{ old('country') == 'Philippines' ? 'selected' : '' }}>Philippines
                </option>
                <option value="Russia" {{ old('country') == 'Russia' ? 'selected' : '' }}>Russian
                  Federation</option>
                <option value="South Africa" {{ old('country') == 'South Africa' ? 'selected' : '' }}>South Africa
                </option>
                <option value="Thailand" {{ old('country') == 'Thailand' ? 'selected' : '' }}>
                  Thailand</option>
                <option value="Turkey" {{ old('country') == 'Turkey' ? 'selected' : '' }}>Turkey
                </option>
                <option value="Ukraine" {{ old('country') == 'Ukraine' ? 'selected' : '' }}>
                  Ukraine</option>
                <option value="United Arab Emirates" {{ old('country') == 'United Arab Emirates' ? 'selected' : '' }}>
                  United Arab Emirates</option>
                <option value="United Kingdom" {{ old('country') == 'United Kingdom' ? 'selected' : '' }}>United
                  Kingdom
                </option>
                <option value="United States" {{ old('country') == 'United States' ? 'selected' : '' }}>United States
                </option>
              </select>
              @error('country')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="city">City</label>
              <input type="text" class="form-control @error('city') is-invalid @enderror" name="city" id="city"
                placeholder="Enter your society city" value="{{ old('city') }}" required />

              @error('city')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="address">Address</label>
              <input type="text" class="form-control @error('address') is-invalid @enderror" name="address"
                id="address" placeholder="Enter complete society address" value="{{ old('address') }}" required />

              @error('address')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-12 ">
              <h6 class="mt-2 fw-bolder">2. Documents</h6>
              <hr class="mt-0" />
            </div>

            <div class="col-md-12 mt-0">
              <label class="form-label fw-bolder">Main Picture</label>
              <div class="dropzone needsclick dz-clickable @error('main_pic') is-invalid @enderror" id="dropzone-basic">
                <div class="dz-message needsclick">
                  <i class="bx bx-upload" style="font-size: 3rem; color: #999;"></i>
                  <h6 class="m-0">Drop files here or click to upload</h6>
                  <span class="note needsclick text-muted d-block mt-0">(Upload main picture for the society)</span>
                </div>
              </div>
              @error('main_pic')
                <div class="text-danger mt-2">{{ $message }}</div>
              @enderror
            </div>

            <!-- Related Documents - Multiple Files Upload -->
            <div class="col-md-12">
              <label class="form-label fw-bolder required">Related Documents</label>
              <div class="dropzone needsclick dz-clickable dropzone_multi @error('documents') is-invalid @enderror"
                id="dropzone-multi">
                <div class="dz-message needsclick">
                  <i class="bx bx-upload p-0" style="font-size: 3rem; color: #999;"></i>
                  <h6 class="m-0 pb-1">Drop files here or click to upload</h6>
                  <span class="note needsclick text-muted d-block mt-0">(Upload multiple documents related to the
                    society)</span>
                </div>
              </div>
              @error('documents')
                <div class="text-danger mt-2">{{ $message }}</div>
              @enderror
            </div>


            <div class="col-12 form-control-validation">
              <button type="submit" name="submitButton"
                class="btn btn-primary">{{ isset($society) ? 'Update' : 'Save' }} Society</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
