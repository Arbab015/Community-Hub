@extends('layouts/layoutMaster')

@section('title', 'Reports - Post')

@section('content')
  <h4 class="mb-1">Reports Details</h4>

  <nav aria-label="breadcrumb" class="pt-2 pb-3">
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
    {{-- Reported Item Preview --}}
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header d-flex justify-content-between pb-0">
          <div class="d-flex">
            <h5 class=" fw-bold">
              View Post Details
            </h5>
            @php
              $category = $post->category . 's';
            @endphp
            <a class="cursor-pointer " data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="View Post"
              href="{{ route('posts.view', ['type' => $category, 'slug' => $post->slug, 'report' => 'yes']) }}">
              <i class="ms-3 ti ti tabler-external-link me-1 fs-4"></i>
            </a>
          </div>
          @if ($post->status == false)
            <div>
              <form action="{{ route('reports.action', ['type' => 'post', $post->id]) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm">
                  <i class="ti tabler-shield-x me-1"></i>
                  Block Post
                </button>
              </form>
            </div>
          @endif
        </div>
        <div class="card-body pt-0">
          <div class="text-muted small mb-2 fst-italic">
            By <strong>{{ $post->user->first_name }} {{ $post->user->last_name }}</strong>
            <span class=" ms-3 badge bg-primary small "> {{ $post->category }} </span>
          </div>
          <hr />
          <div class=" fw-bold d-flex justify-content-between align-items-center">
            <span>Reports ({{ $reports->count() }})</span>
          </div>

          @forelse($reports as $report)
            <div class="d-flex justify-content-between align-items-start p-3 border-bottom">
              <div class="d-flex gap-3">
                <img
                  src="{{ optional($report->user->attachment)->link ? asset('storage/' . $report->user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                  class="rounded-circle" width="36" height="36" alt="User">
                <div>
                  <div class="fw-semibold small">
                    {{ $report->user->first_name }} {{ $report->user->last_name }}
                  </div>
                  <div class="text-muted small">{{ $report->created_at->diffForHumans() }}</div>
                  <span class="badge bg-label-warning text-wrap"
                    style="max-width: 100%; word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                    {{ $report->reason }}
                  </span>
                  <p class="mb-0 mt-1 small text-muted">{{ $report->type }}</p>
                </div>
              </div>

              {{-- Dismiss single report --}}
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


          {{-- <div class="text-muted small mb-2">
              By <strong>{{ $post->user->first_name }} {{ $post->user->last_name }}</strong>
              · {{ $post->created_at->diffForHumans() }}
            </div>
            <p class="mb-0">{{ $post->body }}</p> --}}

        </div>
      </div>
    </div>


  </div>
@endsection
