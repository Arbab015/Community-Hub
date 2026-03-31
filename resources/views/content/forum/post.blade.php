@extends('layouts/layoutMaster')
@section('title', 'Forum - ' . ucfirst($type ?? 'All Posts'))

@section('content')
  @php
    $reaction = $post->userReaction?->type;
  @endphp
  <div class="d-flex justify-content-between">
      <h4 class="mb-1">
        {{ isset($user_type) ? 'Society Post Details' : (isset($request_on) ? 'Post Details' : ucfirst($type)) }}
      </h4>
    @if (!$request_on)
    <div class="d-md-none">
      <button class="btn p-0 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#forumRightOffcanvas"
              aria-controls="forumRightOffcanvas">
        <i class="icon-base ti tabler-menu-2 icon-md"></i>
      </button>
    </div>
    @endif
  </div>

  <nav aria-label="breadcrumb" class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      @if ($request_on)
        @if($request_on == 'report')
        <li class="breadcrumb-item">
          <a href="{{ route('reports.index') }}">Reports</a>
          <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
        </li>
        <li class="breadcrumb-item">
          <a href="{{ route('reports.show', $post->id) }}">Details</a>
          <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
        </li>
        @else
          <li class="breadcrumb-item">
            <a href="{{ route('requests.index', $in_society ? $society->uuid : null) }}">Requested Posts</a>
            <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
          </li>
        @endif
      @elseif(isset($user_type))
        <li class="breadcrumb-item">
          <a href="{{ route('societies.index', $user_type) }}">Societies</a>
          <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
        </li>
        <li class="breadcrumb-item">
          <a href="{{ route('societies.show', [$user_type, $society->uuid]) }}">Soceity</a>
          <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
        </li>
      @else
        <li class="breadcrumb-item">
          <a href="{{ route('posts.index', $type) }}">{{ ucfirst($type) }}</a>
          <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
        </li>
      @endif
      <li class="breadcrumb-item active">Post</li>
    </ol>
  </nav>

  <!-- Toast Container -->
  <div aria-live="polite" aria-atomic="true" class="position-relative">
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
      @if (session('success'))
        <div class="toast align-items-center  show" role="alert" aria-live="assertive" aria-atomic="true">
          <div class="toast-header">
            <i class="icon-base ti tabler-bell icon-xs me-2 text-success"></i>
            <div class="me-auto fw-medium">Message</div>
            <small>{{ now()->diffForHumans() }}</small>
            <button type="button" class="btn-close " data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
          <div class="toast-body">
            {{ session('success') }}
          </div>
        </div>
      @endif

      @if (session('error'))
        <div class="toast align-items-center  show" role="alert" aria-live="assertive" aria-atomic="true">
          <div class="toast-header">
            <i class="icon-base ti tabler-bell icon-xs me-2 text-danger"></i>
            <div class="me-auto fw-medium">Message</div>
            <small>{{ now()->diffForHumans() }}</small>
            <button type="button" class="btn-close " data-bs-dismiss="toast" aria-label="Close"></button>
          </div>

          <div class="toast-body">
            {{ session('error') }}
          </div>

        </div>
      @endif
    </div>
  </div>

  <div class="row g-5">
    {{-- Left Section --}}
    <div class="@if (!$request_on) col-md-9 @endif col-12">
      {{-- Post Card --}}
      <div class="card border-0 shadow-sm mb-5">
        <div class="card-body py-2 py-4 px-6 ps-5">
          <div class="flex-grow-1">
            @php
               $isAuthor = auth()->id() === $post->user_id;
              $noComments = $post->comments->count() === 0;
              $canReport = in_array($post->id, $reportedIds);
              $roleMember = Auth()->user()->hasRole('Society Member');
            @endphp
            <div class="d-flex justify-content-between">
              <div class=" w-100">
                {{-- Title --}}
                <div class="d-flex">
                  <h6 class="fs-5 fw-bolder mb-1 text-break">{{ ucfirst($post->title) }}</h6>
                  @if ($post->status == 'close')
                    <span class="ms-3 badge bg-warning"> Blocked </span>
                  @endif
                </div>

                {{-- Tags --}}
                <div class="d-flex justify-content-between mt-2">
                  @if ($post->tags)
                    <div class="mb-2 tags">
                      @foreach ($post->tags as $tag)
                        @php
                          $color = normalize_color($tag->color);
                        @endphp
                        <span class="badge "
                              style="background-color: {{ $color ?? 'rgba(102,108,232,1)' }}; color: #fff; margin: 1px;">
                        # {{ $tag->name }}
                      </span>
                      @endforeach
                    </div>
                  @endif

                  {{-- Actions --}}
                  <div class="d-flex gap-3 text-muted small mb-2 post-status">
                  <span>
                    <i class="post_react icon-base ti ti tabler-thumb-up-filled me-1"></i>{{ $post->likes->count() }}
                  </span>
                    <span>
                    <i
                      class=" post_react icon-base ti ti tabler-thumb-down-filled me-1"></i>{{ $post->dislikes->count() }}
                  </span>
                    <span>
                    <i class="icon-base ti ti tabler-message-circle-filled me-1"></i>{{ $post->comments->count() }}
                  </span>
                    @if (auth()->id() === $post->user_id)
                      <span>
                      <i class="icon-base ti ti tabler-flag-filled me-1"></i>{{ $post->reports->count() }}
                    </span>
                    @endif
                    @if ($post->is_pinned)
                      <span class="text-warning cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="Pinned">
                      <i class="fas fa-thumbtack"></i>
                    </span>
                    @endif
                    @if ($post->blocked)
                        <span class="text-warning cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="Blocked">
                      <i class="icon-base ti ti tabler-ban icon-sm text-danger"></i>
                      </span>
                      @endif
                  </div>
                </div>
              </div>

              @if ($isAuthor || $noComments || (!$canReport || $roleMember))
                <div class="dropdown">
                  <button class="btn btn-sm p-0 border-0" type="button" id="postActionDropdown{{ $post->id }}"
                          data-bs-toggle="dropdown" aria-expanded="false">
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

                      @can('un-block_request_post')
                        @if($post->blocked && $isAuthor && $is_updated)
                          <li>
                            <a class="dropdown-item py-1 small"
                               href="{{ route('posts.unblock_request', $post->uuid) }}">
                              <i class="ti tabler-send me-1"></i> Request for Un-block
                            </a>
                          </li>
                        @endif
                      @endcan

