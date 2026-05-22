@extends('layouts/layoutMaster')

@section('title', 'Block details')


@section('content')

  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">@if(isset($society) && $user_type)
          Society Management
        @else
          Property Management
        @endif</p>
      <h4 class="mb-1">Block/Sector Details</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          @if(isset($society))
            <li class="breadcrumb-item">
              <a href="{{ route('societies.index', $user_type) }}" class="text-dark opacity-75 text-decoration-none">Societies</a>
            </li>
            <li class="breadcrumb-item">
              <a href="{{ route('societies.show', [$user_type, $society->uuid]) }}"
                 class="text-dark opacity-75 text-decoration-none">Society</a>
            </li>
          @else
            <li class="breadcrumb-item">
              <a href="{{ route('blocks.index', "society_blocks") }}">Blocks</a>
            </li>
          @endif
          <li class="breadcrumb-item active">Block details</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-building-estate text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
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


  {{-- IMPORT PROGRESS BAR (hidden by default) --}}
  <div id="import_progress_wrapper" class="card mb-4 d-none">
    <div class="card-body py-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-2">
          <div class="spinner-border spinner-border-sm text-primary" id="import_spinner" role="status"></div>
          <span class="fw-semibold text-dark" id="import_status_text">Importing properties...</span>
        </div>
        <span class="badge bg-label-primary" id="import_percent_badge">0%</span>
      </div>
      <div class="progress" style="height: 10px; border-radius: 8px;">
        <div
          id="import_progress_bar"
          class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
          role="progressbar"
          style="width: 0%;"
          aria-valuenow="0"
          aria-valuemin="0"
          aria-valuemax="100">
        </div>
      </div>
      <p class="text-muted small mt-2 mb-0" id="import_sub_text">Please wait while your properties are being
        imported.</p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between ">
      <h5 class="mb-1 fw-semibold ">
        {{ ucfirst($block->name) }} — <span
          class="ms-1 badge bg-label-secondary"> {{  ucfirst($block->society->name) }} </span>
      </h5>
      @can('create_property')
        <div class="d-flex gap-2">

          <button class="btn btn-outline-secondary" onclick="showModel()">
            <i class="fa-solid fa-download me-1 text-primary"></i> Import
          </button>

          <a href="{{ route('property.create', ['block_uuid' => $block->uuid]) }}"
             class="btn btn-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
            <i class="ti tabler-plus"></i>
            Add Property
          </a>
        </div>
      @endcan
    </div>
    <hr class="mt-0 mb-3">
    <div class="card-body">
      <form method="Get" action="{{ route('block.view', $block->uuid) }}" id="filter_form" class="fiter_form">
        <div class="row g-4">

          <div class="col-12 col-md-4 mt-0">
            <label class="form-label text-uppercase fw-semibold">Search</label>
            <input type="text" class="form-control" name="search_content" id="search_content"
                   value="{{ request('search_content') }}" placeholder="Search by name or property no...">
          </div>


          <div class="col-12 col-md-4 mt-0">
            <label class="form-label text-uppercase fw-semibold"> Category</label>
            <select class="form-select" name="category" id="category"
                    onchange="document.getElementById('filter_form').submit()">
              <option value="">All</option>
              @foreach($property_categories as $category)
                <option
                  value="{{$category}}" {{  request('category') == $category ? 'selected' : "" }} > {{$category}}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-4 mt-0">
            <label class="form-label text-uppercase fw-semibold"> Property Type</label>
            <select class="form-select" id="type" name="type"
                    onchange="document.getElementById('filter_form').submit()">
              <option value="">All</option>
              @foreach($property_types as $type)
                <option value="{{$type}}" {{  request('type') == $type ? 'selected' : "" }} > {{$type}}</option>
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
            $url = $property->attachment?->link
                      ? asset('storage/' . $property->attachment->link)
                      : asset('assets/img/my_images/dummy_property_image.png')
          @endphp
          <div class="position-relative">
            <img src="{{ $url }}" class="card-img-top property-img cursor-pointer" alt="Property Image"
                 title="Click to Preview image..."
                 onclick="showImage('{{$url}}')">
            <span class="badge bg-primary opacity-75 position-absolute top-0 start-0 m-2">
              {{ ucfirst($property->type) }}
            </span>

            <span class="badge bg-secondary opacity-75 position-absolute top-0 end-0 m-2">
              {{ ucfirst($property->category) }}
            </span>
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

          @php
            $url = isset($society) && $user_type
                ? route('society.property.details', [$user_type, $society->uuid,$property->uuid])
                : route('property.details', $property->uuid);
          @endphp

          <div class="card-footer bg-white border-0 d-flex justify-content-between">
            <a href="{{ $url }}"
               class="btn btn-sm btn-outline-primary w-100">
              View Details
            </a>
          </div>

        </div>
      </div>

    @empty
      <!-- Empty State -->
      <div class="card p-4">
        <div class="text-center py-5">
          <i class="ti tabler-building-skyscraper fs-1 not_found_icon"> </i>
          <h6 class="text-muted">No properties found</h6>
        </div>
      </div>

    @endforelse
  </div>

  @if ($total_properties > $total_skip)
    <div id="load_more" class="text-center">
      <button class="btn btn-primary" type="button" onclick="loadMore(event)">
        <span class="spinner-border me-1 d-none" role="status" aria-hidden="true" id="spinner"></span>
        <span id="load_more_text"> Load More </span>
      </button>
    </div>
  @endif


  @include('_partials._modals.lightbox_model')
  @include('_partials._modals.import_properties')
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

    .import_errors {
      max-height: 150px;
      overflow-y: auto;
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
        url: "{{ route('block.view', $block->uuid)  }}",
        type: 'Get',
        data: data,
        success: function(response) {
          console.log(response);
          skip = response.total_skip;
          response.properties.forEach(function(property) {
            const date = new Date(property.updated_at);
            const formatted = date.toLocaleString('en-US', {
              month: 'long',
              day: '2-digit',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
              hour12: true
            });

            var image_url = property.attachment ? "{{ asset('storage/') }}/" + property.attachment.link :
              "{{ asset('assets/img/my_images/dummy_property_image.png') }}";
            let prop_details_url = "{{ isset($society) && $user_type
    ? route('society.property.details', [$user_type, $society->uuid, '__UUID__'])
    : route('property.details', '__UUID__') }}";

            prop_details_url = prop_details_url.replace('__UUID__', property.uuid);
            $('#property_cards').append(`
           <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
           <div class="card h-100 border-0 shadow-sm property-card">
           <div class="position-relative" >
             <img src="${image_url}" class="card-img-top property-img cursor-pointer" alt="Property Image" title="Click to Preview image..."
            onclick="showImage('${image_url}')">
            <span class="badge bg-primary opacity-75 position-absolute top-0 start-0 m-2">
              ${property.type}
            </span>

            <span class="badge bg-secondary opacity-75 position-absolute top-0 end-0 m-2">
              ${property.category}
            </span>
          </div>

          <div class="card-body p-4">
            <h6 class="fw-bold mb-1 text-truncate text-uppercase cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="tooltip-secondary" data-bs-original-title="">
               ${property.name ? property.name : property.type}
            </h6>
            <p class="text-muted small mb-1">
              Property No: <span class="fw-semibold"> # ${property.property_no}</span>
            </p>
            <p class="small text-secondary mb-0">
              Last update: ${formatted}
            </p>
          </div>

          <!-- Footer -->
          <div class="card-footer bg-white border-0 d-flex justify-content-between">
            <a href="${prop_details_url}" class="btn btn-sm btn-outline-primary w-100">
              View Details
            </a>
            </div>

            </div>
            </div>
           `);
            if (response.total_properties > response.total_skip) {
              load_more.style.display = 'block';
              spinner.classList.add('d-none');
              load_more_text.style.display = 'block';
              btn.disabled = false;
            } else {
              load_more.style.display = 'none';
            }

          });
        },
        error: function(xhr, status, error) {
          spinner.classList.add('d-none');
          btn.disabled = false;
          if (typeof Swal !== 'undefined') {
            Swal.fire('Error', 'Failed to load more societies', 'error');
          }
        }
      });


    }


    function showImage(image) {
      console.log(image);
      const modal = new bootstrap.Modal(document.getElementById('lightboxModal'));
      const img = document.getElementById('lightboxImage');
      console.log(img);
      img.classList.remove('d-none');
      img.src = image;
      modal.show();
    }

    function showModel() {
      const modal = new bootstrap.Modal(document.getElementById('importPropertiesModal'));
      const file = document.getElementById('file');
      file.value = '';
      modal.show();
    }


    document.querySelector('#importPropertiesModal form').addEventListener('submit', function(e) {
      e.preventDefault();

      const form = this;
      const modalEl = document.getElementById('importPropertiesModal');
      (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();

      $.ajax({
        url: form.action,
        type: 'POST',
        data: new FormData(form),
        contentType: false,
        processData: false,
        success: function(res) {
          if (res.errors) {
            showImportErrors(res.errors);
          } else {
            startImportPolling();
          }
        },
        error: function(xhr) {
          // Laravel 422 validation (file/block_id rules)
          let res = xhr.responseJSON;
          if (res && res.errors) {
            var msgs = Object.values(res.errors).flat();
            showImportErrors(msgs);
          } else if (res && res.message) {
            showImportErrors([res.message]);
          } else {
            showImportErrors(['Import failed. Please try again.']);
          }
        }
      });
    });

    function showImportErrors(errors) {
      // Stop any running interval
      if (window._importInterval) {
        clearInterval(window._importInterval);
        window._importInterval = null;
      }


      document.getElementById('import_progress_wrapper').classList.add('d-none');

      const html = errors.map(e => `<li class="small">${e}</li>`).join('');
      const alert = `
    <div class="alert alert-danger alert-dismissible fade show" id="import_error_alert">
      <strong><i class="ti tabler-alert-circle me-1"></i>Import failed — ${errors.length} error(s):</strong>
      <hr class="my-1">
      <ul class="mb-0 mt-2 ps-3" style="max-height:150px;overflow-y:auto">${html}</ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;

      // Remove old alert if exists, then prepend new one
      const old = document.getElementById('import_error_alert');
      if (old) old.remove();
      document.querySelector('.card.mb-4').insertAdjacentHTML('beforebegin', alert);
    }

    function startImportPolling() {
      var wrapper = document.getElementById('import_progress_wrapper');
      var bar = document.getElementById('import_progress_bar');
      var badge = document.getElementById('import_percent_badge');
      var spinner = document.getElementById('import_spinner');
      var status = document.getElementById('import_status_text');

      wrapper.classList.remove('d-none');
      bar.style.width = '0%';
      badge.textContent = '0%';

      // Clear any previous interval
      if (window._importInterval) clearInterval(window._importInterval);

      window._importInterval = setInterval(function() {
        $.get("{{ route('properties.import_properties', $block->uuid) }}", function(res) {
          var p = parseInt(res.progress) || 0;
          bar.style.width = p + '%';
          badge.textContent = p + '%';

          if (p >= 100) {
            clearInterval(window._importInterval);
            window._importInterval = null;
            bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
            bar.classList.add('bg-success');
            spinner.classList.add('d-none');
            status.textContent = 'Import completed! Refreshing...';
            setTimeout(() => window.location.reload(), 2000);
          }
        });
      }, 1000);
    }
  </script>
@endpush
