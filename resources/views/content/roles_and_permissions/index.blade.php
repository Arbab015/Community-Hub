@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Roles - Apps')
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/app-access-roles.js'])
@endsection

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Roles Management</p>
      <h4 class="mb-1">
        Roles Cards
      </h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active"> Roles & Permissions</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-settings text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  {{-- Success Message --}}
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

  {{-- Validation Errors (for form validation failures) --}}
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <!-- Role cards -->
  <div class="row g-6">
    {{-- Add New Role Card --}}
    <div class="col-xl-4 col-lg-6 col-md-6">
      <div class="card h-100">
        <div class="row h-100">
          <div class="col-sm-9">
            <div class="card-body pe-0">
              <p class="">
                Add a new role for your system and assign permissons.<br>if it doesn't exist.
              </p>
              <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal"
                      id="addRoleBtn">
                Add New Role
              </button>
            </div>
          </div>
          <div class="col-sm-3">
            <div class="d-flex align-items-end h-100 justify-content-center mt-sm-0  add-new-role">
              <img src="{{ asset('assets/img/illustrations/add-new-roles.png') }}" class="img-fluid" alt="Image"
                   width="75" />
            </div>
          </div>
        </div>
      </div>
    </div>
    @foreach ($roles as $role)
      <div class="col-xl-4 col-lg-6 col-md-6">
        <div class="card h-100">
          <div class="card-body d-flex flex-column justify-content-between">

            <div class="d-flex justify-content-between align-items-center mb-4">
              <h6 class="fw-normal mb-0 text-body">
                Total {{ $role->users_count }} users
              </h6>
              <ul class="list-unstyled d-flex align-items-center avatar-group mb-0">
                @foreach ($role->users->take(4) as $user)
                  <li class="avatar pull-up">
                    <img
                      class="rounded-circle"
                      src="{{ $user->attachment ? asset('storage/' . $user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                      data-bs-toggle="tooltip"
                      data-bs-placement="top"
                      title="{{ $user->first_name }} {{ $user->last_name }}"
                      alt="{{ $user->name }}"
                    />
                  </li>
                @endforeach
                @if ($role->users_count > 4)
                  <li class="avatar">
                <span class="avatar-initial rounded-circle bg-label-primary">
                  +{{ $role->users_count - 4 }}
                </span>
                  </li>
                @endif
              </ul>
            </div>

            <div class="d-flex justify-content-between align-items-end">
              <div class="role-heading">
                <h5 class="mb-1 text-capitalize">{{ $role->name }}</h5>
                <a href="javascript:void(0)" class="edit-role"
                   data-id="{{ $role->id }}"
                   data-name="{{ $role->name }}"
                   data-permissions='@json($role->permissions->pluck('name'))'>
                  <span>Edit Role</span>
                </a>
                @if ($role->users_count < 1)
                  <a href="{{ route('roles.destroy', $role->id) }}" onclick="confirmDelete(event)">
                    <span class="ps-3 text-danger">Delete Role</span>
                  </a>
                @endif
              </div>
            </div>

          </div>
        </div>
      </div>
    @endforeach
  </div>

  @include('_partials._modals.roles_model')
@endsection
