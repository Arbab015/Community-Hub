@extends('layouts/layoutMaster')
@section('title', 'Reports - Post')
@section('content')
  <h4 class="mb-4">Reports Details</h4>
  <nav aria-label="breadcrumb" class="pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('reports.index') }}">Reports</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Details</li>
    </ol>
  </nav>

  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-12">
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header d-flex justify-content-between align-items-center py-3">
          <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold mb-0">Post Details</h5>
            @php $category = $post->category . 's'; @endphp
            <a data-bs-toggle="tooltip" data-bs-placement="top" title="View Post"
               href="{{ route('posts.view', ['type' => $category, 'slug' => $post->slug, 'report' => 'yes']) }}">
              <i class="ti tabler-external-link fs-5"></i>
            </a>
          </div>

          @if($post->reports->count() > 0)
            <form action="{{ route('reports.action', ['type' => 'post', $post->id]) }}" method="POST">
              @csrf
              <button type="submit" class="btn btn-danger btn-sm btn_responsive">
                <i class="ti tabler-shield-x me-1 d-none d-md-inline"></i> Block Post
              </button>
            </form>
          @endif
        </div>

        <div class="card-body">
          <div class="text-muted small mb-3 ">
            By <strong class="fst-italic">{{ $post->user->first_name }} {{ $post->user->last_name }}</strong>
            <span class="ms-2 badge bg-primary">{{ $post->category }}</span>
          </div>
          <hr class="my-3">
          <h6 class="fw-semibold ">
            <i class="ti tabler-flag-filled me-1 text-danger icon-sm"></i>
            Post Reports
            <span class="badge bg-danger ms-1 small badge_count">{{ $reports->count() }}</span>
          </h6>

          @forelse($reports as $report)
            <div class="d-flex justify-content-between align-items-start border-bottom py-3">
              <div class="d-flex gap-3">
                <img src="{{ optional($report->user->attachment)->link ? asset('storage/' . $report->user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                     class="rounded-circle flex-shrink-0" width="40" height="40" alt="User">
                <div class="w-100">
                  <div class="fw-semibold small">{{ $report->user->first_name }} {{ $report->user->last_name }}</div>
                  <div class="text-muted small mb-1">{{ $report->created_at->diffForHumans() }}</div>
                  <span class="badge bg-light text-dark text-wrap d-inline-block mw-100" >
                    {{ $report->reason }}
                  </span>
                </div>
              </div>

              <form action="{{ route('reports.dismiss', $report->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="confirmDelete(event)">
                  <i class="ti tabler-trash text-danger me-1"></i> <span class="d-none d-sm-block"> Delete </span>
                </button>
              </form>
            </div>
          @empty
            <div class="text-center py-4">
              <i class="ti tabler-message-report fs-1 text-muted  mb-2"></i>
              <h6 class="text-muted">No reports found</h6>
            </div>
          @endforelse
        </div>
      </div>

      @php
        $totalCommentReports = $reportedComments->sum(fn($c) => $c->reports->count());
      @endphp

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">
{{--          <i class="ti tabler-message-report-filled me-1 text-warning icon-sm"></i>--}}
          Reported Comments
        </h5>
        <span class="badge bg-warning text-dark fs-6">
          {{ $totalCommentReports }} Report{{ $totalCommentReports !== 1 ? 's' : '' }}
        </span>
      </div>

      @forelse($reportedComments as $comment)
        <div class="card shadow-sm border-0 mb-5">

          {{-- Comment Card Header --}}
          <div class="card-header d-flex justify-content-between align-items-center py-3 bg-light mb-2">
            <div class="d-flex align-items-center gap-2">
              <img src="{{ optional($comment->user->attachment)->link ? asset('storage/' . $comment->user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                   class="rounded-circle  " width="36" height="36" alt="Commenter">
              <div>
                <div class="fw-semibold small">
                  {{ ucfirst($comment->user->first_name)}}  {{ ucfirst($comment->user->last_name) }}
                </div>
                <div class="text-muted fs-xs" >
                  {{ $comment->created_at->diffForHumans() }}
                </div>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-danger btn-sm btn_responsive">
                <i class="ti tabler-flag-filled me-1"></i>
                <span class="d-none d-sm-inline">Report{{ $comment->reports->count() !== 1 ? 's' : '' }}</span>
                ({{ $comment->reports->count() }})
              </button>
              <form action="{{ route('reports.action', ['type' => 'comment', $comment->id]) }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm btn_responsive">
                  <i class="ti tabler-trash me-1 d-inline d-sm-none"></i>
                  <span class="d-none d-sm-inline">Delete Comment</span>
                </button>
              </form>
            </div>
          </div>

          {{-- Comment Content --}}
          <div class="card-body ">
            <p class="mb-3 fst-italic text-muted">{{ $comment->message }}</p>

            @if($comment->attachment)
              <div class="mb-3">
                <img src="{{ asset('storage/' . $comment->attachment->link) }}"
                     class="img-fluid rounded-3 border"
                     style="max-width: 250px; max-height: 200px; object-fit: cover;"
                     alt="Comment attachment">
              </div>
            @endif

            {{-- Reports for this comment --}}
            <hr class="mt-2 mb-3">
            <h6 class="fw-semibold small text-muted mb-3 text-uppercase tracking-wide">
              <i class="ti tabler-flag-filled me-1"></i>
              Reports on this Comment
            </h6>

            @foreach($comment->reports as $report)
              <div class="d-flex justify-content-between align-items-start py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                <div class="d-flex gap-3 flex-grow-1">
                  <img src="{{ optional($report->user->attachment)->link ? asset('storage/' . $report->user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                       class="rounded-circle flex-shrink-0" width="36" height="36" alt="Reporter">
                  <div>
                    <div class="fw-semibold small">
                      {{ $report->user->first_name }} {{ $report->user->last_name }}
                    </div>
                    <div class="text-muted small mb-1">{{ $report->created_at->diffForHumans() }}</div>
                    <span class="badge bg-light text-dark text-wrap text-break d-inline-block w-100 text-start">{{ $report->reason }}</span>
                  </div>
                </div>
                <form action="{{ route('reports.dismiss', $report->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="confirmDelete(event)">
                    <i class="ti tabler-x me-1 "></i> <span class="d-none d-sm-block"> Dismiss  </span>
                  </button>
                </form>
              </div>
            @endforeach
          </div>
        </div>
      @empty
        <div class="card shadow-sm border-0">
          <div class="card-body text-center py-5">
            <i class="ti tabler-message-check fs-1 text-muted  mb-2"></i>
            <h6 class="text-muted">No reported comments found</h6>
          </div>
        </div>
      @endforelse

    </div>
  </div>
@endsection

@push('styles')
  <style>
    .badge_count{
      font-size: 12px;
    }

    @media (max-width: 576px) {
      .btn_responsive{
        padding: 0.153rem 0.75rem;
        font-size: 0.6875rem;
        border-radius: 0.125rem;
      }

      .btn_responsive i{
        font-size: 0.91rem;
      }
    }


  </style>
@endpush
