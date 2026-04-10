@extends('layouts/layoutMaster')

@section('title', 'Block details')
@section('content')
  <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Property Management</p>
  <h3 class="mb-1">Property Details</h3>
@php
$block = $property->block;
@endphp
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}" class="text-dark opacity-75 text-decoration-none">Home</a>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('blocks.index') }}" class="text-dark opacity-75 text-decoration-none">Blocks</a>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('blocks.view', $property->block->uuid) }}" class="text-dark opacity-75 text-decoration-none">Blocks details</a>
      </li>
      <li class="breadcrumb-item active text-dark opacity-50">Property details</li>
    </ol>
  </nav>
    </div>
    <i class="ti tabler-building-estate text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>


{{--  Property Details --}}
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




  @endsection
