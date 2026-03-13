@extends('layouts/layoutMaster')

@section('title', 'User Management')
{{-- Vendor Styles --}}
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/spinkit/spinkit.scss', 'resources/assets/vendor/libs/notiflix/notiflix.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss'])
@endsection
{{-- Vendor Scripts --}}
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/jquery/jquery.js', 'resources/assets/vendor/libs/notiflix/notiflix.js', 'resources/assets/vendor/libs/sortablejs/sortable.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js', 'resources/assets/vendor/libs/datatables-buttons/buttons.html5.js', 'resources/assets/vendor/libs/datatables-buttons/buttons.print.js', 'resources/assets/vendor/libs/jszip/jszip.js', 'resources/assets/vendor/libs/pdfmake/pdfmake.js', 'resources/assets/vendor/libs/pdfmake/vfs_fonts.js'])
@endsection
@section('page-script')
  @vite(['resources/assets/js/cards-actions.js'])
@endsection
@section('content')
  @php
    $colClass = $user_type == 'owner_societies' ? 'col-12 col-md-4 col-lg-4' : 'col-12 col-md-4 col-lg-3';
  @endphp
  <div class="d-flex justify-content-between text-align-center">
    <h4 class="mb-2">Societies</h4>
    @can('add_society')
      <div>
        <span class="d-flex justify-content-end">
          <a type="button" href="{{ route('society.create', $user_type) }}"
            class="btn btn-primary waves-effect waves-light btn-sm">
            Create New Society
          </a>
        </span>
      </div>
    @endcan
  </div>
  <nav aria-label="breadcrumb" class="pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Societies</li>
    </ol>
  </nav>

  @if (session('success'))
    <div class="alert alert-success">
      {{ session('success') }}
    </div>
  @endif

  <div class="row mb-2">
    <div class="col-12 ">
      <div class="card">
        <div class="card-body p-4">
          <form id="filterForm" method="GET" action="{{ route('societies.index', $user_type) }}">
            <div class="row g-3">
              <div class="{{ $colClass }}">
                <label class="form-label">Society Name </label>
                <select name="name" class="form-select" onchange="document.getElementById('filterForm').submit()">
                  <option value="">All</option>
                  @foreach ($societyNames as $name)
                    <option value="{{ $name }}" {{ request('name') == $name ? 'selected' : '' }}>
                      {{ $name }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="{{ $colClass }}">
                <label class="form-label">City</label>
                <select name="city" class="form-select" onchange="document.getElementById('filterForm').submit()">
                  <option value="">All</option>
                  @foreach ($cities as $city)
                    <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>
                      {{ $city }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="{{ $colClass }}">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" onchange="document.getElementById('filterForm').submit()">
                  <option value="">All</option>
                  <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                  <option value="in-active" {{ request('status') == 'in-active' ? 'selected' : '' }}>In-active</option>
                </select>
              </div>
              @if ($user_type != 'owner_societies')
                <div class="col-12 col-md-4 col-lg-3">
                  <label class="form-label">Owner</label>
                  <select name="owner" class="form-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All</option>
                    @foreach ($owners as $owner)
                      <option value="{{ $owner->id }}" {{ request('owner') == $owner->id ? 'selected' : '' }}>
                        {{ $owner->first_name }} {{ $owner->last_name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              @endif
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  @if ($societies->count() < 1)
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-body text-center py-5">
            <h1 class="mb-0">
              <i class="fa-solid fa-triangle-exclamation not_found_icon text-warning"></i>
            </h1>
            <h4 class="mb-1">No societies found</h4>
            <p class="text-muted mb-1">
              There are no societies available right now. Try changing your filter criteria.
            </p>
          </div>
        </div>
      </div>
    </div>
  @endif

  <div class="row g-3  pt-2 societies_cards mb-6 gap-1px" id="sortable-4">
    @foreach ($societies as $society)
      <div class="col-md-6 col-xl-4">
        <div class="card ">
          <img class="card-img-top"
            src="{{ $society->attachment ? asset('storage/' . $society->attachment->link) : asset('assets/img/my_images/dummy_society_image.png') }}"
            alt="{{ $society->name }}" height="170px" style="object-fit: cover;" />
          <div class="card-body py-2 px-4">
            <h5 class="card-title fs-6 mb-0 ">{{ $society->name }}, {{ $society->city }} </h5>
            @if ($user_type == 'owner_societies')
              <p class="card-text mb-0">

                <small class="text-body address-clamp" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="tooltip-secondary" data-bs-original-title="{{ $society->address }}">{{ $society->address }} </small>
              </p>
              <p class="card-text mb-2 ">
                <small class="text-body">{{ $society->country }}</small>
              </p>
            @endif
            @if ($user_type != 'owner_societies')
              <p class="card-text mb-2 ">
                <small class="text-body"><b>Owner:</b> {{ ucfirst($society->owner->first_name) }}
                  {{ ucfirst($society->owner->last_name) }}</small>
              </p>
            @endif
            <div class="d-flex justify-content-between align-items-center mb-1">
              <a href="{{ route('societies.show', [$user_type, $society->uuid]) }}"
                class="btn btn-outline-primary waves-effect btn-sm">
                <i class="fa-regular fa-eye me-1"></i> View Details
              </a>
              <span class="badge {{ $society->status == 'active' ? 'bg-primary' : 'bg-secondary' }} bg-glow">
                {{ ucfirst($society->status) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- load more button --}}
  @if ($total_societies > $total_skip)
    <div id="load_more" class="text-center">
      <button class="btn btn-primary" type="button" id="load_more_btn">
        <span class="spinner-border me-1 d-none" role="status" aria-hidden="true" id="spinner"></span>
        <span id="load_more_text"> Load More </span>
      </button>
    </div>
  @endif
@endsection

@push('scripts')
  <script>
    var skip = 6;
    $(document).on("click", "#load_more_btn", function(e) {
      e.preventDefault();
      var spinner = $("#spinner");
      spinner.removeClass("d-none");
      $('#load_more_text').hide();
      var btn = $(this);
      btn.prop("disabled", true);

      // Get current filter values
      var data = {
        skip: skip,
        name: $('select[name="name"]').val(),
        city: $('select[name="city"]').val(),
        status: $('select[name="status"]').val(),
        @if ($user_type != 'owner_societies')
          owner: $('select[name="owner"]').val(),
        @endif
      };
      $.ajax({
        url: "{{ route('societies.index', $user_type) }}",
        type: "GET",
        data: data, // Changed this line
        success: function(response) {
          skip = response.total_skip;
          response.societies.forEach(function(society) {
            var imageSrc = society.attachment ?
              "{{ asset('storage/') }}/" + society.attachment.link :
              "{{ asset('assets/img/my_images/dummy_society_image.png') }}";
            var ownerInfo = '';
            @if ($user_type != 'owner_societies')
              ownerInfo = `<p class="card-text mb-2">
              <small class="text-body"><b>Owner:</b> ${society.owner.first_name} ${society.owner.last_name}</small>
            </p>`;
            @else
              ownerInfo = `<p class="card-text mb-0">
              <small class="text-body">${society.address}</small>
            </p>
            <p class="card-text mb-2">
              <small class="text-body">${society.country}</small>
            </p>`;
            @endif
            var statusBadgeClass = society.status == 'active' ? 'bg-primary' : 'bg-secondary';
            var link = "{{ route('societies.show', [$user_type, '__UUID__']) }}".replace('__UUID__', society
              .uuid);
            $('.societies_cards').append(`
            <div class="col-md-6 col-xl-4">
              <div class="card">
                <img class="card-img-top" src="${imageSrc}" alt="${society.name}" height="170px" style="object-fit: cover;" />
                <div class="card-body py-2 px-4">
                  <h5 class="card-title fs-6 mb-0">${society.name}, ${society.city}</h5>
                  ${ownerInfo}
                  <div class="d-flex justify-content-between align-items-center mb-1">
                   <a href="${link}" class="btn btn-outline-primary waves-effect btn-sm">
                      <i class="fa-regular fa-eye me-1"></i> View Details
                    </a>
                    <span class="badge ${statusBadgeClass} bg-glow">
                      ${society.status.charAt(0).toUpperCase() + society.status.slice(1)}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          `);
          });
          if (response.total_societies > response.total_skip) {
            $("#load_more").show();
            spinner.addClass("d-none");
            $('#load_more_text').show();
            btn.prop("disabled", false);
          } else {
            $("#load_more").hide();
          }
        },
        error: function(xhr, status, error) {
          spinner.addClass("d-none");
          btn.prop("disabled", false);
          if (typeof Swal !== "undefined") {
            Swal.fire("Error", "Failed to load more societies", "error");
          }
        }
      });
    });
  </script>
@endpush
