@php
  $posts = match ($type) {
      'discussions' => $discussions,
      'suggestions' => $suggestions,
      'issues' => $issues, // default => collect(),
  };
@endphp
<div class="row g-5">
  <div class="col-md-9 col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        @forelse($posts as $post)
          <div class="post-item p-3 border-bottom" data-id="{{ $post->id }}">
            <div class="d-flex gap-3">
              <img
                src="{{ optional($post->user->attachment)->link ? asset('storage/' . $post->user->attachment->link) : asset('assets/img/avatars/1.png') }}"
                class="rounded-circle" width="40" height="40" alt="User">
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 class="mb-1 fw-semibold">
                      <a href="{{ route('posts.view', [$type, $post->slug]) }}" class="text-dark text-decoration-none"
                        style="word-wrap: break-word; overflow-wrap: break-word; display: inline-block;">
                        {{ $post->title }}
                      </a>
                    </h6>

                    <div class="text-muted small mb-2">
                      <span class="fw-medium">{{ $post->user->first_name }} {{ $post->user->last_name }}</span>
                      <span class="mx-1">•</span>
                      <span>{{ $post->created_at->diffForHumans() }}</span>
                    </div>

                    @if ($post->tags && $post->tags->count() > 0)
                      <div class="mb-2">
                        @foreach ($post->tags as $tag)
                          @php $color = normalize_color($tag->color); @endphp
                          <span class="badge me-1"
                            style="background-color: {{ $color ?? 'rgba(102,108,232,1)' }}; color: #fff;">
                            # {{ $tag->name }}
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
                      @if ($post->is_pinned)
                        <span class="text-warning"><i class="fas fa-thumbtack"></i></span>
                      @endif
                    </div>
                  </div>

                  <div class="dropdown">
                    <button class="btn btn-sm p-0 border-0" type="button" id="postActionDropdown{{ $post->id }}"
                      data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="ti tabler-dots-vertical" style="font-size: 1rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm small"
                      aria-labelledby="postActionDropdown{{ $post->id }}">
                      @if (auth()->id() === $post->user_id)
                        <li>
                          <a class="dropdown-item py-1 small" href="{{ route('posts.edit', [$type, $post->uuid]) }}">
                            <i class="ti tabler-edit me-1"></i> Edit
                          </a>
                        </li>
                        @if ($post->comments->count() === 0)
                          <li>
                            <a class="dropdown-item py-1 small text-danger"
                              href="{{ route('posts.destroy', $post->uuid) }}">
                              <i class="ti tabler-trash me-1"></i> Delete
                            </a>
                          </li>
                        @endif
                      @else
                        @cannot('report_actions')
                          <li
                            class="already_reported_{{ $post->id }} @if (!in_array($post->id, $reportedIds)) d-none @endif">
                            <span class="dropdown-item py-1 small text-muted disabled">
                              <i class="ti tabler-flag me-1"></i>Reported
                            </span>
                          </li>
                          @if (!in_array($post->id, $reportedIds))
                            <li class="report_{{ $post->id }}">
                              <a class="dropdown-item py-1 small" href="#"
                                onclick="openReport({{ $post->id }}, 'post')">
                                <i class="ti tabler-flag me-1"></i> Report
                              </a>
                            </li>
                          @endif
                        @endcan
                      @endif
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        @empty
          <div class="text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3 opacity-50"></i>
            <h6 class="text-muted">No {{ $type }} found</h6>
            <p class="text-muted small">Be the first to create one!</p>
          </div>
        @endforelse
      </div>
    </div>

    <div class="mt-3">
      {!! $posts->withQueryString()->links('pagination::bootstrap-5') !!}
    </div>

  </div>

  @include('components.forum.right_section')

  @include('_partials._modals.report_an_issue')
</div>
