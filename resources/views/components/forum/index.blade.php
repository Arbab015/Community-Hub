<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    @forelse($posts as $post)
      @php
        $isAuthor = auth()->id() === $post->user_id;
        $noComments = $post->comments->count() === 0;
        $reported_post = in_array($post->id, $reportedIds);
        $roleMember = Auth()->user()->hasRole('Society Member');
        $showPost = false;
        $slug = isset($slug) ? $slug : 'my_posts';
        if($slug === 'my_posts') {
        $showPost = !$reported_post && (!$post->blocked || $isAuthor);
        } elseif($slug === 'blocked_posts') {
        $showPost = !$reported_post && $post->blocked;
        } elseif($slug === 'requested_posts') {
        $showPost = !$reported_post && $post->blocked && $post->is_unblock_requested;
    }
      @endphp
      @if($showPost)
        <div class="post-item" data-id="{{ $post->id }}">
          <div class="d-flex gap-3">
            <img src="{{ optional($post->user->attachment)->link
                        ? asset('storage/' . $post->user->attachment->link)
                        : asset('assets/img/avatars/1.png') }}"
                 class="rounded-circle flex-shrink-0" width="40" height="40" alt="User">
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h6 class="mb-1 fw-semibold ">
                    <a href="{{ isset($user_type) ? route('society_posts.view', ['user_type' => $user_type, 'uuid' => $society->uuid, 'type' => $type, 'slug' => $post->slug]) : route('posts.view', ['type' => $type, 'slug' => $post->slug]) }}"
                       class="text-dark text-decoration-none text-break">{{ $post->title }}</a>
                  </h6>
                  <div class="text-muted small mb-2">
                    <span class="fw-medium">{{ $post->user->first_name }} {{ $post->user->last_name }}</span>
                    <span class="mx-1">•</span>
                    <span>{{ $post->created_at->diffForHumans() }}</span>
                    @if($isAuthor)
                      <span class="badge bg-label-info ms-2"> Author </span>
                    @endif
                  </div>
                  @if ($post->tags && $post->tags->count() > 0)
                    <div class="mb-2">
                      @foreach ($post->tags as $tag)
                        @php
                          $color = normalize_color($tag->color);
                        @endphp
                        <span class="badge "
                              style="background-color: {{ $color ?? 'rgba(102,108,232,1)' }}; color: #fff; margin: 1px;">
                                #{{ $tag->name }}
                              </span>
                      @endforeach
                    </div>
                  @endif
                  <div class="d-flex gap-3 text-muted small">
                          <span><i class="icon-base ti ti tabler-thumb-up-filled me-1"></i>{{ $post->likes->count() }}</span>
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
                      <span class="text-warning"><i class="fas fa-thumbtack"></i></span>
                    @endif
                    @if ($post->blocked)
                      <span class="text-warning cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="Blocked">
                      <i class="icon-base ti ti tabler-ban icon-sm text-danger"></i>
                      </span>
                    @endif
                  </div>
                </div>

                @if ($isAuthor || $noComments || (!$reported_post || $roleMember) )
                  <div class="dropdown">
                    <button class="btn btn-sm p-0 border-0" type="button"
                            id="postActionDropdown{{ $post->id }}" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="ti tabler-dots-vertical" style="font-size: 1rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm small"
                        aria-labelledby="postActionDropdown{{ $post->id }}">
                      @can('can_pin')
                        <li>
                          <a class="dropdown-item py-1 small"
                             href="{{ route('posts.pin', $post->uuid) }}">
                            <i class="ti tabler-pin me-1"></i>  {{$post->is_pinned == true ? "Un-pin" : "Pin" }} Post
                          </a>
                        </li>
                      @endcan

                        @can('un-block_post')
                          @if($post->is_unblock_requested)
                            <li>
                              <a class="dropdown-item py-1 small"
                                 href="{{ route('posts.unblock',[$user_type, $uuid, $post->uuid]) }}">
                                <i class="ti tabler-lock-open me-1"></i> Un-block Post
                              </a>
                            </li>
                          @endif
                        @endcan

                      @if($isAuthor)

                        <li>
                          <a class="dropdown-item py-1 small"
                             href="{{ isset($user_type) ? route('posts.edit_in_admin', ['user_type' => $user_type, 'uuid' => $society->uuid,'type' => $type,'slug' => $post->slug]): route('posts.edit', [$type, $post->slug])}}">
                            <i class="ti tabler-edit me-1"></i> Edit
                          </a>
                        </li>
                        @if ($noComments || $post->blocked)
                          <li>
                            <a class="dropdown-item py-1 small text-danger"
                               href="{{ route('posts.destroy', $post->uuid) }}">
                              <i class="ti tabler-trash me-1"></i> Delete
                            </a>
                          </li>
                        @endif

                      @else
                        @if (!$reported_post && $roleMember)
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
        <i class="fas fa-inbox fa-3x text-muted mb-3 opacity-75"></i>
        <h6 class="text-muted">No posts found</h6>
        <p class="text-muted small">Be the first to create one!</p>
      </div>
    @endforelse
  </div>
</div>
