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
              <div class="col-12 col-md-6">
                <div class="input-group">
                  <input type="text" class="form-control" name="search" placeholder="Search by title or tags..."
                    value="{{ request('search') }}">
                  <button class="btn btn-outline-primary" type="submit">
                    <i class="ti ti tabler-search"></i>
                  </button>
                </div>
              </div>

              {{-- Sort Dropdown --}}
              <div class="col-12 col-md-3 col-lg-2">
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
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          @forelse($posts as $post)
            @php
              $isAuthor = auth()->id() === $post->user_id;
              $noComments = $post->comments->count() === 0;
              $canReport = in_array($post->id, $reportedIds);
              $roleMember = Auth()->user()->hasRole('Society Member');
            @endphp
            @if (!in_array($post->id, $reportedIds))
              <div class="post-item" data-id="{{ $post->id }}">
                <div class="d-flex gap-3">
                  <img
                    src="{{ optional($post->user->attachment)->link
                        ? asset('storage/' . $post->user->attachment->link)
                        : asset('assets/img/avatars/1.png') }}"
                    class="rounded-circle" width="40" height="40" alt="User">
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <h6 class="mb-1 fw-semibold d-flex">
                          <a href="{{ route('posts.view', [$type, $post->slug]) }}"
                            class="text-dark text-decoration-none">{{ $post->title }}</a>
                          @if ($post->status == 'close')
                            <span class="ms-3 badge bg-warning" style="font-size: 0.78rem"> Blocked </span>
                          @endif
                        </h6>

                        <div class="text-muted small mb-2">
                          <span class="fw-medium">{{ $post->user->first_name }} {{ $post->user->last_name }}</span>
                          <span class="mx-1">•</span>
                          <span>{{ $post->created_at->diffForHumans() }}</span>
                        </div>

                        @if ($post->tags && $post->tags->count() > 0)
                          <div class="mb-2">
                            @foreach ($post->tags as $tag)
                              @php
                                $color = normalize_color($tag->color);
                              @endphp
                              <span class="badge me-1"
                                style="background-color: {{ $color ?? 'rgba(102,108,232,1)' }}; color: #fff;">
                                #{{ $tag->name }}
                              </span>
                            @endforeach
                          </div>
                        @endif

                        <div class="d-flex gap-3 text-muted small">
                          <span><i
                              class="icon-base ti ti tabler-thumb-up-filled me-1"></i>{{ $post->likes->count() }}</span>
                          <span><i
                              class="icon-base ti ti tabler-thumb-down-filled me-1"></i>{{ $post->dislikes->count() }}</span>
                          <span><i
                              class="icon-base ti ti tabler-message-circle-filled me-1"></i>{{ $post->comments->count() }}</span>
                          @if ($isAuthor)
                            <span>
                              <i class="icon-base ti ti tabler-flag-filled me-1"></i>{{ $post->reports->count() }}
                            </span>
                          @endif
                          @if ($post->is_pinned)
                            <span class="text-warning"><i class="ti ti tabler-pin-filled"></i></span>
                          @endif
                        </div>
                      </div>



                      @if ($isAuthor || $noComments || (!$canReport && $roleMember))
                        <div class="dropdown">
                          <button class="btn btn-sm p-0 border-0" type="button"
                            id="postActionDropdown{{ $post->id }}" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ti tabler-dots-vertical" style="font-size: 1rem;"></i>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end shadow-sm small"
                            aria-labelledby="postActionDropdown{{ $post->id }}">
                            @if ($isAuthor)
                              <li>
                                <a class="dropdown-item py-1 small"
                                  href="{{ route('posts.edit', [$type, $post->slug]) }}">
                                  <i class="ti tabler-edit me-1"></i> Edit
                                </a>
                              </li>
                              @if ($noComments)
                                <li>
                                  <a class="dropdown-item py-1 small text-danger"
                                    href="{{ route('posts.destroy', $post->uuid) }}">
                                    <i class="ti tabler-trash me-1"></i> Delete
                                  </a>
                                </li>
                              @endif
                            @else
                              @if (!$canReport && $roleMember)
                                <li class="report_{{ $post->id }}">
                                  <a class="dropdown-item py-1 small" href="#"
                                    onclick="openReport({{ $post->id }}, 'post')">
                                    <i class="ti tabler-flag me-1"></i> Report an issue
                                  </a>
                                </li>
                              @endif
                            @endif
                          </ul>
                        </div>
                      @endif
                    </div>
                  </div>
                </div>
              </div>
            @endif
          @empty
            <div class="text-center py-5">
              <i class="fas fa-inbox fa-3x text-muted mb-3 opacity-50"></i>
              <h6 class="text-muted">No posts found</h6>
              <p class="text-muted small">Be the first to create one!</p>
            </div>
          @endforelse
        </div>
      </div>

      {{-- pagination section --}}
      <div class="mt-3">
        {!! $posts->withQueryString()->links('pagination::bootstrap-5') !!}
      </div>
    </div>

    {{-- Right section desktop only --}}
    <div class="d-none d-md-block col-md-3">
      @include('components.forum.right_section')
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

  </div>
  @include('_partials._modals.report_an_issue')

@endsection
