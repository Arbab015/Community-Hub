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
    {{-- Reported Post --}}
    <div class="col-12">
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header d-flex justify-content-between align-items-center pb-2">
          <div class="d-flex align-items-center">
            <h5 class="fw-bold mb-0">View Post Details</h5>
            @php
              $category = $post->category . 's';
            @endphp
            <a class="ms-3" data-bs-toggle="tooltip" data-bs-placement="top" title="View Post"
               href="{{ route('posts.view', ['type' => $category, 'slug' => $post->slug, 'report' => 'yes']) }}">
              <i class="ti tabler-external-link fs-5"></i>
            </a>
          </div>

          @if($post->reports->count() > 0)
            <form action="{{ route('reports.action', ['type' => 'post', $post->id]) }}" method="POST">
              @csrf
              <button type="submit" class="btn btn-danger btn-sm">
                <i class="ti tabler-shield-x me-1"></i> Block Post
              </button>
            </form>
          @endif
        </div>

        <div class="card-body pt-3 ">
          <div class="text-muted small mb-3 fst-italic">
            By <strong>{{ $post->user->first_name }} {{ $post->user->last_name }}</strong>
            <span class="ms-3 badge bg-primary">{{ $post->category }}</span>
          </div>

          <hr class="my-3">
          <h6 class="fw-semibold mb-3">Reports ({{ $reports->count() }})</h6>

          @forelse($reports as $report)
            <div class="d-flex justify-content-between align-items-start border-bottom py-3">
              <div class="d-flex gap-3">
                <img src="{{ optional($report->user->attachment)->link ? asset('storage/' . $report->user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                     class="rounded-circle" width="40" height="40" alt="User">
                <div>
                  <div class="fw-semibold small">{{ $report->user->first_name }} {{ $report->user->last_name }}</div>
                  <div class="text-muted small">{{ $report->created_at->diffForHumans() }}</div>
                  <span class="badge bg-warning d-inline-block text-break"
                        style="max-width: 100%;">
                        {{ $report->reason }}
                      </span>
                  <p class="mb-0 mt-1 small text-muted">{{ $report->type }}</p>
                </div>


              </div>

              <form action="{{ route('reports.dismiss', $report->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="confirmDelete(event)">
                  <i class="ti tabler-x me-1"></i> Delete
                </button>
              </form>
            </div>

          @empty
            <div class="text-center py-5">
              <h6 class="text-muted">No reports found</h6>
            </div>
          @endforelse
        </div>
      </div>

      {{-- Reported Comments --}}
      <div class="card shadow-sm border-0 mb-5">
        <div class="card-header pb-2">
          <h5 class="fw-bold mb-2">Reported Comments</h5>
        </div>
        <div class="card-body pt-3 ">

          @foreach($reportedComments as $comment)
            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="fw-semibold">{{ $comment->message }}</div>
                <a class="btn btn-danger btn-sm">Delete</a>
              </div>

              @if($comment->attachment)
                <div class="mb-3">
                  <img src="{{ asset('storage/' . $comment->attachment->link) }}" class="img-fluid rounded-3 border w-50"
                       alt="Comment attachment">
                </div>
              @endif

              <div class="fw-semibold mb-2">Reports ({{ $comment->reports->count() }})</div>

              @foreach($comment->reports as $report)
                <div class="d-flex justify-content-between align-items-start border-bottom py-3">
                  <div class="d-flex gap-3">
                    <img src="{{ optional($report->user->attachment)->link ? asset('storage/' . $report->user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                         class="rounded-circle" width="40" height="40" alt="User">
                    <div>
                      <div class="fw-semibold small">{{ $report->user->first_name }} {{ $report->user->last_name }}</div>
                      <div class="text-muted small">{{ $report->created_at->diffForHumans() }}</div>
                      <span class="badge bg-warning text-wrap">{{ $report->reason }}</span>
                      <p class="mb-0 mt-1 small text-muted">{{ $report->type }}</p>
                    </div>
                  </div>
                  <form action="{{ route('reports.dismiss', $report->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="confirmDelete(event)">
                      <i class="ti tabler-x me-1"></i> Delete
                    </button>
                  </form>
                </div>
              @endforeach

            </div>
          @endforeach

          @if($reportedComments->isEmpty())
            <div class="text-center py-4">
              <h6 class="text-muted">No reported comments found</h6>
            </div>
          @endif

        </div>
      </div>
    </div>
  </div>
@endsection
