@extends('layouts/layoutMaster')

@section('title', 'System Settings')

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Configuration</p>
      <h4 class="mb-1">System Settings</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active">System Settings</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-adjustments-horizontal text-dark opacity-25 position-absolute end-0 me-4"></i>
  </div>

  @if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible" role="alert">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="card border-0 shadow-sm rounded-3">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3 py-3 border-bottom">
      <div class="d-flex align-items-center gap-2">
        <i class="ti tabler-settings text-primary fs-5"></i>
        <div>
          <h5 class="fw-bold mb-0">Society Configuration</h5>
          <small class="text-muted">Choose a society and enable the settings it should use.</small>
        </div>
      </div>

      <form method="GET" action="{{ route('system-settings.index') }}" class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 fw-semibold text-nowrap" for="society_switcher">Society</label>
        <select id="society_switcher" name="society_id" class="form-select form-select-sm"
                onchange="this.form.submit()" {{ $societies->count() <= 1 ? 'disabled' : '' }}>
          @forelse ($societies as $society)
            <option value="{{ $society->id }}" @selected($selectedSociety && $selectedSociety->id === $society->id)>
              {{ ucfirst($society->name) }}
            </option>
          @empty
            <option value="">No societies</option>
          @endforelse
        </select>
        <noscript><button type="submit" class="btn btn-sm btn-outline-primary">Load</button></noscript>
      </form>
    </div>

    <div class="card-body mt-2">
      @if ($selectedSociety)
        <form action="{{ route('system-settings.update') }}" method="POST">
          @csrf
          <input type="hidden" name="society_id" value="{{ $selectedSociety->id }}">

          @foreach ($definitions as $key => $definition)
            <div class="d-flex align-items-start justify-content-between gap-3 border rounded-3 p-3 mb-3">
              <div>
                <h6 class="mb-1 fw-semibold">{{ $definition['label'] }}</h6>
                <small class="text-muted">{{ $definition['description'] }}</small>
              </div>
              <div class="form-check form-switch fs-5 mt-1">
                <input type="hidden" name="settings[{{ $key }}]" value="0">
                <input type="checkbox" role="switch" class="form-check-input" id="setting_{{ $key }}"
                       name="settings[{{ $key }}]" value="1"
                       @checked($settings[$key] ?? $definition['default'])>
                <label class="form-check-label d-none" for="setting_{{ $key }}">{{ $definition['label'] }}</label>
              </div>
            </div>
          @endforeach

          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">
              <i class="ti tabler-check me-1"></i> Save Settings
            </button>
          </div>
        </form>
      @else
        <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted">
          <i class="ti tabler-building-off fs-2 opacity-50 mb-2"></i>
          <small>You do not have any society to configure.</small>
        </div>
      @endif
    </div>
  </div>
@endsection
