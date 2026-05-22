@extends('layouts/layoutMaster')
@section('title', 'Forum - ' . ucfirst($type ?? 'All Posts'))

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <h4 class="mb-1">
        {{ ucfirst($type) }}
      </h4>

      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">

          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>

          @if($is_my_post ?? false)
            <li class="breadcrumb-item">
              <a href="{{ route('posts.index', $type) }}">
                {{ ucfirst($type) }}
              </a>
            </li>
          @endif

          <li class="breadcrumb-item active">
            {{ ($is_my_post ?? false) ? 'My Posts' : ucfirst($type) }}
          </li>

        </ol>
      </nav>

    </div>

    <i class="ti tabler-messages text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>

    {{-- Mobile Offcanvas --}}
    <div class="d-md-none" id="forum_canvas_btn">
      <button
        class="btn btn-sm btn-secondary opacity-75"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#forumRightOffcanvas"
        aria-controls="forumRightOffcanvas">
        <i class="icon-base ti tabler-layout-sidebar-right icon-md"></i>
      </button>
    </div>

  </div>

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

              <div class="col-12 col-sm-3 col-xxl-2 ">
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
