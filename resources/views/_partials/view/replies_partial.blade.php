{{-- Partial view for AJAX loaded replies --}}
@foreach ($replies as $reply)
  @php
    $reaction = $reply->userReaction?->type;
  @endphp

  <div class="comment-item mt-3" data-comment-id="{{ $reply->id }}">
    <div class="d-flex gap-3 align-items-start ms-6 ps-6">

      {{-- Avatar --}}
      <img src="{{ $reply->user->avatar ?? asset('assets/img/avatars/1.png') }}" class="rounded-circle flex-shrink-0"
        width="40" height="40" alt="User">
      <div class="flex-grow-1" style="min-width: 0;">
        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h6 class="mb-0 fw-semibold comment-author">
              {{ ucfirst($reply->user->first_name) }} {{ ucfirst($reply->user->last_name) }}
            </h6>
            <small class="text-muted d-block mt-1">
              {{ $reply->created_at->format('F d, Y h:i A') }}
            </small>
          </div>
        </div>

        {{-- Reply indicator --}}
        @if ($reply->parent)
          <div class="mt-2 mb-1">
            <small class="text-muted">
              <i class="ti tabler-corner-up-left-double me-1"></i>
              Reply to <span class="fw-semibold">{{ ucfirst($reply->parent->user->first_name) }}
                {{ ucfirst($reply->parent->user->last_name) }}</span>
            </small>
          </div>
        @endif

        {{-- Message --}}
        <div class="pt-2">
          <p class="mb-2 text-body lh-base"
            style="word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; max-width: 100%;">
            {{ $reply->message }}
          </p>

          {{-- Attachment --}}
          @if ($reply->attachment)
            <div class="my-2">
              <img src="{{ asset('storage/' . $reply->attachment->link) }}" class="img-fluid rounded-3 border"
                style="width: 40%; height: 40%;" alt="Comment attachment">
            </div>
          @endif
        </div>

        {{-- Actions --}}
        <div class="d-flex justify-content-between comment-actions">
          <div>
            <span class="comment_react me-3 cursor-pointer" data-id="{{ $reply->id }}" data-type="comment"
              data-reaction="like">
              <i
                class="icon-base ti {{ $reaction === 'like' ? 'tabler-thumb-up-filled' : 'tabler-thumb-up' }} react-like-icon me-1"></i>
              <span class="comment-like-count">{{ $reply->likes->count() }}</span>
            </span>

            <span class="comment_react cursor-pointer me-3" data-id="{{ $reply->id }}" data-type="comment"
              data-reaction="dislike">
              <i
                class="icon-base ti {{ $reaction === 'dislike' ? 'tabler-thumb-down-filled' : 'tabler-thumb-down' }} react-dislike-icon me-1"></i>
              <span class="comment-dislike-count">{{ $reply->dislikes->count() }}</span>
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
  </div>
@endforeach
