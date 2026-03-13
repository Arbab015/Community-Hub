@extends('layouts/layoutMaster')

@section('title', 'Society Details - ' . $society->name)
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/spinkit/spinkit.scss', 'resources/assets/vendor/libs/dropzone/dropzone.scss'])
@endsection
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/dropzone/dropzone.js'])
@endsection
@section('page-script')
  @vite(['resources/assets/js/forms-file-upload.js'])
@endsection
@section('content')
  <div class="d-flex justify-content-between">
    <h4 class="mb-1">Society Details</h4>
    <div class="d-md-none d-none " id="forum_canvas_btn">
      <button class="btn p-0 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#forumRightOffcanvas" aria-controls="forumRightOffcanvas">
        <i class="icon-base ti tabler-menu-2 icon-md"></i>
      </button>
    </div>
  </div>

  <nav aria-label="breadcrumb" class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>

      <li class="breadcrumb-item">
        <a href="{{ route('societies.index', $user_type) }}">Societies</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Society</li>
    </ol>
  </nav>

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

  @if (auth()->user()->can('owner_societies') || auth()->user()->can('all_societies') ) <!--  && $society->status == 'active' -->
    <div class="col-12">
      <div class="card mb-4 shadow-sm rounded-3">
        <div class="card-header p-2 ">
          <ul class="nav nav-pills flex-wrap gap-1" role="tablist">
            <li class="nav-item">
              <button class="nav-link  active" data-bs-toggle="tab" data-bs-target="#info" type="button">
                Info
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link tab-warning" data-bs-toggle="tab" data-bs-target="#gallery" type="button">
                Gallery
              </button>
            </li>

            <!-- Forum Dropdown -->
            <li class="nav-item dropdown">
              <button class="nav-link dropdown-toggle tab-danger" data-bs-toggle="dropdown" type="button">
                Forum
              </button>
              <ul class="dropdown-menu">
                <li>
                  <button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#discussions" type="button">
                    Discussions
                  </button>
                </li>
                <li>
                  <button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#suggestions" type="button">
                    Suggestions
                  </button>
                </li>
                <li>
                  <button class="dropdown-item" data-bs-toggle="tab" data-bs-target="#issues" type="button">
                    Issues
                  </button>
                </li>
              </ul>
            </li>

            <li class="nav-item">
              <button class="nav-link tab-danger" data-bs-toggle="tab" data-bs-target="#complaints" type="button">
                Complaints
              </button>
            </li>

          </ul>
        </div>
      </div>
    </div>
  @endif

  {{-- show tabs content --}}
  <div class="tab-content p-0">
    <div class="tab-pane fade show active" id="info">
      @include('components.societies.info')
    </div>
    <div class="tab-pane fade" id="gallery">
      @include('components.societies.gallery')
    </div>

    {{-- Forum tabs --}}
    <div class="tab-pane fade" id="discussions">
      @include('components.societies.forum', ['type' => 'discussions', 'category' => 'discussion'])
    </div>
    <div class="tab-pane fade" id="suggestions">
      @include('components.societies.forum', ['type' => 'suggestions', 'category' => 'suggestion'])
    </div>
    <div class="tab-pane fade" id="issues">
      @include('components.societies.forum', ['type' => 'issues', 'category' => 'issue'])
    </div>

    <div class="tab-pane fade" id="complaints">
      @include('components.societies.complaints')
    </div>
  </div>

  {{-- Right section offcanvas mobile only --}}

@endsection

@push('scripts')
  <script>
    const forumTabIds = ['#discussions', '#suggestions', '#issues'];
    const offcanvasBtn = document.getElementById('forum_canvas_btn');

    // Show offcanvas btn when any forum dropdown item is clicked
    document.querySelectorAll('.dropdown-item[data-bs-toggle="tab"]').forEach(item => {
      item.addEventListener('click', function () {
        const target = this.getAttribute('data-bs-target');
        if (forumTabIds.includes(target)) {
          offcanvasBtn.classList.remove('d-none');
          offcanvasBtn.classList.add('d-md-none');
        }
      });
    });
  </script>
@endpush
