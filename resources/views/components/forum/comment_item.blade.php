@php
  $level = $level ?? 0;
  $reaction = $comment->userReaction?->type;
@endphp

<div class="comment-item {{ $level > 0 ? 'mt-3' : 'mb-3' }}" data-comment-id="{{ $comment->id }}"
     data-replies-count="{{ $comment->replies_count ?? 0 }}">
  <div class="d-flex gap-3 align-items-start {{ $level > 0 ? 'ms-6 ps-6' : '' }}">
    <img
      src="{{ $comment->user->attachment ? asset('storage/' . $comment->user->attachment->link) : asset('assets/img/avatars/1.png') }}"
      class="rounded-circle flex-shrink-0" width="40" height="40" alt="User">
    <div class="flex-grow-1" style="min-width: 0;">
      {{-- Header --}}
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h6 class="mb-0 fw-semibold comment-author d-flex align-items-center gap-2 flex-wrap">
            {{ ucfirst($comment->user->first_name) }} {{ ucfirst($comment->user->last_name) }}
            @php
              $total_replies = $comment->replies->whereNotIn('id', $reportedIds)->count();
            @endphp
            @if ($level === 1 && $total_replies > 0 )
              <a href="javascript:void(0);" class="see-replies-inline text-primary fw-semibold" style="font-size:0.78em;"
                 data-comment-id="{{ $comment->id }}" data-skip="0" data-total="{{ $comment->replies_count }}">
                <i class="ti ti tabler-caret-right-filled"></i>
                {{ $comment->replies_count == 1 ? 'See reply' : 'See replies' }}
              </a>
            @endif
          </h6>
          <small class="text-muted d-block mt-1">
            {{ $comment->created_at->format('F d, Y h:i A') }}
          </small>
        </div>
        @php
          $isAuther = auth()->id() === $comment->user_id;
          $reported_comment = in_array($comment->id, $reportedIds);
          $roleMember = Auth()->user()->hasRole('Society Member');
        @endphp
        {{-- Dropdown for edit/delete --}}
        @if ($isAuther || (!$reported_comment && $roleMember))
          <div class="dropdown">
            <button class="btn btn-sm p-0 border-0" type="button" id="commentActionDropdown{{ $comment->id }}"
                    data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti tabler-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end small"
                aria-labelledby="commentActionDropdown{{ $comment->id }}">
              @if ($isAuther)
                <li>
                  <button type="button" class="dropdown-item edit-comment-btn small" data-id="{{ $comment->id }}">
                    Edit
                  </button>
                </li>
                <li>
                  <button type="button" class="dropdown-item delete-comment-btn text-danger small"
                          data-id="{{ $comment->id }}">
                    Delete
                  </button>
                </li>
              @else
                @if (!$reported_comment && $roleMember)
                  {{-- Not yet reported - show button --}}
                  <li class="report_{{ $comment->id }}">
                    <a class="dropdown-item py-1 small" href="#"
                       onclick="openReport({{ $comment->id }}, 'comment')">
                      <i class="ti tabler-flag me-1"></i> Report an issue
                    </a>
                  </li>
                @endif
              @endif
            </ul>
          </div>
        @endif
      </div>

      {{-- Reply indicator (if this is a reply) --}}
      @if ($level > 0 && $comment->parent)
        <div class="mt-2 mb-1">
          <small class="text-muted">
            <i class="ti tabler-corner-up-left-double me-1"></i>
            Reply to <span class="fw-semibold">{{ ucfirst($comment->parent->user->first_name) }}
              {{ ucfirst($comment->parent->user->last_name) }}</span>
          </small>
        </div>
      @endif

      {{-- Message --}}
      <div class="pt-2">
        <p class="mb-2 text-body lh-base"
           style="word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; max-width: 100%;">
          {{ $comment->message }}
        </p>

        {{-- Attachment --}}
        @if ($comment->attachment)
          <div class="my-2">
            <img src="{{ asset('storage/' . $comment->attachment->link) }}" class="img-fluid rounded-3 border"
                 style="width: 40%; height: 40%;" alt="Comment attachment">
          </div>
        @endif
      </div>

      {{-- Actions --}}
      <div class="d-flex justify-content-between comment-actions">
        <div>
          <span class="comment_react me-3 cursor-pointer" data-id="{{ $comment->id }}" data-type="comment"
                data-reaction="like">
            <i
              class="icon-base ti {{ $reaction === 'like' ? 'tabler-thumb-up-filled' : 'tabler-thumb-up' }} react-like-icon me-1"></i>
            <span class="comment-like-count">{{ $comment->likes->count() }}</span>
          </span>

          <span class="comment_react cursor-pointer me-3" data-id="{{ $comment->id }}" data-type="comment"
                data-reaction="dislike">
            <i
              class="icon-base ti {{ $reaction === 'dislike' ? 'tabler-thumb-down-filled' : 'tabler-thumb-down' }} react-dislike-icon me-1"></i>
            <span class="comment-dislike-count">{{ $comment->dislikes->count() }}</span>
          </span>

          <span type="button" class="reply-btn cursor-pointer">
            <i class="icon-base ti ti tabler-message-circle me-1"></i>Reply
          </span>
        </div>
      </div>
      {{-- Reply box --}}
      <div class="comment-reply-box mt-2"></div>
    </div>
  </div>

  {{-- Replies: only render for level 0. Level 1 shows inline button next to username instead. --}}
  @if ($level === 0 && $comment->replies && $comment->replies->count() > 0)
    <div class="replies-container-{{ $comment->id }}">
      @foreach ($comment->replies as $reply)
        @if (!in_array($reply->id, $reportedIds))
        @include('components.forum.comment_item', [
            'comment' => $reply,
            'level' => 1,
        ])
        @endif
      @endforeach
    </div>

    @if ($comment->replies_count > 3)
      <div class="ms-6 ps-6 mt-2">
        <a href="javascript:void(0);" class="text-primary fw-semibold see-more-replies"
           data-comment-id="{{ $comment->id }}" data-skip="3">
          See more replies
        </a>
      </div>
    @endif

  @endif
</div>
