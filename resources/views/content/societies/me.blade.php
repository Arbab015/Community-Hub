@extends('layouts/layoutMaster')
@section('title', 'Society Details - ' . $society->name)
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
      <li class="breadcrumb-item">
        <a href="{{ route('societies.show', [$user_type, $uuid]) }}">Society</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      @php
        $slug_labels = [
            'my_posts' => 'My posts',
            'blocked_posts' => 'Blocked posts',
            'requested_posts' => 'Requested posts'
        ];
      @endphp
      <li class="breadcrumb-item active">
        {{ $slug_labels[$slug] }}
      </li>
    </ol>
  </nav>

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
