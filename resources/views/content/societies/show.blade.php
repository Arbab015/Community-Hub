@extends('layouts/layoutMaster')
@section('title', 'Society Details - ' . $society->name)
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/spinkit/spinkit.scss', 'resources/assets/vendor/libs/notyf/notyf.scss', 'resources/assets/vendor/libs/dropzone/dropzone.scss'])
@endsection
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/notyf/notyf.js','resources/assets/vendor/libs/dropzone/dropzone.js'])
@endsection
@section('page-script')
  @vite([ 'resources/assets/js/ui-toasts.js','resources/assets/js/forms-file-upload.js'])
@endsection

@section('content')
  {{-- HEADER + BREADCRUMB --}}
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Society Management</p>
      <h4 class="mb-1">Society Details</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}" class="text-dark opacity-75 text-decoration-none">Home</a>
          </li>
          <li class="breadcrumb-item">
            <a href="{{ route('societies.index', $user_type) }}"
               class="text-dark opacity-75 text-decoration-none">Societies</a>
          </li>
          <li class="breadcrumb-item active text-dark opacity-50">Society details</li>
        </ol>
      </nav>
    </div>

    {{-- Mobile offcanvas toggle — only visible on forum tabs --}}
    <div class="d-none position-relative" id="forum_canvas_btn" style="z-index: 2;">
      <button
        class="btn btn-sm btn-secondary opacity-75"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#forumRightOffcanvas"
        aria-controls="forumRightOffcanvas"
      >
        <i class="icon-base ti tabler-layout-sidebar-right icon-md"></i>
      </button>
    </div>

    <i class="ti tabler-building-community text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  {{-- ALERTS --}}
  @if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
      <i class="ti tabler-circle-check-filled"></i> {{ session('success') }}
    </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
      <i class="ti tabler-alert-circle-filled"></i> {{ session('error') }}
    </div>
  @endif

  {{-- GLOBAL VALIDATION SUMMARY --}}
  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible d-flex gap-3 mb-3" role="alert">
      <i class="ti tabler-alert-circle-filled fs-4 flex-shrink-0 mt-1"></i>
      <div>
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-1 small">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- TITLE ROW --}}
  @php
    $society_address = collect([
        $society->address ? ucfirst($society->address) : null,
        $society->city ? ucfirst($society->city) : null,
        $society->country ? ucfirst($society->country) : null,
    ])->filter()->implode(', ');
  @endphp

  <div class="d-flex align-items-start gap-3 mb-3">
    <div class="rounded-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
         style="width:48px; height:48px;">
      <i class="ti tabler-building-community text-primary fs-4"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <h6 class="mb-1 fw-semibold">{{ ucwords($society->name) }}</h6>
      @if($society_address)
        <span class="text-muted small">
        {{ $society_address }}
        </span>
      @endif
    </div>
  </div>

  {{-- TABS --}}
  @if (auth()->user()->can('owner_societies') || auth()->user()->can('all_societies') )
    <div class="col-12">
      <ul class="nav nav-pills flex-column flex-sm-row gap-1 border rounded-3 p-1 bg-light">
        <li class="nav-item">
          <button class="nav-link active w-100 text-start" data-bs-toggle="pill" data-bs-target="#info">
            <i class="ti tabler-info-circle me-2 d-none d-sm-inline-block"></i> Info
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#gallery">
            <i class="ti tabler-photo me-2 d-none d-sm-inline-block"></i> Gallery
          </button>
        </li>

        <!-- Forum Dropdown -->
        <li class="nav-item dropdown">
          <button class="nav-link dropdown-toggle w-100 text-start" data-bs-toggle="dropdown" type="button"
                  aria-expanded="false">
            <i class="ti tabler-inbox me-2 d-none d-sm-inline-block"></i> Forum
          </button>
          <ul class="dropdown-menu">
            <li>
              <button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#discussions" type="button">
                <i class="ti tabler-messages me-2"></i> Discussions
              </button>
            </li>
            <li>
              <button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#suggestions" type="button">
                <i class="ti tabler-bulb me-2"></i> Suggestions
              </button>
            </li>
            <li>
              <button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#issues" type="button">
                <i class="ti tabler-alert-octagon me-2"></i> Issues
              </button>
            </li>
          </ul>
        </li>

        <li class="nav-item">
          <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#properties">
            <i class="ti tabler-building-skyscraper me-2 d-none d-sm-inline-block"></i> Properties
          </button>
        </li>
      </ul>
    </div>
  @endif

  {{-- TAB CONTENT --}}
  <div class="tab-content mt-3 p-0 mt-5">
    <div class="tab-pane fade show active" id="info" role="tabpanel">
      @include('components.societies.info')
    </div>
    <div class="tab-pane fade" id="gallery" role="tabpanel">
      @include('components.gallery')
    </div>

    {{-- Forum tabs --}}
    <div class="tab-pane fade" id="discussions" role="tabpanel">
      @include('components.societies.forum', ['type' => 'discussions', 'category' => 'discussion'])
    </div>
    <div class="tab-pane fade" id="suggestions" role="tabpanel">
      @include('components.societies.forum', ['type' => 'suggestions', 'category' => 'suggestion'])
    </div>
    <div class="tab-pane fade" id="issues" role="tabpanel">
      @include('components.societies.forum', ['type' => 'issues', 'category' => 'issue'])
    </div>

    <div class="tab-pane fade" id="properties" role="tabpanel">
      @include('components.societies.properties')
    </div>
  </div>{{-- /.tab-content --}}

@endsection

@push('scripts')
  <script>
    const offcanvasBtn = document.getElementById('forum_canvas_btn');
    const offcanvasBtnInner = offcanvasBtn.querySelector('button');
    const forumTabIds = ['#discussions', '#suggestions', '#issues'];

    document.addEventListener('shown.bs.tab', function(e) {
      const target = e.target.getAttribute('data-bs-target');

      if (forumTabIds.includes(target)) {
        // Show button only on mobile (below md = 768px)
        offcanvasBtn.classList.remove('d-none');
        offcanvasBtn.classList.add('d-md-none'); // hides on md and above

        // Retarget offcanvas button to the active tab pane's offcanvas
        const activePane = document.querySelector(target);
        const activeOffcanvas = activePane
          ? activePane.querySelector('[id^="forumRightOffcanvas"]')
          ?? activePane.querySelector('.offcanvas')
          : null;

        if (activeOffcanvas) {
          const uniqueId = 'forumRightOffcanvas_' + target.replace('#', '');
          activeOffcanvas.setAttribute('id', uniqueId);
          offcanvasBtnInner.setAttribute('data-bs-target', '#' + uniqueId);
          offcanvasBtnInner.setAttribute('aria-controls', uniqueId);
        }
      } else {
        // Hide completely on non-forum tabs
        offcanvasBtn.classList.add('d-none');
        offcanvasBtn.classList.remove('d-md-none');
      }
    });
  </script>
@endpush
