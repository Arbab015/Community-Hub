@extends('layouts/layoutMaster')

@section('title', 'Add New User')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/flatpickr/flatpickr.scss', 'resources/assets/vendor/libs/typeahead-js/typeahead.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js', 'resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/flatpickr/flatpickr.js', 'resources/assets/vendor/libs/typeahead-js/typeahead.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/form-validation.js'])
@endsection

@section('content')
  @php
    $page_name = str_replace('_', ' ', $slug);
    $page_name = ucwords($page_name);
  @endphp

  {{-- PAGE HEADER --}}
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Users Management</p>
      <h3 class="text-dark fw-bold mb-2">Add New User</h3>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="{{ route('dashboard.analytics') }}"
                                         class="text-dark opacity-75 text-decoration-none">Home</a></li>
          <li class="breadcrumb-item"><a href="{{ route('users.index', $slug) }}"
                                         class="text-dark opacity-75 text-decoration-none">{{ $page_name }}</a></li>
          <li class="breadcrumb-item active text-dark opacity-50">Create</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-users text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
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

  <div class="card border-0 shadow-sm rounded-3">
    <form method="post" id="form_validation" action='{{ route('user.storeOrUpdate', $slug) }}'
          enctype="multipart/form-data" class="validation_form">
      @csrf

      {{-- SECTION 01 — ACCOUNT DETAILS --}}
      <div class="card-body border-bottom p-4">
        <div class="d-flex align-items-center gap-2 mb-4">
          <span
            class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">01</span>
          <h5 class="mb-0 fw-bold">Account Details</h5>
        </div>

        <div class="row g-4">
          <div class="col-md-6">
            <label for="first_name" class="form-label fw-semibold small text-uppercase text-muted required">First
              Name</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-user text-muted"></i></span>
              <input type="text" id="first_name" name="first_name" required
                     value="{{ old('first_name') }}"
                     class="form-control border-start-0 @error('first_name') is-invalid @enderror"
                     placeholder="John">
            </div>
            @error('first_name')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label for="last_name" class="form-label fw-semibold small text-uppercase text-muted">Last Name</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-user text-muted"></i></span>
              <input type="text" id="last_name" name="last_name"
                     value="{{ old('last_name') }}"
                     class="form-control border-start-0 @error('last_name') is-invalid @enderror"
                     placeholder="Doe">
            </div>
            @error('last_name')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label for="email" class="form-label fw-semibold small text-uppercase text-muted required">Email</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-mail text-muted"></i></span>
              <input type="email" id="email" name="email" required
                     value="{{ old('email') }}"
                     class="form-control border-start-0 @error('email') is-invalid @enderror"
                     placeholder="john.doe@example.com">
            </div>
            @error('email')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          {{-- Password --}}
          <div class="col-md-6">
            <label for="password"
                   class="form-label fw-semibold small text-uppercase text-muted required">Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-lock text-muted"></i></span>
              <input type="password" id="password" name="password"
                     class="form-control border-start-0 @error('password') is-invalid @enderror"
                     placeholder="············">
            </div>
            @error('password')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          {{-- Confirm Password --}}
          <div class="col-md-6">
            <label for="password_confirmation" class="form-label fw-semibold small text-uppercase text-muted required">Confirm
              Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i
                  class="ti tabler-lock-check text-muted"></i></span>
              <input type="password" id="password_confirmation" name="password_confirmation"
                     class="form-control border-start-0 @error('password_confirmation') is-invalid @enderror"
                     placeholder="············">
            </div>
            @error('password_confirmation')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>
        </div>
      </div>

      {{-- SECTION 02 — PERSONAL INFO --}}
      <div class="card-body border-bottom p-4">
        <div class="d-flex align-items-center gap-2 mb-4">
          <span
            class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">02</span>
          <h5 class="mb-0 fw-bold">Personal Info</h5>
        </div>

        <div class="row g-4">

          {{-- Role or Society --}}
          @if ($slug != 'society_members')
            <div class="col-md-6">
              <label for="role" class="form-label fw-semibold small text-uppercase text-muted required">Role</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="ti tabler-shield text-muted"></i></span>
                <select id="role" name="role" required
                        class="form-select border-start-0 @error('role') is-invalid @enderror">
                  <option value="">Choose any role</option>
                  @foreach ($roles as $role)
                    <option value="{{ $role->id }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                      {{ $role->name }}
                    </option>
                  @endforeach
                </select>
              </div>
              @error('role')
              <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
              @enderror
            </div>
          @else
            <input type="hidden" name="role" value="35">
            <div class="col-md-6">
              <label for="society"
                     class="form-label fw-semibold small text-uppercase text-muted required">Society</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i
                    class="ti tabler-building text-muted"></i></span>
                <select id="society" name="society" required
                        class="form-select border-start-0 @error('society') is-invalid @enderror">
                  <option value="">Choose any society</option>
                  @foreach ($societies as $society)
                    <option value="{{ $society->id }}" {{ old('society') == $society->id ? 'selected' : '' }}>
                      {{ $society->name }}
                    </option>
                  @endforeach
                </select>
              </div>
              @error('society')
              <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
              @enderror
            </div>
          @endif

          <div class="col-md-6">
            <label for="dob" class="form-label fw-semibold small text-uppercase text-muted required">Date of
              Birth</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-calendar text-muted"></i></span>
              <input type="text" id="dob" name="dob" required
                     class="form-control border-start-0 @error('dob') is-invalid @enderror"
                     placeholder="Select your date of birth"
                     data-default-date="{{ old('dob') }}">
            </div>
            @error('dob')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label for="country" class="form-label fw-semibold small text-uppercase text-muted required">Country</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-world text-muted"></i></span>
              <select id="country" name="country" required
                      class="form-select border-start-0 select2 @error('country') is-invalid @enderror"
                      data-allow-clear="true">
                <option value="">Select Your Country</option>
                @foreach(['Pakistan','Australia','Bangladesh','Belarus','Brazil','Canada','China','France','Germany','India','Indonesia','Israel','Italy','Japan','Korea','Mexico','Philippines','Russia','South Africa','Thailand','Turkey','Ukraine','United Arab Emirates','United Kingdom','United States'] as $c)
                  <option value="{{ $c }}" {{ old('country') == $c ? 'selected' : '' }}>{{ $c }}</option>
                @endforeach
              </select>
            </div>
            @error('country')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label for="cnic_passport" class="form-label fw-semibold small text-uppercase text-muted required">CNIC /
              Passport</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-id text-muted"></i></span>
              <input type="text" id="cnic_passport" name="cnic_passport" required
                     value="{{ old('cnic_passport') }}"
                     class="form-control border-start-0 @error('cnic_passport') is-invalid @enderror"
                     placeholder="13503-1235405-3">
            </div>
            @error('cnic_passport')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label for="profession"
                   class="form-label fw-semibold small text-uppercase text-muted required">Profession</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-briefcase text-muted"></i></span>
              <input type="text" id="profession" name="profession" required autocomplete="off"
                     value="{{ old('profession') }}"
                     class="form-control border-start-0 typeahead @error('profession') is-invalid @enderror"
                     placeholder="Software Engineer">
            </div>
            @error('profession')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label for="picture" class="form-label fw-semibold small text-uppercase text-muted required">Profile
              Picture</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-photo text-muted"></i></span>
              <input type="file" id="picture" name="picture" accept="image/*"
                     {{ !isset($user) ? 'required' : '' }}
                     class="form-control border-start-0 @error('picture') is-invalid @enderror">
            </div>
            @error('picture')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label for="contact" class="form-label fw-semibold small text-uppercase text-muted required">Contact</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-phone text-muted"></i></span>
              <input type="tel" id="contact" name="contact" required
                     value="{{ old('contact') }}"
                     class="form-control border-start-0 phone-input @error('contact') is-invalid @enderror"
                     placeholder="0300 1234567">
            </div>
            @error('contact')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label for="emergency_contact" class="form-label fw-semibold small text-uppercase text-muted required">Emergency
              Contact</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i
                  class="ti tabler-phone-call text-muted"></i></span>
              <input type="tel" id="emergency_contact" name="emergency_contact" required
                     value="{{ old('emergency_contact') }}"
                     class="form-control border-start-0 phone-input @error('emergency_contact') is-invalid @enderror"
                     placeholder="0300 1234567">
            </div>
            @error('emergency_contact')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          {{-- Gender --}}
          <div class="col-md-6">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Gender</label>
            <div class="d-flex gap-3">
              <label for="male" class="const_labels">
                <input type="radio" id="male" name="gender" value="male" hidden
                  {{ old('gender', $user->gender ?? '') == 'male' ? 'checked' : '' }}>
                <div class="d-flex align-items-center justify-content-center gap-2">
                  <i class="ti tabler-mars fs-4"></i>
                  <span class="small fw-semibold">Male</span>
                </div>
              </label>
              <label for="female" class="const_labels">
                <input type="radio" id="female" name="gender" value="female" hidden
                  {{ old('gender', $user->gender ?? '') == 'female' ? 'checked' : '' }}>
                <div class="d-flex align-items-center justify-content-center gap-2">
                  <i class="ti tabler-venus fs-4"></i>
                  <span class="small fw-semibold">Female</span>
                </div>
              </label>
              <label for="other" class="const_labels">
                <input type="radio" id="other" name="gender" value="other" hidden
                  {{ old('gender', $user->gender ?? '') == 'other' ? 'checked' : '' }}>
                <div class="d-flex align-items-center justify-content-center gap-2">
                  <i class="ti tabler-gender-bigender fs-4"></i>
                  <span class="small fw-semibold">Other</span>
                </div>
              </label>
            </div>
            @error('gender')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          {{-- Marital Status --}}
          <div class="col-md-6">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Marital Status</label>
            <div class="d-flex gap-3">
              <label for="married" class="const_labels">
                <input type="radio" id="married" name="marital_status" value="married" hidden
                  {{ old('marital_status', $user->marital_status ?? '') == 'married' ? 'checked' : '' }}>
                <div class="d-flex align-items-center justify-content-center gap-2">
                  <i class="ti tabler-heart fs-4"></i>
                  <span class="small fw-semibold">Married</span>
                </div>
              </label>
              <label for="un-married" class="const_labels">
                <input type="radio" id="un-married" name="marital_status" value="un-married" hidden
                  {{ old('marital_status', $user->marital_status ?? '') == 'un-married' ? 'checked' : '' }}>
                <div class="d-flex align-items-center justify-content-center gap-2">
                  <i class="ti tabler-heart-off fs-4"></i>
                  <span class="small fw-semibold">Un Married</span>
                </div>
              </label>
            </div>
            @error('marital_status')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          {{-- Addresses --}}
          <div class="col-md-6">
            <label for="present_address" class="form-label fw-semibold small text-uppercase text-muted required">Present
              Address</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-map-pin text-muted"></i></span>
              <textarea id="present_address" name="present_address" required rows="1"
                        class="form-control border-start-0 @error('present_address') is-invalid @enderror"
                        placeholder="25-B, Street 12, Block C, Karachi">{{ old('present_address') }}</textarea>
            </div>
            @error('present_address')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label for="permanent_address" class="form-label fw-semibold small text-uppercase text-muted required">Permanent
              Address</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-map-pins text-muted"></i></span>
              <textarea id="permanent_address" name="permanent_address" required rows="1"
                        class="form-control border-start-0 @error('permanent_address') is-invalid @enderror"
                        placeholder="25-B, Street 12, Block C, Karachi">{{ old('permanent_address') }}</textarea>
            </div>
            @error('permanent_address')
            <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
            @enderror
          </div>

        </div>
      </div>

      {{-- SUBMIT --}}
      <div class="card-body p-4 d-flex justify-content-end">
        <button type="submit" name="submitButton" class="btn btn-primary px-4 fw-bold">
          <i class="ti tabler-device-floppy me-1"></i> Save User
        </button>
      </div>

    </form>
  </div>

@endsection


@push('styles')
  <style>
    .const_labels {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border: 1px solid #d9dee3;
      border-radius: 0.375rem;
      cursor: pointer;
      transition: all 0.2s;
      background: #fff;
    }

    .const_labels:has(input:checked) {
      border-color: #7367f0;
      background: rgba(115, 103, 240, 0.08);
      color: #7367f0;
    }

    .const_labels:has(input:checked) i {
      color: #7367f0;
    }
  </style>
@endpush
