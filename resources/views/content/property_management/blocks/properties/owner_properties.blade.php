@extends('layouts/layoutMaster')

@section('title', 'My Properties')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/spinkit/spinkit.scss', 'resources/assets/vendor/libs/notiflix/notiflix.scss'])
@endsection

@section('content')

  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <h4 class="mb-1">My Properties</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active">Properties</li>
        </ol>
      </nav>
    </div>
    <i
      class="ti tabler-building-skyscraper text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  @if (session('success'))
    <div class="alert alert-success">
      <i class="ti tabler-circle-check me-1"></i> {{ session('success') }}
    </div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger">
      <i class="ti tabler-alert-circle me-1"></i> {{ session('error') }}
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-body">
      <form method="Get" action="{{ route('owner.properties.show', Auth()->user()->uuid) }}" id="filter_form"
            class="fiter_form">
        <div class="row g-4">
          <div class="col-12 col-md-4 mt-0">
            <label class="form-label text-uppercase fw-semibold">Search</label>
            <input type="text" class="form-control" name="search_content" id="search_content"
                   value="{{ request('search_content') }}" placeholder="Search by name or property no...">
          </div>

          <div class="col-12 col-md-4 mt-0">
            <label class="form-label text-uppercase fw-semibold">Category</label>
            <select class="form-select" name="category" id="category"
                    onchange="document.getElementById('filter_form').submit()">
              <option value="">All</option>
              @foreach($property_categories as $category)
                <option
                  value="{{$category}}" {{ request('category') == $category ? 'selected' : "" }}>{{$category}}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-4 mt-0">
            <label class="form-label text-uppercase fw-semibold">Property Type</label>
            <select class="form-select" id="type" name="type"
                    onchange="document.getElementById('filter_form').submit()">
              <option value="">All</option>
              @foreach($property_types as $type)
                <option value="{{$type}}" {{ request('type') == $type ? 'selected' : "" }}>{{$type}}</option>
              @endforeach
            </select>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-4 mx-0 mb-4" id="property_cards">
    @forelse($properties as $property)
      <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
        <div class="card h-100 border-0 shadow-sm property-card">
          @php
            $url = $property->image_link
                      ? asset('storage/' . $property->image_link)
                      : asset('assets/img/my_images/dummy_property_image.png')
          @endphp
          <div class="position-relative">
            <img src="{{ $url }}" class="card-img-top property-img cursor-pointer" alt="Property Image"
                 title="Click to Preview image..." onclick="showImage('{{$url}}')">
            <span class="badge bg-primary opacity-75 position-absolute top-0 start-0 m-2">
              {{ ucfirst($property->type) }}
            </span>
            <span class="badge bg-secondary opacity-75 position-absolute top-0 end-0 m-2">
              {{ ucfirst($property->category) }}
            </span>
            {{--            @if($property->source_type === 'unit')--}}
            {{--              <span class="badge bg-primary opacity-75 position-absolute bottom-0 start-0 m-2">Unit</span>--}}
            {{--            @endif--}}
          </div>

          <div class="card-body p-4">
            <h6 class="fw-bold mb-1 text-truncate text-uppercase cursor-pointer" data-bs-toggle="tooltip"
                data-bs-placement="bottom" data-bs-custom-class="tooltip-secondary"
                data-bs-original-title="{{ $property->name ? ucfirst($property->name) : ucfirst($property->type)}}">
              {{ $property->name ? ucfirst($property->name) : ucfirst($property->type)}}
            </h6>
            <p class="text-muted small mb-1">
              Property No: <span class="fw-semibold"> #{{ ucfirst($property->property_no) }}</span>
            </p>
            <p class="small text-secondary mb-0">
              Last update: {{ $property->updated_at->format('F d, Y h:i A') }}
            </p>
          </div>

          <div class="card-footer bg-white border-0 d-flex justify-content-between">
            <a href="{{ $property->details_url }}" class="btn btn-sm btn-outline-primary w-100">
              View Details
            </a>
          </div>
        </div>
      </div>
    @empty
      <div class="card p-4">
        <div class="text-center py-5">
          <i class="ti tabler-building-skyscraper fs-1 not_found_icon"></i>
          <h6 class="text-muted">No properties found</h6>
        </div>
      </div>
    @endforelse
  </div>

  @if ($total_properties > $total_skip)
    <div id="load_more" class="text-center">
      <button class="btn btn-sm btn-primary" type="button" onclick="loadMore(event)">
        <span class="spinner-border me-1 d-none" role="status" aria-hidden="true" id="spinner"></span>
        <span id="load_more_text"> Load More </span>
      </button>
    </div>
  @endif

  @include('_partials._modals.lightbox_model')
