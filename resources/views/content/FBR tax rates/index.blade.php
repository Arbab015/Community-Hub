@extends('layouts/layoutMaster')

@section('title', 'FBR Tax Rates')

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Tax Configuration</p>
      <h4 class="mb-1">FBR Tax Rates</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active">FBR Tax Rates</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-calculator text-dark opacity-25 position-absolute end-0 me-4"></i>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4">
    <i class="ti tabler-info-circle"></i>
    <span class="small">
      Each role (Buyer / Seller) is broken into value slabs. Every slab must have one rate for
      <b>filers</b> and one rate for <b>non-filers</b> — the system keeps both records together automatically.
    </span>
  </div>

  @php
    $fmtRange = fn($valueFrom, $valueTo) => 'PKR ' . number_format($valueFrom)
      . ($valueTo !== null ? ' – ' . number_format($valueTo) : ' and above');
    $sections = [
        ['key' => 'buyer', 'title' => 'Buyer', 'slabs' => $buyerSlabs, 'hint' => 'Tax collected from the buyer ', 'icon' => 'ti tabler-user-plus'],
        ['key' => 'seller', 'title' => 'Seller', 'slabs' => $sellerSlabs, 'hint' => 'Tax collected from the seller ', 'icon' => 'ti tabler-user-minus'],
    ];
  @endphp

  <div class="row g-4">
    @foreach($sections as $section)
      <div class="col-12">
        <div class="card border-0 shadow-sm rounded-3">
          <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <i class="ti {{ $section['icon'] }} text-primary fs-5"></i>
              <div>
                <h5 class="fw-bold mb-0">{{ $section['title'] }} Tax Rates</h5>
                <small class="text-muted">{{ $section['hint'] }}</small>
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-primary add-slab-btn" data-role="{{ $section['key'] }}">
              <i class="ti tabler-plus me-1"></i> Add Slab
            </button>
          </div>

          <div class="card-body mt-2">
            @if(count($section['slabs']))
              @foreach($section['slabs'] as $index => $slab)
                @php
                  $filer = $slab['filer'];
                  $nonFiler = $slab['non_filer'];
                @endphp
                <div class="border rounded-3 mb-3 overflow-hidden">
                  <div
                    class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2 bg-label-secondary">
                    <span class="fw-semibold small text-heading">
                      #{{ $index + 1 }} · {{ $fmtRange($slab['value_from'], $slab['value_to']) }}
                    </span>
                    <form action="{{ route('fbr-tax-rates.destroy') }}" method="POST"
                          onsubmit="return confirmDelete(event, null)">
                      @csrf
                      <input type="hidden" name="slab_id" value="{{ $filer->id }}">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Remove this slab">
                        <i class="ti tabler-trash"></i>
                      </button>
                    </form>
                  </div>

                  <form action="{{ route('fbr-tax-rates.update') }}" method="POST" class="px-3 py-2">
                    @csrf
                    <input type="hidden" name="slab_id" value="{{ $filer->id }}">

                    <div class="row g-2 align-items-end">
                      <div class="col-6 col-md-3">
                        <label class="form-label small mb-1 text-muted">From (PKR)</label>
                        <input type="number" name="value_from" class="form-control form-control-sm" min="0" step="1"
                               value="{{ $slab['value_from'] }}" required>
                      </div>

                      <div class="col-6 col-md-3">
                        <label class="form-label small mb-1 text-muted">To (PKR) <span class="text-muted">(blank = and above)</span></label>
                        <input type="number" name="value_to" class="form-control form-control-sm" min="0" step="1"
                               value="{{ $slab['value_to'] !== null ? $slab['value_to'] : '' }}"
                               placeholder="And above">
                      </div>

                      <div class="col-6 col-md-2">
                        <label class="form-label small mb-1 text-muted">Filer (%)</label>
                        <input type="number" name="filers_rate" class="form-control form-control-sm" min="0" max="100"
                               step="0.01" value="{{ $filer->rate_percent }}" required>
                      </div>

                      <div class="col-6 col-md-2">
                        <label class="form-label small mb-1 text-muted">Non-Filer (%)</label>
                        <input type="number" name="non_filers_rate" class="form-control form-control-sm" min="0"
                               max="100"
                               step="0.01" value="{{ $nonFiler->rate_percent }}" required>
                      </div>

                      <div class="col-12 col-md-2 d-flex justify-content-md-end">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Save changes">
                          <i class="ti tabler-check me-1"></i> Save
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              @endforeach
            @else
              <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
                <i class="ti tabler-calendar-x fs-2 opacity-50 mb-2"></i>
                <small>No {{ strtolower($section['title']) }} rates configured yet. Click <b>Add Slab</b> to create one.</small>
              </div>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Add slab modal --}}
  <div class="modal fade" id="addSlabModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-simple modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Add FBR Rate Slab</h4>
            <p class="text-muted mb-0">A filer and a non-filer record will be created together for this slab.</p>
          </div>

          <form action="{{ route('fbr-tax-rates.store') }}" method="POST">
            @csrf

            <div class="mb-3">
              <label class="form-label fw-semibold">Category / Role</label>
              <select name="role" id="addSlabRole" class="form-select" required>
                @foreach(['buyer', 'seller'] as $role)
                  <option value="{{ $role }}" @selected($role === 'buyer')>{{ ucfirst($role) }}</option>
                @endforeach
              </select>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label fw-semibold">Value From (PKR)</label>
                <input type="number" name="value_from" class="form-control" min="0" step="1" placeholder="0" required>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Value To (PKR) <span class="text-muted">(blank = and above)</span></label>
                <input type="number" name="value_to" class="form-control" min="0" step="1" placeholder="And above">
              </div>
            </div>

            <div class="row g-3">
              <div class="col-6">
                <label class="form-label fw-semibold">Filer Rate (%)</label>
                <input type="number" name="filers_rate" class="form-control" min="0" max="100" step="0.01" value="0"
                       required>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">Non-Filer Rate (%)</label>
                <input type="number" name="non_filers_rate" class="form-control" min="0" max="100" step="0.01" value="0"
                       required>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Add Rate</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.querySelectorAll('.add-slab-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var role = btn.getAttribute('data-role');
        var select = document.getElementById('addSlabRole');
        if (select) select.value = role;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addSlabModal')).show();
      });
    });
  </script>
@endpush
