@extends('layouts/layoutMaster')
@section('title', 'Forum - ' . ucfirst($type ?? 'All Posts'))

@section('content')
  <div class="d-flex justify-content-between">
    <h4 class="mb-1">{{ ucfirst($type) }}</h4>
    <div class="d-md-none">
      <button class="btn p-0 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#forumRightOffcanvas"
        aria-controls="forumRightOffcanvas">
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
      <li class="breadcrumb-item active">{{ ucfirst($type) }}</li>
    </ol>
  </nav>

  <div class="row g-5">
    <div class="col-md-9 col-12">
      <div class="card border-0 shadow-sm mb-5">
        <div class="card-body py-3 px-4">
          <form method="GET" action="{{ route('posts.index', $type) }}">
            <div class="row justify-content-between g-3">
              {{-- Search Input --}}
              <div class="col-12 col-sm-6">
                <div class="input-group">
                  <input type="text" class="form-control" name="search" placeholder="Search by title or tags..."
                    value="{{ request('search') }}">
                  <button class="btn btn-outline-primary" type="submit">
                    <i class="ti ti tabler-search"></i>
                  </button>
                </div>
              </div>

              {{-- Sort Dropdown --}}
              <div class="col-12 col-sm-3 col-lg-2">
                <select name="sort" class="form-select" onchange="this.form.submit()">
                  <option value="latest" {{ request('sort') == 'latest' || !request('sort') ? 'selected' : '' }}>Latest
                  </option>
                  <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>Oldest</option>
                </select>
              </div>
            </div>
          </form>
        </div>
      </div>

      @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      {{-- posts cards --}}
      @include('components.forum.index')



      {{-- pagination section --}}
      <div class="mt-3">
        {!! $posts->withQueryString()->links('pagination::bootstrap-5') !!}
      </div>
    </div>

    {{-- Right section desktop only --}}
    <div class="d-none d-md-block col-md-3">
      @include('components.forum.right_section')
    </div>
  </div>

  {{-- Right section offcanvas mobile only --}}
  <div class="offcanvas offcanvas-end" tabindex="-1" id="forumRightOffcanvas"
       aria-labelledby="forumRightOffcanvasLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="forumRightOffcanvasLabel"></h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      @include('components.forum.right_section')
    </div>
  </div>
  @include('_partials._modals.report_an_issue')
@endsection
