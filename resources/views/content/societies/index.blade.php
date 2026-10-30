@extends('layouts/layoutMaster')

@section('title', 'Societies')
{{-- Vendor Styles --}}
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/spinkit/spinkit.scss', 'resources/assets/vendor/libs/notiflix/notiflix.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss'])
@endsection
{{-- Vendor Scripts --}}
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/jquery/jquery.js', 'resources/assets/vendor/libs/notiflix/notiflix.js', 'resources/assets/vendor/libs/sortablejs/sortable.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js'  ])
@endsection
@section('page-script')
  @vite(['resources/assets/js/cards-actions.js', 'resources/assets/js/forms-selects.js', 'resources/assets/js/installment-plan-calculator.js', 'resources/assets/js/installment-template-preview.js'])
@endsection
@section('content')
  @php
    $colClass = $user_type == 'owner_societies' ? 'col-12 col-md-4 col-lg-4' : 'col-12 col-md-4 col-lg-3';
  @endphp
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Society Management</p>
      <h4 class="mb-1">@if($user_type === 'owner_societies')
          My Societies
        @else
          All Societies
        @endif</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active"> Societies</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-building-community text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card mb-4 border-0 shadow-sm rounded-4">
    <div class="card-header d-flex justify-content-between align-items-center">

      <h5 class="mb-1 fw-semibold">
        Societies
      </h5>

      <div class="d-flex align-items-center gap-3">
        <button type="button" id="select_all_societies_btn" class="btn btn-sm btn-outline-secondary rounded-pill"
                onclick="toggleSelectAllSocieties()">
          <i class="ti tabler-checkbox me-1"></i> Select All
        </button>

        <button type="button" id="apply_payment_template_btn" class="btn btn-sm btn-primary rounded-pill d-none"
                onclick="applyPaymentTemplateToSocieties()">
          <i class="ti tabler-file-invoice me-1"></i> Apply Payment Plan
        </button>

        @can('add_society')
          <a href="{{ route('society.create', $user_type) }}"
             class="btn btn-primary btn-sm rounded-pill d-flex align-items-center gap-1 shadow-sm">
            <i class="ti tabler-plus"></i>
            Create Society
          </a>
        @endcan
      </div>

    </div>

    <hr class="mt-0 mb-3">

    <div class="card-body">
      <form method="GET" action="{{ route('societies.index', $user_type) }}" id="filterForm">

        <div class="row g-3">
          <div class="{{ $colClass }} mt-0">
            <label class="form-label text-uppercase fw-semibold small">Society Name</label>
            <input type="text" class="form-control" name="name" id="search_content"
                   value="{{ request('name') }}" placeholder="Search by society name...">
          </div>

          <div class="{{ $colClass }} mt-0">
            <label class="form-label text-uppercase fw-semibold small">City</label>
            <select name="city" class="form-select"
                    onchange="document.getElementById('filterForm').submit()">
              <option value="">All</option>
              @foreach ($cities as $city)
                <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>
                  {{ $city }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="{{ $colClass }} mt-0">
            <label class="form-label text-uppercase fw-semibold small">Status</label>
            <select name="status" class="form-select"
                    onchange="document.getElementById('filterForm').submit()">
              <option value="">All</option>
              <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
              <option value="in-active" {{ request('status') == 'in-active' ? 'selected' : '' }}>In-active</option>
            </select>
          </div>

          @if ($user_type != 'owner_societies')
            <div class="{{ $colClass }} mt-0">
              <label class="form-label text-uppercase fw-semibold small">Owner</label>
              <select name="owner" class="form-select"
                      onchange="document.getElementById('filterForm').submit()">
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

  <div class="row g-3 pt-2 societies_cards mb-6 gap-1px" id="sortable-4">
    @foreach ($societies as $society)
      <div class="col-md-6 col-xl-3">
        <div class="card society-card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="position-relative overflow-hidden">
            <input type="checkbox"
                   class="form-check-input society-checkbox fs-5 position-absolute top-0 start-0 m-3 z-2 shadow"
                   value="{{ $society->uuid }}">
            <img class="card-img-top society-img object-fit-cover"
                 src="{{ $society->attachment ? asset('storage/' . $society->attachment->link) : asset('assets/img/my_images/dummy_society_image.png') }}"
                 alt="{{ $society->name }}" height="170" />
          </div>
          <div class="card-body px-4 py-3 d-flex flex-column">
            <h5 class="card-title fs-6 fw-semibold mb-1 text-truncate" data-bs-toggle="tooltip"
                data-bs-placement="bottom" data-bs-custom-class="tooltip-secondary"
                data-bs-original-title="{{ $society->name }}, {{ $society->city }}">{{ ucwords($society->name) }},
              {{ ucwords($society->city) }}</h5>
            @if ($user_type == 'owner_societies')
              <p class="card-text mb-0">
                <small class="text-body-secondary address-clamp" data-bs-toggle="tooltip" data-bs-placement="bottom"
                       data-bs-custom-class="tooltip-secondary"
                       data-bs-original-title="{{ $society->address }}">{{ ucwords($society->address) }}</small>
              </p>
              <p class="card-text mb-2">
                <small class="text-body-secondary">{{ ucfirst($society->country) }}</small>
              </p>
            @else
              <p class="card-text mb-2">
                <small class="text-body-secondary"><b>Owner:</b> {{ ucfirst($society->owner->first_name) }}
                  {{ ucfirst($society->owner->last_name) }}</small>
              </p>
            @endif
            <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
              <a href="{{ route('societies.show', [$user_type, $society->uuid]) }}"
                 class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fa-regular fa-eye me-1"></i> View Details
              </a>
              <span
                class="badge rounded-pill {{ $society->status == 'active' ? 'bg-primary' : 'bg-secondary' }} bg-glow">
                {{ ucfirst($society->status) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  @if ($total_societies > $total_skip)
    <div id="load_more" class="text-center">
      <button class="btn btn-primary rounded-pill px-4 shadow-sm" type="button" id="load_more_btn">
        <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true" id="spinner"></span>
        <span id="load_more_text"> Load More </span>
      </button>
    </div>
  @endif
  @include('_partials._modals.apply_payment_plan')
@endsection

@section('page-style')
  <style>
    .society-card {
      transition: transform .2s ease, box-shadow .2s ease;
    }

    .society-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 .75rem 1.5rem rgba(47, 43, 61, .15) !important;
    }

    .society-img {
      transition: transform .35s ease;
    }

    .society-card:hover .society-img {
      transform: scale(1.06);
    }

    .society-checkbox {
      cursor: pointer;
    }

    .society-card:has(.society-checkbox:checked) {
      box-shadow: 0 0 0 2px var(--bs-primary) !important;
    }
  </style>
@endsection

@push('scripts')
  <script>
    // ---------- Helpers ----------
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#039;'
    }[ch]));

    const titleCase = (value) => String(value ?? '').replace(/\b\w/g, (ch) => ch.toUpperCase());

    const fieldValue = (selector) => {
      const el = document.querySelector(selector);
      return el ? el.value : '';
    };

    // Current filter values (same names the controller reads)
    const getFilters = () => {
      const filters = {
        name: fieldValue('input[name="name"]'),
        city: fieldValue('select[name="city"]'),
        status: fieldValue('select[name="status"]')
      };
      @if ($user_type != 'owner_societies')
        filters.owner = fieldValue('select[name="owner"]');
      @endif
        return filters;
    };

    // GET request that returns JSON (X-Requested-With makes $request->ajax() true in Laravel)
    const getJson = async (url, params = {}) => {
      const query = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') query.append(key, value);
      });
      const response = await fetch(`${url}?${query.toString()}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });
      if (!response.ok) throw new Error(`Request failed with status ${response.status}`);
      return response.json();
    };

    const showError = (message) => {
      if (typeof Swal !== 'undefined') {
        Swal.fire('Error', message, 'error');
      }
    };

    // ---------- Live search ----------
    let searchTimer;
    document.getElementById('search_content').addEventListener('keyup', function() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        document.getElementById('filterForm').submit();
      }, 1000);
    });

    // ---------- Load more ----------
    let skip = 6;
    const loadMoreBtn = document.getElementById('load_more_btn');

    const buildSocietyCard = (society) => {
      const imageSrc = society.attachment
        ? "{{ asset('storage') }}/" + society.attachment.link
        : "{{ asset('assets/img/my_images/dummy_society_image.png') }}";

      let ownerInfo = '';
      @if ($user_type != 'owner_societies')
        ownerInfo = `
          <p class="card-text mb-2">
            <small class="text-body-secondary"><b>Owner:</b> ${escapeHtml(titleCase(society.owner.first_name))} ${escapeHtml(titleCase(society.owner.last_name))}</small>
          </p>`;
      @else
        ownerInfo = `
          <p class="card-text mb-0">
            <small class="text-body-secondary address-clamp">${escapeHtml(titleCase(society.address))}</small>
          </p>
          <p class="card-text mb-2">
            <small class="text-body-secondary">${escapeHtml(titleCase(society.country))}</small>
          </p>`;
      @endif

      const statusBadgeClass = society.status === 'active' ? 'bg-primary' : 'bg-secondary';
      const statusText = society.status.charAt(0).toUpperCase() + society.status.slice(1);
      const link = "{{ route('societies.show', [$user_type, '__UUID__']) }}".replace('__UUID__', society.uuid);
      const checked = selectedSocieties.has(society.uuid) ? 'checked' : '';

      return `
        <div class="col-md-6 col-xl-4">
          <div class="card society-card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="position-relative overflow-hidden">
              <input type="checkbox" class="form-check-input society-checkbox fs-5 position-absolute top-0 start-0 m-3 z-2 shadow"
                     value="${escapeHtml(society.uuid)}" ${checked}>
              <img class="card-img-top society-img object-fit-cover" src="${imageSrc}" alt="${escapeHtml(society.name)}" height="170" />
            </div>
            <div class="card-body px-4 py-3 d-flex flex-column">
              <h5 class="card-title fs-6 fw-semibold mb-1 text-truncate">${escapeHtml(titleCase(society.name))}, ${escapeHtml(titleCase(society.city))}</h5>
              ${ownerInfo}
              <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                <a href="${link}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                  <i class="fa-regular fa-eye me-1"></i> View Details
                </a>
                <span class="badge rounded-pill ${statusBadgeClass} bg-glow">${escapeHtml(statusText)}</span>
              </div>
            </div>
          </div>
        </div>`;
    };

    if (loadMoreBtn) {
      loadMoreBtn.addEventListener('click', async function(e) {
        e.preventDefault();

        const spinner = document.getElementById('spinner');
        const loadMoreText = document.getElementById('load_more_text');

        spinner.classList.remove('d-none');
        loadMoreText.classList.add('d-none');
        loadMoreBtn.disabled = true;

        try {
          const response = await getJson("{{ route('societies.index', $user_type) }}", {
            skip: skip,
            ...getFilters()
          });

          skip = response.total_skip;

          const container = document.querySelector('.societies_cards');
          response.societies.forEach((society) => {
            container.insertAdjacentHTML('beforeend', buildSocietyCard(society));
          });

          if (response.total_societies > response.total_skip) {
            document.getElementById('load_more').classList.remove('d-none');
          } else {
            document.getElementById('load_more').classList.add('d-none');
          }
        } catch (error) {
          showError('Failed to load more societies');
        } finally {
          spinner.classList.add('d-none');
          loadMoreText.classList.remove('d-none');
          loadMoreBtn.disabled = false;
        }
      });
    }

    // ---------- Society multi-select for payment template application ----------
    let selectedSocieties = new Set();
    let allSocietiesSelected = false;

    document.addEventListener('change', function(e) {
      if (e.target.classList.contains('society-checkbox')) {
        if (e.target.checked) {
          selectedSocieties.add(e.target.value);
        } else {
          selectedSocieties.delete(e.target.value);
          if (allSocietiesSelected) {
            allSocietiesSelected = false;
            setSelectAllSocietiesBtn(false);
          }
        }
        updateSocietySelectionBar();
      }
    });

    function setSelectAllSocietiesBtn(active) {
      const btn = document.getElementById('select_all_societies_btn');
      btn.innerHTML = active
        ? '<i class="ti tabler-square-off me-1"></i> Deselect All'
        : '<i class="ti tabler-checkbox me-1"></i> Select All';
    }

    async function toggleSelectAllSocieties() {
      const btn = document.getElementById('select_all_societies_btn');

      if (allSocietiesSelected) {
        selectedSocieties.clear();
        document.querySelectorAll('.society-checkbox').forEach((cb) => cb.checked = false);
        allSocietiesSelected = false;
        setSelectAllSocietiesBtn(false);
        updateSocietySelectionBar();
        return;
      }

      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Selecting...';

      try {
        const response = await getJson("{{ route('societies.all_ids', $user_type) }}", getFilters());

        selectedSocieties = new Set(response.ids);
        document.querySelectorAll('.society-checkbox').forEach((cb) => cb.checked = true);
        allSocietiesSelected = true;
        setSelectAllSocietiesBtn(true);
        updateSocietySelectionBar();
      } catch (error) {
        btn.innerHTML = originalHtml;
        showError('Failed to select all societies');
      } finally {
        btn.disabled = false;
      }
    }

    function updateSocietySelectionBar() {
      const applyBtn = document.getElementById('apply_payment_template_btn');
      applyBtn.classList.toggle('d-none', selectedSocieties.size === 0);
    }

    function applyPaymentTemplateToSocieties() {
      if (!selectedSocieties.size) return;
      document.getElementById('apply_plan_selected_count').textContent = selectedSocieties.size;
      document.getElementById('apply_plan_ids').value = Array.from(selectedSocieties).join(',');
      document.getElementById('apply_plan_type').value = 'society';
      new bootstrap.Modal(document.getElementById('applyPaymentPlanModal')).show();
    }
  </script>
@endpush