{{--                      @can('un-block_post')--}}
{{--                        @if($post->is_unblock_requested)--}}
{{--                          <li>--}}
{{--                            <a class="dropdown-item py-1 small"--}}
{{--                               href="{{ route('posts.unblock',[$user_type, $uuid, $post->uuid]) }}">--}}
{{--                              <i class="ti tabler-lock-open me-1"></i> Un-block Post--}}
{{--                            </a>--}}
{{--                          </li>--}}
{{--                        @endif--}}
{{--                      @endcan--}}

                    @if ($isAuthor)
                      <li>
                        <a class="dropdown-item py-1 small" href="{{ isset($user_type) ? route('posts.edit_in_admin', [$user_type, $society->uuid, $type,  $post->slug]) :  route('posts.edit', [$type, $post->slug]) }}">
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

      {{-- Post Detail --}}
      <div class="card border-0 shadow-sm" id="post_detail_section">
        <div class="card-body post_details">
          {{-- Header --}}
          <div class="d-flex mb-4">
            <img
              src="{{ optional($post->user->attachment)->link
                  ? asset('storage/' . $post->user->attachment->link)
                  : asset('assets/img/avatars/1.png') }}"
              class="rounded-circle" width="45" height="45" alt="User">
            <div class="ms-2">
              <h6 class="mb-0 fw-semibold">{{ ucfirst($post->user->first_name) }} {{ ucfirst($post->user->last_name) }}
              </h6>
              <small class="text-muted">{{ $post->created_at->format('F d, Y h:i A') }}</small>
            </div>
          </div>

          <hr class="my-4">
          {{-- Description (Quill HTML) --}}
          <div class="mt-3">
            {!! $post->description !!}
          </div>

          {{-- Action Buttons --}}
          <div class="d-flex justify-content-between mb-3 px-2 post-actions">
            <div>
              <span class="post_react me-3 cursor-pointer" data-id="{{ $post->id }}" data-type="post"
                    data-reaction="like">
                <i
                  class="icon-base ti {{ $reaction === 'like' ? 'tabler-thumb-up-filled' : 'tabler-thumb-up' }} react-like-icon me-1"></i>
                <span class="post-like-count">{{ $post->likes->count() }}</span>
              </span>
              <span class="post_react cursor-pointer" data-id="{{ $post->id }}" data-type="post"
                    data-reaction="dislike">
                <i
                  class="icon-base ti
                 {{ $reaction === 'dislike' ? 'tabler-thumb-down-filled' : 'tabler-thumb-down' }}
                  react-dislike-icon me-1"></i>
                <span class="post-dislike-count">{{ $post->dislikes->count() }}</span>
              </span>
            </div>
            <div class="">
              <span type="button" class="reply-btn cursor-pointer post-reply-btn">
                <i class="icon-base ti ti tabler-message-circle me-1"></i>Reply
              </span>
            </div>
          </div>

          {{-- Comment Box Placeholder --}}
          <div class="m-2" id="comment_box_show"></div>
          <hr class="mb-4">

        </div>
      </div>

      {{-- all comments section --}}
      <div class="d-flex justify-content-between my-5 ">
        <div class="text-dark">
          {{ $comments->count() }} comments
        </div>

        <div>
          <form method="GET" id="commentSortForm">
            @foreach (request()->except('sort', 'page') as $key => $value)
              <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <div class="btn-group">
              <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                      aria-expanded="false">
                {{ request('sort') === 'oldest' ? 'Oldest' : 'Latest' }}
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <button type="submit" name="sort" value="latest" class="dropdown-item">
                    Latest
                  </button>
                </li>
                <li>
                  <button type="submit" name="sort" value="oldest" class="dropdown-item">
                    Oldest
                  </button>
                </li>
              </ul>
            </div>
          </form>
        </div>
      </div>

      @if($comments->count() > 0)
        <div class="card border-0 shadow-sm p-0">
          <div class="card-body post_details">
            {{-- All comments --}}
            @foreach ($comments as $comment)
              @if (!in_array($comment->id, $reportedIds))
                @include('components.forum.comment_item', [
                    'comment' => $comment,
                    'level' => 0,
                ])
                <hr class="my-4">
              @endif
            @endforeach
            {{-- pagination section --}}
            <div class="mt-3">
              {!! $comments->withQueryString()->links('pagination::bootstrap-5') !!}
            </div>
          </div>
        </div>
      @else
        <div class="card border-0 shadow-sm">
          <div class="card-body">
        <div class="text-center">
          <i class="fas fa-inbox fa-3x text-muted mb-3 opacity-75"></i>
          <h6 class="text-muted mb-1">No Comments found</h6>
          <p class="text-muted small">Be the first to create one!</p>
        </div>
          </div>
        </div>
        @endif

    </div>
    @if (!$request_on)
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
    @endif
  </div>
  @include('_partials._modals.report_an_issue')

  {{-- Comment Box --}}
  @include('components.forum.comment_box')

