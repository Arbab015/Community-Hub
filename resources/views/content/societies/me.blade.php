@extends('layouts/layoutMaster')
@section('title', 'Society Details - ' . $society->name)
@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Society Management</p>
      <h4 class="mb-1">Society Details</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item">
            <a href="{{ route('societies.index', $user_type) }}">Societies</a>
          </li>
          <li class="breadcrumb-item">
            <a href="{{ route('societies.show', [$user_type, $uuid]) }}">Society</a>
          </li>
          @php
            $slug_labels = [
                'my_posts' => 'My posts',
                'blocked_posts' => 'Blocked posts',
//                'requested_posts' => 'Requested posts'
            ];
          @endphp
          <li class="breadcrumb-item active">   {{ $slug_labels[$slug] }}</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-building-community text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>

    {{-- Mobile offcanvas toggle — only visible on forum tabs --}}
    <div class="d-block d-md-none" id="forum_canvas_btn">
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
  </div>


  <div class="row g-5">
    <div class="col-md-9 col-12">
      @include('components.forum.index')
      <div class="mt-3">
        {!! $posts->withQueryString()->links('pagination::bootstrap-5') !!}
      </div>
    </div>

    {{-- Right section desktop only --}}
    <div class="d-none d-md-block col-md-3">
      @include('components.forum.right_section')
    </div>
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
  </div>

@endsection
