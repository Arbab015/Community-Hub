@extends('layouts/layoutMaster')

@section('title', 'Add New User')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/flatpickr/flatpickr.scss', 'resources/assets/vendor/libs/typeahead-js/typeahead.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js', 'resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/flatpickr/flatpickr.js', 'resources/assets/vendor/libs/typeahead-js/typeahead.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js'])
@endsection

<!-- Page Scripts -->
@section('page-script')
  @vite(['resources/assets/js/form-validation.js'])
@endsection

@section('content')
  @php
    $page_name = str_replace('_', ' ', $slug);
    $page_name = ucwords($page_name);
    $societyMemberRole = optional($roles)->firstWhere('name', 'Society Member');
  @endphp
  <h4 class="mb-1">Create Users</h4>
  <nav aria-label="breadcrumb " class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('users.index', $slug) }}">{{ $page_name }}</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Create</li>
    </ol>
  </nav>

  <div class="row">
    <div class="col-12">
      <div class="card">
        <h4 class="card-header fw-bolder "> Add New User</h4>
        @if (session('success'))
          <div class="alert alert-success"> {{ session('success') }} </div>
        @endif
        @if (session('error'))
          <div class="alert alert-danger"> {{ session('error') }} </div>
        @endif

        <div class="card-body">
          <form method="post" id="form_validation" action='{{ route('user.storeOrUpdate', $slug) }}'
            enctype="multipart/form-data" class="row g-6 validation_form">
            @csrf
            <!-- Account Details -->
            <div class="col-12">
              <h6 class="fw-bolder">1. Account Details</h6>
              <hr class="mt-0" />
            </div>

            <div class="col-md-6 form-control-validation ">
              <label class="form-label fw-bolder required" for="first_name">First Name</label>
              <input type="text" id="first_name" class="form-control @error('first_name') is-invalid @enderror"
                required placeholder="John" name="first_name" value="{{ old('first_name') }}" />
              @error('first_name')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-6 form-control-validation ">
              <label class="form-label fw-bolder " for="last_name">Last Name</label>

              <input type="text" id="last_name" class="form-control @error('last_name') is-invalid @enderror"
                placeholder="Doe" name="last_name" value="{{ old('last_name') }}" />
              @error('last_name')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="email">Email</label>
              <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" Required
                name="email" placeholder="john.doe@example.com" value="{{ old('email') }}" />
              @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <div class="form-password-toggle">
                <label class="form-label fw-bolder required" for="password">Password</label>
                <div class="input-group input-group-merge">
                  <input class="form-control @error('password') is-invalid @enderror" type="password" id="password"
                    name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                    aria-describedby="multicol-password2" />
                  <span class="input-group-text cursor-pointer" id="multicol-password2">
                    <i class="icon-base ti tabler-eye-off"></i>
                  </span>
                </div>
                @error('password')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <div class="col-md-6 form-control-validation">
              <div class="form-password-toggle">
                <label class="form-label fw-bolder required" for="password_confirmation">Confirm Password</label>
                <div class="input-group input-group-merge">
                  <input class="form-control @error('password_confirmation') is-invalid @enderror" type="password"
                    id="password_confirmation" name="password_confirmation"
                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                    aria-describedby="multicol-confirm-password2" />
                  <span class="input-group-text cursor-pointer" id="multicol-confirm-password2">
                    <i class="icon-base ti tabler-eye-off"></i>
                  </span>
                </div>
                @error('password_confirmation')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <!-- Personal Info -->
            <div class="col-12">
              <h6 class="mt-2 fw-bolder">2. Personal Info</h6>
              <hr class="mt-0" />
            </div>
            @if ($slug != 'society_members')
              <div class="col-md-6 form-control-validation">
                <label for="role" class="col fw-bolder required">Roles:</label>
                <div>
                  <select class="form-select" @error('role') is-invalid @enderror Required
                    aria-label="Default select example" name="role">
                    <option value="" selected>Choose any role</option>
                    @foreach ($roles as $role)
                      <option value="{{ $role->id }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                        {{ $role->name }}
                      </option>
                    @endforeach
                  </select>
                  @error('role')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            @elseif($slug == 'society_members')
              <input type="hidden" name="role" value="35">
              <div class="col-md-6 form-control-validation">
                <label for="society" class="col fw-bolder required">Society</label>
                <div>
                  <select class="form-select" @error('society') is-invalid @enderror Required
                    aria-label="Default select example" name="society">
                    <option value="" selected>Choose any society</option>
                    @foreach ($societies as $society)
                      <option value="{{ $society->id }}" {{ old('society') == $society->id ? 'selected' : '' }}>
                        {{ $society->name }}
                      </option>
                    @endforeach
                  </select>
                  @error('society')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            @endif

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="dob">DOB</label>
              <input type="text" class="form-control flatpickr-validation @error('dob') is-invalid @enderror"
                name="dob" id="dob" placeholder="Select your date of birth"
                data-default-date="{{ old('dob') }}" required />

              @error('dob')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="country">Country</label>
              <select id="country" name="country" class="form-select select2 @error('country') is-invalid @enderror"
                Required data-allow-clear="true">
                <option value="">Select Your Country
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
                <option value="Canada" {{ old('country') == 'Canada' ? 'selected' : '' }}>Canadas
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
                <option value="Korea" {{ old('country') == 'Korea' ? 'selected' : '' }}>Korea</option>
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
                <option value="Turkey"{{ old('country') == 'Turkey' ? 'selected' : '' }}>Turkey
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
              <label class="form-label fw-bolder required" for="cnic_passport">CNIC/Passport</label>
              <input class="form-control @error('cnic_passport') is-invalid @enderror" type="text" Required
                id="cnic_passport" name="cnic_passport" placeholder="13503-1235405-3"
                value="{{ old('cnic_passport') }}" />
              @error('cnic_passport')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" Required>Gender</label>
              <div class="d-flex">
                <div class="form-check custom mb-2">
                  <input type="radio" id="male" name="gender" class="form-check-input" value="male"
                    {{ old('gender', $user->gender ?? '') == 'male' ? 'checked' : '' }} />
                  <label class="form-check-label pe-5" for="male">Male</label>
                </div>
                <div class="form-check custom">
                  <input type="radio" id="female" name="gender" class="form-check-input" value="female"
                    {{ old('gender', $user->gender ?? '') == 'female' ? 'checked' : '' }} />
                  <label class="form-check-label pe-5" for="female">Female</label>
                </div>
                <div class="form-check custom">
                  <input type="radio" id="other" name="gender" class="form-check-input" value="other"
                    {{ old('gender', $user->gender ?? '') == 'other' ? 'checked' : '' }} />
                  <label class="form-check-label" for="other">Other</label>
                </div>
              </div>
              @error('gender')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" Required>Marital Status</label>
              <div class="d-flex">
                <div class="form-check custom mb-2">
                  <input type="radio" id="married" name="marital_status" value="married" class="form-check-input"
                    {{ old('marital_status', $user->marital_status ?? '') == 'married' ? 'checked' : '' }} />
                  <label class="form-check-label pe-5" for="married">Married</label>
                </div>
                <div class="form-check custom">
                  <input type="radio" id="un-married" name="marital_status" value="un-married"
                    class="form-check-input"
                    {{ old('marital_status', $user->marital_status ?? '') == 'un-married' ? 'checked' : '' }} />
                  <label class="form-check-label" for="un-married">Un-Married</label>
                </div>
              </div>
              @error('marital_status')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required " for="profession">Profession</label>
              <input class="form-control typeahead @error('profession') is-invalid @enderror" type="text" Required
                id="profession" name="profession" autocomplete="off" placeholder="Software Engineer"
                value="{{ old('profession') }}" />
              @error('profession')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label for="picture" class="form-label fw-bolder required">Profile Picture</label>
              <input class="form-control @error('picture') is-invalid @enderror" type="file" id="picture"
                {{ !isset($user) ? 'Required' : '' }} name="picture" />
              @error('picture')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="contact">Contact</label>
              <input type="tel" id="contact"
                class="form-control phone-input @error('contact') is-invalid @enderror" name="contact" Required
                placeholder="0300 1234567" value="{{ old('contact') }}" />
              @error('contact')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="emergency_contact">Emergency Contact</label>
              <input type="tel" id="emergency_contact"
                class="form-control phone-input @error('emergency_contact') is-invalid @enderror" Required
                name="emergency_contact" placeholder="0300 1234567" value="{{ old('emergency_contact') }}" />
              @error('emergency_contact')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="present_address">Present Address</label>
              <textarea class="form-control @error('present_address') is-invalid @enderror" id="present_address" Required
                placeholder="25-B, Street 12, Block C, Gulshan-e-Iqbal, Karachi, Sindh 75300, Pakistan" name="present_address"
                rows="1">{{ old('present_address') }}</textarea>
              @error('present_address')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="permanent_address">Permanent Address</label>
              <textarea class="form-control @error('permanent_address') is-invalid @enderror" id="permanent_address" Required
                placeholder="25-B, Street 12, Block C, Gulshan-e-Iqbal, Karachi, Sindh 75300, Pakistan" name="permanent_address"
                rows="1">{{ old('permanent_address') }}</textarea>
              @error('permanent_address')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-12 form-control-validation">
              <button type="submit" name="submitButton" class="btn btn-primary"> Save User</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