@endsection

@push('styles')
  <style>
    .property-card {
      transition: all 0.3s ease;
      border-radius: 12px;
      overflow: hidden;
    }

    .property-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    }

    .property-img {
      height: 180px;
      object-fit: cover;
    }
  </style>
@endpush

@push('scripts')
  <script>
    let searchTimer;
    document.getElementById('search_content').addEventListener('keyup', function() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        document.getElementById('filter_form').submit();
      }, 1000);
    });

    var skip = 4;

    function loadMore(event) {
      event.preventDefault();
      var load_more = document.getElementById('load_more');
      var spinner = document.getElementById('spinner');
      var load_more_text = document.getElementById('load_more_text');

      spinner.classList.remove('d-none');
      load_more_text.style.display = 'none';
      var btn = event.target;
      btn.disabled = true;

      var data = {
        skip: skip,
        search_content: document.getElementById('search_content').value,
        category: document.getElementById('category').value,
        type: document.getElementById('type').value
      };

      $.ajax({
        url: "{{ route('owner.properties.show', Auth()->user()->uuid) }}",
        type: 'GET', data: data,
        success: function(response) {
          skip = response.total_skip;
          response.properties.forEach(function(property) {
            const date = new Date(property.updated_at);
            const formatted = date.toLocaleString('en-US', {
              month: 'long', day: '2-digit', year: 'numeric',
              hour: '2-digit', minute: '2-digit', hour12: true
            });

            var image_url = property.image_link
              ? "{{ asset('storage/') }}/" + property.image_link
              : "{{ asset('assets/img/my_images/dummy_property_image.png') }}";

            $('#property_cards').append(`
            <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
              <div class="card h-100 border-0 shadow-sm property-card">
                <div class="position-relative">
                  <img src="${image_url}" class="card-img-top property-img cursor-pointer" alt="Property Image"
                       title="Click to Preview image..." onclick="showImage('${image_url}')">
                  <span class="badge bg-primary opacity-75 position-absolute top-0 start-0 m-2">${property.type}</span>
                  <span class="badge bg-secondary opacity-75 position-absolute top-0 end-0 m-2">${property.category}</span>
                </div>
                <div class="card-body p-4">
                  <h6 class="fw-bold mb-1 text-truncate text-uppercase cursor-pointer"
                      data-bs-toggle="tooltip" data-bs-placement="bottom"
                      data-bs-custom-class="tooltip-secondary" data-bs-original-title="">
                    ${property.name ? property.name : property.type}
                  </h6>
                  <p class="text-muted small mb-1">Property No: <span class="fw-semibold">#${property.property_no}</span></p>
                  <p class="small text-secondary mb-0">Last update: ${formatted}</p>
                </div>
                <div class="card-footer bg-white border-0 d-flex justify-content-between">
                  <a href="${property.details_url}" class="btn btn-sm btn-outline-primary w-100">View Details</a>
                </div>
              </div>
            </div>
          `);
          });

          if (response.total_properties > response.total_skip) {
            load_more.style.display = 'block';
          } else {
            load_more.style.display = 'none';
          }
          spinner.classList.add('d-none');
          load_more_text.style.display = 'block';
          btn.disabled = false;
        },
        error: function() {
          spinner.classList.add('d-none');
          btn.disabled = false;
          if (typeof Swal !== 'undefined') {
            Swal.fire('Error', 'Failed to load more properties', 'error');
          }
        }
      });
    }

    function showImage(image) {
      const modal = new bootstrap.Modal(document.getElementById('lightboxModal'));
      const img = document.getElementById('lightboxImage');
      img.classList.remove('d-none');
      img.src = image;
      modal.show();
    }
  </script>
@endpush