@endsection

@push('styles')
  <style>
    .post_details {
      padding: 40px;
    }

    @media (max-width: 576px) {
      .post_details {
        padding: 16px !important;
      }

      .comment-item .ms-6,
      .comment-item .ps-6 {
        margin-left: 0 !important;
        padding-left: 0 !important;
      }

      .comment-item {
        margin-bottom: 16px !important;
      }

      .comment-item p {
        word-break: break-word;
      }

      .comment-item img.img-fluid {
        width: 70% !important;
        height: auto !important;
      }

      #comment_section {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1055;
        margin: 0 !important;
        border-radius: 12px 12px 0 0;
        box-shadow: 0 -6px 20px rgba(0, 0, 0, .15);
        background: #fff;
        max-height: 75vh;
        overflow-y: auto;
        padding-bottom: env(safe-area-inset-bottom);
      }

      body {
        padding-bottom: 90px;
      }

      #comment_section img {
        width: 32px;
        height: 32px;
      }

      #emoji-picker-container {
        position: fixed !important;
        bottom: 80px;
        right: 16px;
        left: auto;
      }
    }
  </style>
@endpush

@push('scripts')
  <script type="module">
    import 'https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js';

    const emojiBtn = document.getElementById('emoji-btn');
    const textarea = document.getElementById('comment-textarea');
    const container = document.getElementById('emoji-picker-container');
    const picker = document.createElement('emoji-picker');
    container.appendChild(picker);

    emojiBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    });

    picker.addEventListener('emoji-click', event => {
      textarea.value += event.detail.unicode;
      textarea.focus();
    });

    document.addEventListener('click', function(e) {
      if (!container.contains(e.target) && !emojiBtn.contains(e.target)) {
        container.style.display = 'none';
      }
    });

    // Show comment box
    const parentIdInput = document.getElementById('parent_id');
    const commentImageInput = document.getElementById('comment-image');
    const previewContainer = document.getElementById('comment-image-preview');
    const imageLabel = document.getElementById('comment-image-label');

    function clearImagePreview() {
      if (previewContainer) previewContainer.innerHTML = '';
      if (commentImageInput) commentImageInput.value = '';
      if (imageLabel) imageLabel.style.display = 'flex';
    }

    function showImagePreview(src, isExisting = false) {
      if (!previewContainer) return;
      previewContainer.innerHTML = '';
      if (imageLabel) imageLabel.style.display = 'none';

      const wrapper = document.createElement('div');
      wrapper.className = 'position-relative';
      wrapper.innerHTML = `
    <img src="${src}" class="rounded border" style="width:60px;height:60px;object-fit:cover;">
    <span class="remove-preview-btn" style="position:absolute;top:-5px;right:-5px;background:rgba(0,0,0,0.6);color:#fff;border-radius:50%;cursor:pointer;padding:2px 6px;font-size:14px;">&times;</span>
  `;
      previewContainer.appendChild(wrapper);

      wrapper.querySelector('.remove-preview-btn').addEventListener('click', () => {
        clearImagePreview();
      });
    }

    document.querySelectorAll('.reply-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const commentSection = document.getElementById('comment_section');
        const submitBtn = commentSection.querySelector('button[type="submit"]');
        const parentIdInput = document.getElementById('parent_id');
        const editIdInput = document.getElementById('edit_id');

        const commentItem = this.closest('.comment-item');
        const replyBox = commentItem ? commentItem.querySelector('.comment-reply-box') : null;
        const authorEl = commentItem ? commentItem.querySelector('.comment-author') : null;

        if (!replyBox || !authorEl) return;

        editIdInput.value = '';
        parentIdInput.value = commentItem.dataset.commentId;

        textarea.placeholder = `Reply to: ${authorEl.textContent.trim()}`;
        submitBtn.textContent = 'Reply';

        clearImagePreview();

        replyBox.appendChild(commentSection);
        commentSection.classList.remove('d-none');
        textarea.focus();
      });
    });

    document.querySelector('.post-reply-btn')?.addEventListener('click', () => {
      const commentSection = document.getElementById('comment_section');
      const parentIdInput = document.getElementById('parent_id');
      const editIdInput = document.getElementById('edit_id');
      const submitBtn = commentSection.querySelector('button[type="submit"]');
      parentIdInput.value = '';
      editIdInput.value = '';

      textarea.placeholder = 'Write a comment...';
      submitBtn.textContent = 'Comment';

      clearImagePreview();

      document.getElementById('comment_box_show').appendChild(commentSection);
      commentSection.classList.remove('d-none');
      textarea.focus();
    });

    textarea.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = this.scrollHeight + 'px';
    });

    document.querySelector('.cancel_btn')?.addEventListener('click', () => {
      ['parent_id', 'edit_id'].forEach(id => document.getElementById(id).value = '');
      textarea.value = '';
      textarea.style.height = 'auto';
      textarea.placeholder = 'Write a comment...';
      clearImagePreview();
      document.getElementById('comment_section').classList.add('d-none');
    });

    if (commentImageInput) {
      commentImageInput.addEventListener('change', e => {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = ev => {
            showImagePreview(ev.target.result, false);
          };
          reader.readAsDataURL(file);
        }
      });
    }

    // Post & Comment Reactions — single delegated function for both static and dynamic
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.post_react, .comment_react');
      if (!btn) return;

      const id = btn.dataset.id;
      const type = btn.dataset.type;
      const reaction = btn.dataset.reaction;

      fetch("{{ route('react') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': "{{ csrf_token() }}"
        },
        body: JSON.stringify({
          reactionable_id: id,
          reactionable_type: type,
          type: reaction
        })
      })
        .then(res => res.json())
        .then(res => {
          if (!res.status) return;
          const parent = btn.closest('.comment-actions, .post-actions');
          if (!parent) return;
          const likeCount = parent.querySelector('.post-like-count, .comment-like-count');
          const dislikeCount = parent.querySelector('.post-dislike-count, .comment-dislike-count');
          const likeIcon = parent.querySelector('.react-like-icon');
          const dislikeIcon = parent.querySelector('.react-dislike-icon');

          let like = parseInt(likeCount.textContent);
          let dislike = parseInt(dislikeCount.textContent);

          likeIcon.classList.remove('tabler-thumb-up-filled');
          likeIcon.classList.add('tabler-thumb-up');
          dislikeIcon.classList.remove('tabler-thumb-down-filled');
          dislikeIcon.classList.add('tabler-thumb-down');

          const updates = {
            added: {
              like: [1, 0],
              dislike: [0, 1]
            },
            removed: {
              like: [-1, 0],
              dislike: [0, -1]
            },
            updated: {
              like: [1, -1],
              dislike: [-1, 1]
            }
          };

          const [likeChange, dislikeChange] = updates[res.status][reaction];

          likeCount.textContent = like + likeChange;
          dislikeCount.textContent = dislike + dislikeChange;

          if ((res.status === 'added' || res.status === 'updated') && reaction === 'like') {
            likeIcon.classList.remove('tabler-thumb-up');
            likeIcon.classList.add('tabler-thumb-up-filled');
          }
          if ((res.status === 'added' || res.status === 'updated') && reaction === 'dislike') {
            dislikeIcon.classList.remove('tabler-thumb-down');
            dislikeIcon.classList.add('tabler-thumb-down-filled');
          }

          if (type === 'post') {
            const topCard = document.querySelector('.post-status');
            if (topCard) {
              const spans = topCard.querySelectorAll('span');
              if (likeChange && spans[0]) {
                const currentLike = parseInt(spans[0].textContent.match(/\d+/)[0]);
                spans[0].innerHTML =
                  `<i class="icon-base ti ti tabler-thumb-up-filled me-1"></i>${currentLike + likeChange}`;
              }
              if (dislikeChange && spans[1]) {
                const currentDislike = parseInt(spans[1].textContent.match(/\d+/)[0]);
                spans[1].innerHTML =
                  `<i class="icon-base ti ti tabler-thumb-down-filled me-1"></i>${currentDislike + dislikeChange}`;
              }
            }
          }
        })
        .catch(err => console.error(err));
    });

    // Edit comment - Fixed version
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.edit-comment-btn').forEach(btn => {
        btn.addEventListener('click', e => {
          e.preventDefault();
          e.stopPropagation();
          const commentId = btn.dataset.id;
          const commentItem = document.querySelector(`.comment-item[data-comment-id="${commentId}"]`);
          if (!commentItem) return;
          const commentBox = document.getElementById('comment_section');
          const textarea = document.getElementById('comment-textarea');
          const editIdInput = document.getElementById('edit_id');
          const parentIdInput = document.getElementById('parent_id');
          const submitBtn = commentBox.querySelector('button[type="submit"]');
          const previewContainer = document.getElementById('comment-image-preview');
          const imageLabel = document.getElementById('comment-image-label');
          editIdInput.value = commentId;
          parentIdInput.value = '';
          const commentText = commentItem.querySelector('p.mb-2')?.textContent.trim() || '';
          textarea.value = commentText;
          textarea.style.height = 'auto';
          textarea.style.height = textarea.scrollHeight + 'px';
          const existingImage = commentItem.querySelector('.my-2 img');
          if (previewContainer && imageLabel) {
            if (existingImage) {
              imageLabel.style.display = 'none';
              previewContainer.innerHTML = `<div class="position-relative">
            <img src="${existingImage.src}" class="rounded border" style="width:60px;height:60px;object-fit:cover;">
            <span class="remove-preview-btn" style="position:absolute;top:-5px;right:-5px;background:rgba(0,0,0,0.6);color:#fff;border-radius:50%;cursor:pointer;padding:2px 6px;font-size:14px;">&times;</span>
          </div>`;
              previewContainer.querySelector('.remove-preview-btn').addEventListener('click', () => {
                previewContainer.innerHTML = '';
                document.getElementById('comment-image').value = '';
                imageLabel.style.display = 'flex';
              });
            } else {
              previewContainer.innerHTML = '';
              document.getElementById('comment-image').value = '';
              imageLabel.style.display = 'flex';
            }
          }

          submitBtn.textContent = 'Update';

          const replyBox = commentItem.querySelector('.comment-reply-box');
          if (replyBox) {
            replyBox.appendChild(commentBox);
          }

          commentBox.classList.remove('d-none');

          if (window.innerWidth <= 576) {
            commentBox.style.zIndex = '1055';
          }

          textarea.focus();
        });
      });
    });

    // Cancel button
    document.querySelector('.cancel_btn')?.addEventListener('click', () => {
      const commentBox = document.getElementById('comment_section');
      const textarea = document.getElementById('comment-textarea');
      textarea.value = '';
      textarea.style.height = 'auto';
      document.getElementById('edit_id').value = '';
      document.getElementById('parent_id').value = '';

      const previewContainer = document.getElementById('comment-image-preview');
      const imageLabel = document.getElementById('comment-image-label');

      if (previewContainer) previewContainer.innerHTML = '';
      if (document.getElementById('comment-image')) document.getElementById('comment-image').value = '';
      if (imageLabel) imageLabel.style.display = 'flex';

      commentBox.classList.add('d-none');
      commentBox.querySelector('button[type="submit"]').textContent = 'Comment';
    });

    // Mobile: slide-up comment box behavior
    const commentSection = document.getElementById('comment_section');

    function openMobileCommentBox() {
      if (window.innerWidth <= 768) {
        commentSection.classList.remove('d-none');
        commentSection.scrollTop = 0;
      }
    }

    function closeMobileCommentBox() {
      if (window.innerWidth <= 768) {
        commentSection.classList.add('d-none');
      }
    }

    document.querySelectorAll('.reply-btn, .post-reply-btn, .edit-comment-btn')
      .forEach(btn => {
        btn.addEventListener('click', () => {
          openMobileCommentBox();
        });
      });

    document.querySelector('.cancel_btn')?.addEventListener('click', () => {
      closeMobileCommentBox();
    });

    // ── Inline "See replies" button (next to username) ──────────────────────
    document.addEventListener('click', e => {
      const btn = e.target.closest('.see-replies-inline');
      if (!btn) return;
      e.preventDefault();

      // Guard: prevent double-fetch from fast/multiple clicks
      if (btn.dataset.loading === '1') return;
      btn.dataset.loading = '1';
      btn.style.pointerEvents = 'none';
      btn.style.opacity = '0.5';

      const commentId = btn.dataset.commentId;
      const skip      = parseInt(btn.dataset.skip);
      const total     = parseInt(btn.dataset.total || 0);

      // Use the comment-item whose data-comment-id matches this button exactly
      // Do NOT use .closest() — it may grab a parent comment-item instead
      const replyItem = document.querySelector(`.comment-item[data-comment-id="${commentId}"]`);
      if (!replyItem) { btn.dataset.loading = ''; return; }

      // Each reply gets its OWN scoped highlight block, keyed by commentId
      const highlightKey = `reply-highlight-${commentId}`;

      fetch(`/posts/view/{{ $type }}/{{ $post->uuid }}?comment_id=${commentId}&skip=${skip}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      })
        .then(res => res.json())
        .then(data => {
          // Find or create the highlight wrapper scoped ONLY to this reply
          let parentHighlight = document.querySelector(`.parent-reply-highlight[data-highlight-key="${highlightKey}"]`);
          if (!parentHighlight) {
            parentHighlight = document.createElement('div');
            parentHighlight.className = 'parent-reply-highlight';
            parentHighlight.dataset.highlightKey = highlightKey;
            parentHighlight.style.cssText =
              'background: rgba(102,108,232,0.07); border-radius: 8px; padding: 8px 8px 4px 8px; margin-top: 6px;';
            replyItem.insertAdjacentElement('beforebegin', parentHighlight);
            parentHighlight.appendChild(replyItem);
          }

          // Replies wrapper scoped to this highlight only
          let highlightWrap = parentHighlight.querySelector(':scope > .replies-highlight-wrap');
          if (!highlightWrap) {
            highlightWrap = document.createElement('div');
            highlightWrap.className = 'replies-highlight-wrap';
            parentHighlight.appendChild(highlightWrap);
          }
          if (skip === 0) highlightWrap.innerHTML = '';

          data.replies.forEach(reply => {
            if (data.reportedIds.includes(reply.id)) {
              return; // skip
            }
            highlightWrap.insertAdjacentHTML('beforeend', renderReply(reply));
          });

          const newSkip = skip + data.count;

          // Remove the inline "See replies" link
          btn.remove();

          // Inject/update the bottom control scoped to this highlight only
          let bottomCtrl = parentHighlight.querySelector(':scope > .inline-replies-bottom-ctrl');
          if (!bottomCtrl) {
            bottomCtrl = document.createElement('div');
            bottomCtrl.className = 'inline-replies-bottom-ctrl ms-6 ps-6 mt-2';
            parentHighlight.appendChild(bottomCtrl);
          }

          if (newSkip >= total) {
            bottomCtrl.innerHTML =
              `<a href="javascript:void(0);" class="text-primary fw-semibold inline-replies-see-less"
                  data-comment-id="${commentId}" data-total="${total}" style="font-size:0.85rem;">
                <i class="ti tabler-caret-right-filled" style="font-size:0.78rem;"></i> See less
              </a>`;
          } else {
            bottomCtrl.innerHTML =
              `<a href="javascript:void(0);" class="text-primary fw-semibold inline-replies-see-more"
                  data-comment-id="${commentId}" data-skip="${newSkip}" data-total="${total}"
                  data-highlight-key="${highlightKey}" style="font-size:0.85rem;">
                See more replies
              </a>`;
          }
        })
        .catch(err => console.error('Error loading replies:', err));
    });

    // ── "See more replies" inside an inline highlight block ──────────────────
    document.addEventListener('click', e => {
      const btn = e.target.closest('.inline-replies-see-more');
      if (!btn) return;
      e.preventDefault();

      const commentId    = btn.dataset.commentId;
      const skip         = parseInt(btn.dataset.skip);
      const total        = parseInt(btn.dataset.total || 0);
      const highlightKey = btn.dataset.highlightKey;

      const parentHighlight = document.querySelector(`.parent-reply-highlight[data-highlight-key="${highlightKey}"]`);
      const highlightWrap   = parentHighlight ? parentHighlight.querySelector(':scope > .replies-highlight-wrap') : null;
      const bottomCtrl      = parentHighlight ? parentHighlight.querySelector(':scope > .inline-replies-bottom-ctrl') : null;

      if (!parentHighlight || !highlightWrap || !bottomCtrl) return;

      fetch(`/posts/view/{{ $type }}/{{ $post->uuid }}?comment_id=${commentId}&skip=${skip}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      })
        .then(res => res.json())
        .then(data => {
          data.replies.forEach(reply => {
            if (data.reportedIds.includes(reply.id)) {
              return; // skip
            }
            highlightWrap.insertAdjacentHTML('beforeend', renderReply(reply));
          });

          const newSkip = skip + data.count;

          if (newSkip >= total) {
            bottomCtrl.innerHTML =
              `<a href="javascript:void(0);" class="text-primary fw-semibold inline-replies-see-less"
                  data-comment-id="${commentId}" data-total="${total}" style="font-size:0.85rem;">
                <i class="ti tabler-caret-right-filled" style="font-size:0.78rem;"></i> See less
              </a>`;
          } else {
            btn.dataset.skip = newSkip;
          }
        })
        .catch(err => console.error('Error loading more replies:', err));
    });

    // ── "See less" inside an inline highlight block
    document.addEventListener('click', e => {
      const btn = e.target.closest('.inline-replies-see-less');
      if (!btn) return;
      e.preventDefault();

      const commentId = btn.dataset.commentId;
      const total     = parseInt(btn.dataset.total || 0);

      // Find only the highlight block that directly owns this "See less" button
      const parentHighlight = btn.closest('.parent-reply-highlight');
      if (!parentHighlight) return;

      // Remove rendered replies scoped to this block only
      const highlightWrap = parentHighlight.querySelector(':scope > .replies-highlight-wrap');
      if (highlightWrap) highlightWrap.remove();

      // Move the reply item back out
      const replyItem = parentHighlight.querySelector(`.comment-item[data-comment-id="${commentId}"]`);
      if (replyItem) {
        parentHighlight.insertAdjacentElement('afterend', replyItem);

        // Re-inject the "See replies" link next to the username
        const authorEl = replyItem.querySelector('.comment-author');
        if (authorEl && !authorEl.querySelector('.see-replies-inline')) {
          const repliesCount = total || parseInt(replyItem.dataset.repliesCount || 1);
          const link = document.createElement('a');
          link.href = 'javascript:void(0);';
          link.className = 'see-replies-inline text-primary fw-semibold';
          link.style.fontSize = '0.78em';
          link.dataset.commentId = commentId;
          link.dataset.skip      = '0';
          link.dataset.total     = repliesCount;
          link.innerHTML = `<i class="ti tabler-caret-right-filled"></i> ${repliesCount === 1 ? 'See reply' : 'See replies'}`;
          authorEl.appendChild(link);
        }
      }

      parentHighlight.remove();
    });

    // ── Bottom "See more replies" button (outside inline highlight) ───────────
    document.addEventListener('click', e => {
      const btn = e.target.closest('.see-more-replies');
      if (!btn) return;
      e.preventDefault();

      // "See less" mode — collapse extra loaded replies
      if (btn.dataset.mode === 'see-less-bottom') {
        const seeMoreContainer = btn.parentElement;
        const parentComment    = btn.closest('.comment-item');
        const repliesContainerDiv = parentComment.querySelector('[class*="replies-container-"]');
        if (repliesContainerDiv) {
          let next = repliesContainerDiv.nextElementSibling;
          while (next && next !== seeMoreContainer) {
            const toRemove = next;
            next = next.nextElementSibling;
            if (toRemove.classList.contains('comment-item')) toRemove.remove();
          }
        }
        btn.dataset.skip = '3';
        btn.dataset.mode = '';
        btn.innerHTML = `See more replies`;
        return;
      }

      const commentId = btn.dataset.commentId;
      const skip      = parseInt(btn.dataset.skip);

      fetch(`/posts/view/{{ $type }}/{{ $post->uuid }}?comment_id=${commentId}&skip=${skip}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      })
        .then(res => res.json())
        .then(data => {
          const seeMoreContainer = btn.parentElement;
          data.replies.forEach(reply => {
            if (data.reportedIds.includes(reply.id)) {
              return; // skip
            }
            seeMoreContainer.insertAdjacentHTML('beforebegin', renderReply(reply));
          });

          const newSkip    = skip + data.count;
          btn.dataset.skip = newSkip;
          const parentComment = btn.closest('.comment-item');
          const totalReplies  = parentComment ? parseInt(parentComment.dataset.repliesCount || 0) : 0;

          if (newSkip >= totalReplies) {
            btn.innerHTML    = `See less`;
            btn.dataset.mode = 'see-less-bottom';
          }
        })
        .catch(err => console.error('Error loading more replies:', err));
    });



    // Helper function to render reply HTML
    function renderReply(reply) {
      const avatarUrl = (reply.user.attachment && reply.user.attachment.link) ?
        `/storage/${reply.user.attachment.link}` :
        `/assets/img/avatars/1.png`;
      const reaction = reply.user_reaction ? reply.user_reaction.type : null;
      const likeIcon = reaction === 'like' ? 'tabler-thumb-up-filled' : 'tabler-thumb-up';
      const dislikeIcon = reaction === 'dislike' ? 'tabler-thumb-down-filled' : 'tabler-thumb-down';
      const likesCount = reply.reactions ? reply.reactions.filter(r => r.type === 'like').length : 0;
      const dislikesCount = reply.reactions ? reply.reactions.filter(r => r.type === 'dislike').length : 0;
      const attachmentHtml = reply.attachment ?
        `<div class="my-2">
      <img src="/storage/${reply.attachment.link}" class="img-fluid rounded-3 border"
        style="width: 40%; height: 40%;" alt="Comment attachment">
    </div>` : '';
      const editDeleteHtml = reply.user_id === {{ auth()->id() }} ?
        `<div class="dropdown">
    <button class="btn btn-sm p-0 border-0" type="button" id="commentActionDropdown${reply.id}"
      data-bs-toggle="dropdown" aria-expanded="false">
      <i class="ti ti tabler-dots-vertical"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="commentActionDropdown${reply.id}">
      <li>
        <button type="button" class="dropdown-item edit-comment-btn" data-id="${reply.id}">
          Edit
        </button>
      </li>
      <li>
        <button type="button" class="dropdown-item delete-comment-btn text-danger" data-id="${reply.id}">
          Delete
        </button>
      </li>
    </ul>
  </div>` :
        `{{ Auth()->user()->hasRole('Society Member') ? 'show' : 'hide' }}` === 'show' ?
          `<div class="dropdown">
    <button class="btn btn-sm p-0 border-0" type="button" id="commentActionDropdown${reply.id}"
      data-bs-toggle="dropdown" aria-expanded="false">
      <i class="ti ti tabler-dots-vertical"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="commentActionDropdown${reply.id}">
      <li class="report_${reply.id}">
        <a class="dropdown-item py-1 small" href="#"
          onclick="openReport(${reply.id}, 'comment')">
          <i class="ti tabler-flag me-1"></i> Report an issue
        </a>
      </li>
    </ul>
  </div>` : '';

      const createdAt = new Date(reply.created_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });

      return `
    <div class="comment-item mt-3" data-comment-id="${reply.id}">
      <div class="d-flex gap-3 align-items-start ms-6 ps-6">
        <img src="${avatarUrl}" class="rounded-circle flex-shrink-0" width="40" height="40" alt="User">
        <div class="flex-grow-1" style="min-width: 0;">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="mb-0 fw-semibold comment-author d-flex align-items-center gap-2 flex-wrap">
                ${reply.user.first_name.charAt(0).toUpperCase() + reply.user.first_name.slice(1)}
                ${reply.user.last_name.charAt(0).toUpperCase() + reply.user.last_name.slice(1)}
                ${(reply.replies_count > 0) ? `<a href="javascript:void(0);" class="see-replies-inline text-primary fw-semibold" style="font-size:0.75rem;" data-comment-id="${reply.id}" data-skip="0" data-total="${reply.replies_count}"><i class="ti tabler-caret-right-filled"></i> ${reply.replies_count === 1 ? 'See reply' : 'See replies'}</a>` : ''}
              </h6>
              <small class="text-muted d-block mt-1 ">${createdAt}</small>
          ${reply.parent && reply.parent.user ? `
                                                                                       <div class="mt-2 mb-1">
                                                                                        <small class="text-muted"  >
                                                                                          <i class="ti tabler-corner-up-left-double me-1"></i>
                                                                                           Reply to <span class="fw-semibold">
                                                                                            ${reply.parent.user.first_name.charAt(0).toUpperCase() + reply.parent.user.first_name.slice(1)}
                                                                                            ${reply.parent.user.last_name.charAt(0).toUpperCase() + reply.parent.user.last_name.slice(1)}
                                                                                          </span>
                                                                                        </small>
                                                                                         </div> ` : ''}
            </div>
            ${editDeleteHtml}
          </div>
          <div class="pt-2">
            <p class="mb-2 text-body lh-base"
              style="word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; max-width: 100%;">
              ${reply.message}
            </p>
            ${attachmentHtml}
          </div>
          <div class="d-flex justify-content-between comment-actions">
            <div>
              <span class="comment_react me-3 cursor-pointer" data-id="${reply.id}" data-type="comment" data-reaction="like">
                <i class="icon-base ti ${likeIcon} react-like-icon me-1"></i>
                <span class="comment-like-count">${likesCount}</span>
              </span>
              <span class="comment_react cursor-pointer me-3" data-id="${reply.id}" data-type="comment" data-reaction="dislike">
                <i class="icon-base ti ${dislikeIcon} react-dislike-icon me-1"></i>
                <span class="comment-dislike-count">${dislikesCount}</span>
              </span>
              <span type="button" class="reply-btn cursor-pointer">
                <i class="icon-base ti ti tabler-message-circle me-1"></i>Reply
              </span>
            </div>
          </div>
          <div class="comment-reply-box mt-2"></div>
        </div>
      </div>
    </div>
  `;
    }

    // Delete comment — delegated (handles both static and dynamic)
    document.addEventListener('click', e => {
      if (!e.target.closest('.delete-comment-btn')) return;

      e.preventDefault();
      const btn = e.target.closest('.delete-comment-btn');
      const commentId = btn.dataset.id;

      fetch(`/forum/{{ $type }}/comments/${commentId}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': "{{ csrf_token() }}",
          'Accept': 'application/json'
        }
      })
        .then(res => {
          if (!res.ok) throw new Error('Delete failed');
          return res.json();
        })
        .then(data => {
          const item = document.querySelector(`[data-comment-id="${commentId}"]`);
          if (item) {
            const allItems = [item];

            // Remove replies container for level-0 comments
            const repliesContainer = document.querySelector(`.replies-container-${commentId}`);
            if (repliesContainer) allItems.push(repliesContainer);

            // Remove orphaned "See more replies" button wrapper
            const seeMoreBtn = document.querySelector(`.see-more-replies[data-comment-id="${commentId}"]`);
            if (seeMoreBtn && seeMoreBtn.closest('div')) allItems.push(seeMoreBtn.closest('div'));

            // Remove any inline highlight blocks that belong to this comment
            document.querySelectorAll('.parent-reply-highlight').forEach(block => {
              if (block.querySelector(`.comment-item[data-comment-id="${commentId}"]`)) {
                allItems.push(block);
              }
            });

            allItems.forEach(el => {
              if (!el) return;
              el.style.transition = 'opacity 0.3s';
              el.style.opacity = '0';
              setTimeout(() => el.remove(), 300);
            });
          }

          const countEls = document.querySelectorAll('.text-dark');
          countEls.forEach(el => {
            const match = el.textContent.match(/(\d+)\s*comments?/i);
            if (match) {
              const newCount = parseInt(match[1]) - 1;
              el.textContent = `${newCount} comment${newCount !== 1 ? 's' : ''}`;
            }
          });

          const statusCount = document.querySelector('.post-status span:nth-child(3)');
          if (statusCount) {
            const count = parseInt(statusCount.textContent.match(/\d+/)?.[0] || 0);
            statusCount.innerHTML = `<i class="icon-base ti ti tabler-message-circle-filled me-1"></i>${count - 1}`;
          }
        })
        .catch(err => {
          console.error(err);
          alert('Failed to delete comment');
        });
    });

    // Delegated reply button — handles both static and dynamic
    document.addEventListener('click', e => {
      const replyBtn = e.target.closest('.reply-btn');
      if (!replyBtn) return;

      const commentSection = document.getElementById('comment_section');
      const submitBtn = commentSection.querySelector('button[type="submit"]');
      const parentIdInput = document.getElementById('parent_id');
      const editIdInput = document.getElementById('edit_id');

      const commentItem = replyBtn.closest('.comment-item');
      const replyBox = commentItem ? commentItem.querySelector('.comment-reply-box') : null;
      const authorEl = commentItem ? commentItem.querySelector('.comment-author') : null;

      if (!replyBox || !authorEl) return;

      editIdInput.value = '';
      parentIdInput.value = commentItem.dataset.commentId;
      textarea.placeholder = `Reply to: ${authorEl.textContent.trim()}`;
      submitBtn.textContent = 'Reply';

      clearImagePreview();
      replyBox.appendChild(commentSection);
      commentSection.classList.remove('d-none');
      textarea.focus();
    });

    // Delegated edit button — handles both static and dynamic
    document.addEventListener('click', e => {
      const editBtn = e.target.closest('.edit-comment-btn');
      if (!editBtn) return;

      const commentId = editBtn.dataset.id;
      const commentItem = document.querySelector(`.comment-item[data-comment-id="${commentId}"]`);
      if (!commentItem) return;

      const commentBox = document.getElementById('comment_section');
      const editIdInput = document.getElementById('edit_id');
      const parentIdInput = document.getElementById('parent_id');
      const submitBtn = commentBox.querySelector('button[type="submit"]');
      const previewContainer = document.getElementById('comment-image-preview');
      const imageLabel = document.getElementById('comment-image-label');

      editIdInput.value = commentId;
      parentIdInput.value = '';

      const commentText = commentItem.querySelector('p.mb-2')?.textContent.trim() || '';
      textarea.value = commentText;
      textarea.style.height = 'auto';
      textarea.style.height = textarea.scrollHeight + 'px';

      const existingImage = commentItem.querySelector('.my-2 img');
      if (previewContainer && imageLabel) {
        if (existingImage) {
          imageLabel.style.display = 'none';
          previewContainer.innerHTML = `<div class="position-relative">
        <img src="${existingImage.src}" class="rounded border" style="width:60px;height:60px;object-fit:cover;">
        <span class="remove-preview-btn" style="position:absolute;top:-5px;right:-5px;background:rgba(0,0,0,0.6);color:#fff;border-radius:50%;cursor:pointer;padding:2px 6px;font-size:14px;">&times;</span>
      </div>`;
          previewContainer.querySelector('.remove-preview-btn').addEventListener('click', () => {
            previewContainer.innerHTML = '';
            document.getElementById('comment-image').value = '';
            imageLabel.style.display = 'flex';
          });
        } else {
          previewContainer.innerHTML = '';
          document.getElementById('comment-image').value = '';
          imageLabel.style.display = 'flex';
        }
      }

      submitBtn.textContent = 'Update';
      const replyBox = commentItem.querySelector('.comment-reply-box');
      if (replyBox) replyBox.appendChild(commentBox);
      commentBox.classList.remove('d-none');
      if (window.innerWidth <= 576) commentBox.style.zIndex = '1055';
      textarea.focus();
    });
  </script>
@endpush
