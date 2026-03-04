@php
  use Illuminate\Support\Facades\Route;
@endphp

@extends('layouts.layoutMaster')

@section('title', 'User View')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/flatpickr/flatpickr.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/flatpickr/flatpickr.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js'])
@endsection

<!-- Page Scripts -->
@section('page-script')

  @vite(['resources/assets/js/form-validation.js', 'resources/assets/js/app-access-roles.js'])
@endsection

@section('content')
  <div class="row">
    <div class="col-xl-4 col-lg-5">
      <div class="card mb-6">
        <div class="card-body pt-12">
          <div class="user-avatar-section">
            <div class=" d-flex align-items-center flex-column">
              <img class="img-fluid rounded mb-4"
                src="{{ $user->attachment?->link ? asset('storage/' . $user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                height="120" width="120">
              <div class="user-info text-center">
                <h5 class="mb-1 fw-bolder">{{ $user->first_name }} {{ $user->last_name }}</h5>
                <span class="badge bg-label-secondary mb-5">{{ $user->email }}</span>
              </div>
            </div>
          </div>
          <h5 class="pb-4 mb-4 border-bottom fw-bolder mt-5 ">User Details</h5>
          <div class="info-container">
            <ul class="list-unstyled mb-6">
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">First Name : </span>
                <span>{{ $user->first_name }} </span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Last Name:</span>
                <span>{{ $user->last_name }}</span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Role:</span>
                <span>{{ $user->roles->pluck('name')->implode(', ') }}</span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Country:</span>
                <span>{{ $user->country }}</span>
              </li>
              <li class="mb-2 ">
                <span class="h6 fw-bolder pe-2">Gender:</span>
                <span>{{ $user->gender }}</span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Marital Status:</span>
                <span>{{ $user->marital_status }}</span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Date Of Birth:</span>
                <span>{{ $user->dob }}</span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Profession:</span>
                <span>{{ $user->profession }}</span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Contact:</span>
                <span>{{ $user->contact }}</span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Emergency Contact:</span>
                <span>{{ $user->emergency_contact }}</span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Present Address:</span>
                <span>{{ $user->present_address }}</span>
              </li>
              <li class="mb-2">
                <span class="h6 fw-bolder pe-2">Permanent Address:</span>
                <span>{{ $user->permanent_address }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-8 col-lg-7">
      <ul class="nav nav-pills mb-6" role="tablist">
        <li class="nav-item">
          <a class="nav-link  active" data-bs-toggle="tab" data-bs-target="#basic" type="button">
            Basic Info
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link " data-bs-toggle="tab" data-bs-target="#security" type="button">
            Security
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link " data-bs-toggle="tab" data-bs-target="#other" type="button">
            Other
          </a>
        </li>
        @if ($slug !== 'society_owners' || !$isSelf)
          <li class="nav-item">
            <a class="nav-link " data-bs-toggle="tab" data-bs-target="#roles_permissions" type="button">
              Role & Permissions
            </a>
          </li>
        @endif
      </ul>
      {{-- Session Messages --}}
      @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif
      {{-- Error Message (for general errors) --}}
      @if (session('error'))
        <div class="alert alert-danger">
          {{ session('error') }}
        </div>
      @endif

      <div class="tab-content p-0">
        {{-- basic information  --}}
        <div class="tab-pane fade show active" id="basic">
          <div class="card">
            <h5 class="card-header fw-bold">Basic Information</h5>
            <div class="card-body">
              <form method="POST" action="{{ route('user.storeOrUpdate', [$slug, $user->uuid]) }}"
                enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="section" value="basic">
                <div class="d-flex align-items-center flex-column position-relative mb-4">
                  <img id="userAvatarPreview" class="img-fluid rounded-circle mb-2 cursor-pointer"
                    src="{{ $user->attachment?->link ? asset('storage/' . $user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                    height="70" width="70" title="Click to change avatar"
                    onclick="document.getElementById('avatarInput').click();">
                  <!-- Pencil icon -->
                  <span class="position-absolute bottom-0 ms-6 bg-white rounded-circle p-1 cursor-pointer"
                    style="transform: translate(25%, 25%);" onclick="document.getElementById('avatarInput').click();">
                    <i class="fa-solid fa-pencil text-primary"></i>
                  </span>
                  <input type="file" id="avatarInput" name="picture" class="d-none" accept="image/*"
                    onchange="previewAvatar(event)">
                </div>

                <div class="row mt-5">
                  <div class="col-md-12 form-control-validation mb-2">
                    <label class="form-label fw-bolder required mb-2" for="last_name">First Name</label>

                    <input type="text" id="first_name" name="first_name"
                      class="form-control @error('first_name') is-invalid @enderror" required
                      value="{{ old('first_name', $user->first_name ?? '') }}">
                    @error('first_name')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-12 form-control-validation mb-2">
                    <span>
                      <label class="form-label fw-bolder  mb-2" for="last_name">Last Name</label>
                      <input type="text" id="last_name"
                        class="form-control @error('last_name') is-invalid @enderror" name="last_name"
                        value="{{ old('last_name', $user->last_name ?? '') }} " />
                      @error('last_name')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                      @enderror
                    </span>
                  </div>
                  <div class="col-md-12 form-control-validation mb-5">
                    <label class="form-label fw-bolder required mb-2" for="contact">Contact</label>
                    <input type="tel" id="contact"
                      class="form-control phone-input @error('contact') is-invalid @enderror" name="contact" Required
                      placeholder="0300 1234567" value="{{ old('contact', $user->contact ?? '') }}" />
                    @error('contact')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>
                  <div class="col-md-6">
                    <button class="btn btn-primary mt-3">Update Basic</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="security">
          <!-- Change Password -->
          <div class="card">
            <h5 class="card-header fw-bold">Change Password</h5>
            <div class="card-body">
              <form method="POST" action="{{ route('user.storeOrUpdate', [$slug, $user->uuid]) }}">
                @csrf
                <input type="hidden" name="section" value="security">
                <div class="alert alert-warning alert-dismissible" role="alert">
                  <h5 class="alert-heading mb-1">Ensure that these requirements are met</h5>
                  <span>Minimum 8 characters long, uppercase & symbol</span>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <div class="row gx-6">
                  <div class="col-md-6 form-control-validation">
                    <div class="form-password-toggle">
                      <label class="form-label fw-bolder required" for="password">Password</label>
                      <div class="input-group input-group-merge">
                        <input class="form-control @error('password') is-invalid @enderror" type="password"
                          id="password" name="password" placeholder="••••••••••••"
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
                  <div class="col-md-6 form-control-validation mb-5">
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

                  <div>
                    <button type="submit" class="btn btn-primary mt-3">Change Password</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="other">
          <!-- Change Password -->
          <div class="card ">
            <h5 class="card-header fw-bolder">Other Information</h5>
            <div class="card-body">
              <form method="POST" action="{{ route('user.storeOrUpdate', [$slug, $user->uuid]) }}">
                @csrf
                <input type="hidden" name="section" value="other">
                <div class="alert alert-warning alert-dismissible" role="alert">
                  <h5 class="alert-heading mb-1 fw-bolder">Ensure that these requirements are met:</h5>
                  <p class="mb-1">CNIC or Passport number must be unique.</p>
                  <p class="mb-1">Present and Permanent address must be more than 15 characters.</p>
                  <p>All fields must be filled.</p>


                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <div class="row gx-6">
                  <div class="col-md-6 form-control-validation mb-2">
                    <label class="form-label fw-bolder required" for="country">Country</label>
                    <select id="country" name="country"
                      class="form-select select2 @error('country') is-invalid @enderror" Required
                      data-allow-clear="true">
                      <option value="">Select Your Country
                      </option>
                      <option value="Pakistan" {{ old('country', $user->country) == 'Pakistan' ? 'selected' : '' }}>
                        Pakistan</option>
                      <option value="Australia" {{ old('country', $user->country) == 'Australia' ? 'selected' : '' }}>
                        Australia</option>
                      <option value="Bangladesh" {{ old('country', $user->country) == 'Bangladesh' ? 'selected' : '' }}>
                        Bangladesh</option>
                      <option value="Belarus" {{ old('country', $user->country) == 'Belarus' ? 'selected' : '' }}>
                        Belarus
                      </option>
                      <option value="Brazil" {{ old('country', $user->country) == 'Brazil' ? 'selected' : '' }}>Brazil
                      </option>
                      <option value="Canada" {{ old('country', $user->country) == 'Canada' ? 'selected' : '' }}>Canada
                      </option>
                      <option value="China" {{ old('country', $user->country) == 'China' ? 'selected' : '' }}>China
                      </option>
                      <option value="France" {{ old('country', $user->country) == 'France' ? 'selected' : '' }}>France
                      </option>
                      <option value="Germany" {{ old('country', $user->country) == 'Germany' ? 'selected' : '' }}>
                        Germany
                      </option>
                      <option value="India" {{ old('country', $user->country) == 'India' ? 'selected' : '' }}>India
                      </option>
                      <option value="Indonesia" {{ old('country', $user->country) == 'Indonesia' ? 'selected' : '' }}>
                        Indonesia</option>
                      <option value="Israel" {{ old('country', $user->country) == 'Israel' ? 'selected' : '' }}>Israel
                      </option>
                      <option value="Italy" {{ old('country', $user->country) == 'Italy' ? 'selected' : '' }}>Italy
                      </option>
                      <option value="Japan" {{ old('country', $user->country) == 'Japan' ? 'selected' : '' }}>Japan
                      </option>
                      <option value="Korea" {{ old('country', $user->country) == 'Korea' ? 'selected' : '' }}>Korea,
                        Republic of</option>
                      <option value="Mexico" {{ old('country', $user->country) == 'Mexico' ? 'selected' : '' }}>Mexico
                      </option>
                      <option value="Philippines"
                        {{ old('country', $user->country) == 'Philippines' ? 'selected' : '' }}>
                        Philippines</option>
                      <option value="Russia" {{ old('country') }}>Russian
                        Federation</option>
                      <option value="South Africa"
                        {{ old('country', $user->country) == 'South Africa' ? 'selected' : '' }}>South Africa
                      </option>
                      <option value="Thailand" {{ old('country', $user->country) == 'Thailand' ? 'selected' : '' }}>
                        Thailand</option>
                      <option value="Turkey"{{ old('country', $user->country) == 'Turkey' ? 'selected' : '' }}>Turkey
                      </option>
                      <option value="Ukraine" {{ old('country', $user->country) == 'Ukraine' ? 'selected' : '' }}>
                        Ukraine</option>
                      <option value="United Arab Emirates"
                        {{ old('country', $user->country) == 'United Arab Emirates' ? 'selected' : '' }}>
                        United Arab Emirates</option>
                      <option value="United Kingdom"
                        {{ old('country', $user->country) == 'United Kingdom' ? 'selected' : '' }}>United Kingdom
                      </option>
                      <option value="United States"
                        {{ old('country', $user->country) == 'United States' ? 'selected' : '' }}>United States
                      </option>
                    </select>
                    @error('country')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-6 form-control-validation mb-2">
                    <label class="form-label fw-bolder required" for="dob">DOB</label>
                    <input type="text" class="form-control flatpickr-validation @error('dob') is-invalid @enderror"
                      name="dob" id="dob" placeholder="Select your date of birth"
                      data-default-date="{{ old('dob', $user->dob) }}" required />

                    @error('dob')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>
                  <div class="col-md-6 form-control-validation mb-2">
                    <label class="form-label fw-bolder required" for="cnic_passport">CNIC/Passport</label>
                    <input class="form-control @error('cnic_passport') is-invalid @enderror" type="text" Required
                      id="cnic_passport" name="cnic_passport" placeholder="13503-1235405-3"
                      value="{{ old('cnic_passport', $user->cnic_passport) }}" />
                    @error('cnic_passport')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-6 form-control-validation mb-2">
                    <label class="form-label fw-bolder required " for="profession">Profession</label>
                    <input class="form-control typeahead @error('profession') is-invalid @enderror" type="text"
                      Required id="profession" name="profession" autocomplete="off" placeholder="Software Engineer"
                      value="{{ old('profession', $user->profession ?? '') }}" />
                    @error('profession')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>
                  <div class="col-md-6 form-control-validation mb-2">
                    <label class="form-label fw-bolder required" @error('gender') is-invalid @enderror
                      Required>Gender</label>
                    <div class="d-flex">
                      <div class="form-check custom">
                        <input type="radio" name="gender" value="male" id="male" class="form-check-input"
                          {{ old('gender', $user->gender) == 'male' ? 'checked' : '' }}>
                        <label class="form-check-label pe-5" for="male">Male</label>
                      </div>
                      <div class="form-check custom">
                        <input type="radio" id="female" name="gender" class="form-check-input" value="female"
                          {{ old('gender', $user->gender) == 'female' ? 'checked' : '' }} />
                        <label class="form-check-label pe-5" for="female">Female</label>
                      </div>
                      <div class="form-check custom">
                        <input type="radio" id="other" name="gender" class="form-check-input" value="other"
                          {{ old('gender', $user->gender) == 'other' ? 'checked' : '' }} />
                        <label class="form-check-label" for="other">Other</label>
                      </div>
                    </div>
                    @error('gender')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-6 form-control-validation mb-2">
                    <label class="form-label fw-bolder required" Required>Marital Status</label>
                    <div class="d-flex">
                      <div class="form-check custom mb-2">
                        <input type="radio" id="married" name="marital_status" value="married"
                          class="form-check-input"
                          {{ old('marital_status', $user->marital_status) == 'married' ? 'checked' : '' }} />
                        <label class="form-check-label pe-5" for="married">Married</label>
                      </div>
                      <div class="form-check custom">
                        <input type="radio" id="un-married" name="marital_status" value="un-married"
                          class="form-check-input"
                          {{ old('marital_status', $user->marital_status) == 'un-married' ? 'checked' : '' }} />
                        <label class="form-check-label" for="un-married">Un-Married</label>
                      </div>
                    </div>
                    @error('marital_status')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-6 form-control-validation mb-2">
                    <label class="form-label fw-bolder required" for="emergency_contact">Emergency Contact</label>
                    <input type="tel" id="emergency_contact"
                      class="form-control phone-input @error('emergency_contact') is-invalid @enderror" Required
                      name="emergency_contact" placeholder="0300 1234567"
                      value="{{ old('emergency_contact', $user->emergency_contact ?? '') }}" />
                    <input type="hidden" name="contact_country">
                    @error('emergency_contact')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-6 form-control-validation mb-2">
                    <label class="form-label fw-bolder required" for="present_address">Present Address</label>
                    <textarea class="form-control @error('present_address') is-invalid @enderror" id="present_address" Required
                      placeholder="25-B, Street 12, Block C, Gulshan-e-Iqbal, Karachi, Sindh 75300, Pakistan" name="present_address"
                      rows="1">{{ old('present_address', $user->present_address) }}</textarea>
                    @error('present_address')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>

                  <div class="col-md-12 form-control-validation mb-5">
                    <label class="form-label fw-bolder required" for="permanent_address">Permanent Address</label>
                    <textarea class="form-control @error('permanent_address') is-invalid @enderror" id="permanent_address" Required
                      placeholder="25-B, Street 12, Block C, Gulshan-e-Iqbal, Karachi, Sindh 75300, Pakistan" name="permanent_address"
                      rows="1">{{ old('permanent_address', $user->permanent_address) }}</textarea>
                    @error('permanent_address')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>
                  <div>
                    <button type="submit" class="btn btn-primary mt-3">Update Other</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        {{-- @if ($slug != 'society_owners') --}}
        <div class="tab-pane fade" id="roles_permissions">
          <form method="POST" action="{{ route('user.storeOrUpdate', [$slug, $user->uuid]) }}">
            @csrf
            <input type="hidden" name="section" value="roles">

            <div class="card">
              <h5 class="card-header fw-bold">User Roles</h5>
              <div class="card-body">
                <div class="row mb-4">
                  @foreach ($roles as $role)
                    <div class="col-md-4 mb-2">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" id="role_{{ $role->id }}" name="role"
                          value="{{ $role->name }}" {{ $user->roles->contains('id', $role->id) ? 'checked' : '' }}>
                        <label class="form-check-label" for="role_{{ $role->id }}">
                          {{ ucfirst($role->name) }}
                        </label>
                      </div>
                    </div>
                  @endforeach
                </div>
                <h6 class="mb-3 fw-bolder">Permissions (Assigned Role)</h6>

                @if ($assignedRole)
                  <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <div class="border-start border-4 border-primary ps-3">
                        <h6 class="fw-bolder mb-0">{{ ucfirst($assignedRole->name) }}</h6>
                        <small class="text-muted">Permissions for assigned role</small>
                      </div>
                      <button type="button" class="btn btn-sm btn-outline-primary edit-role"
                        data-id="{{ $assignedRole->id }}" data-name="{{ $assignedRole->name }}"
                        data-permissions='@json($assignedRole->permissions->pluck('name'))'>
                        <i class="fa-solid fa-pen-to-square pe-1"></i>Change Permissions
                      </button>
                    </div>
                    <div class="row">
                      @forelse ($assignedRole->permissions as $permission)
                        <div class="col-md-4 mb-1">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked disabled>
                            <label class="form-check-label">
                              {{ $permission->name }}
                            </label>
                          </div>
                        </div>
                      @empty
                        <div class="col-12">
                          <span class="text-muted fst-italic">No permissions assigned</span>
                        </div>
                      @endforelse
                    </div>
                  </div>
                @else
                  <span class="text-muted fst-italic">No role assigned to this user</span>
                @endif
                <button type="submit" class="btn btn-primary mt-3">Update Roles</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>
  @include('_partials._modals.roles_model')

@endsection
