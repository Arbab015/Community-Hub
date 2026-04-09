@extends('layouts/layoutMaster')

@section('title', 'Block details')
@section('content')
  <h4 class="mb-1">Block/Sector Details</h4>
  <nav aria-label="breadcrumb" class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('blocks.index') }}">Blocks</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Block details</li>
    </ol>
  </nav>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between py-5">
      <span class="">{{ $block->name }}</span>
      <a href="{{ route('property.create', ['block' => $block]) }}" class="btn btn-sm btn-outline-primary"> Add Property</a>
    </div>
  </div>

  <div class="row mb-12 g-6">
  <div class="col-md">
    <div class="card">
      <div class="row g-0">
        <div class="col-md-4">
          <img class="card-img card-img-left" src="{{ asset('assets/img/elements/12.png') }}" alt="Card image" />
        </div>
        <div class="col-md-8">
          <div class="card-body">
            <h5 class="card-title">Card title</h5>
            <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional
              content. This content is a little bit longer.</p>
            <p class="card-text"><small class="text-body-secondary">Last updated 3 mins ago</small></p>
          </div>

          <!-- Footer -->
          <div class="card-footer bg-white border-0 d-flex justify-content-between">
            <a href="{{ route('property.details', $property->uuid) }}" class="btn btn-sm btn-outline-primary w-100">
              View Details
            </a>
          </div>

        </div>
      </div>

    @empty
      <!-- Empty State -->
      <div class="card ">
        <div class="text-center py-5">
          <i class="ti tabler-building-skyscraper fs-1 not_found_icon"> </i>
          <h6 class="text-muted">No properties found</h6>
        </div>
      </div>

    @endforelse
  </div>

  @include('_partials._modals.lightbox_model')
@endsection

