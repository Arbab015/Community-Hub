@php
  use Illuminate\Support\Facades\Auth;
@endphp
{{-- comment box --}}
<div class="border rounded-3 p-3 my-4 bg-white d-none" id="comment_section" style="margin-left:10%;">
  <div class="d-flex gap-3 align-items-start">

    {{-- Avatar --}}
    <img
      src="{{ Auth::user()->attachment?->link
          ? asset('storage/' . Auth::user()->attachment->link)
          : asset('assets/img/avatars/1.png') }}"
      class="rounded-circle flex-shrink-0" width="40" height="40" alt="User">

    <div class="flex-grow-1 position-relative">

      <form action="{{ route('comments.store', $type) }}" method="POST" enctype="multipart/form-data">
        @csrf

        <input type="hidden" name="post_id" value="{{ $post->id }}">
        <input type="hidden" name="parent_id" id="parent_id" value="">
        <input type="hidden" name="edit_id" id="edit_id" value="">


        {{-- Textarea --}}
        <textarea name="message" id="comment-textarea" class="form-control border-0 shadow-none px-0 mb-2 lh-base"
          placeholder="Add a comment..." rows="2" style="resize:none; overflow:hidden;"></textarea>

        {{-- Divider --}}
        {{-- <hr class="my-2"> --}}

        {{-- Actions Row --}}
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            {{-- Image Upload --}}
            <label for="comment-image" id="comment-image-label"
              class="cursor-pointer text-primary mb-0 d-flex align-items-center justify-content-center"
              style="width:32px;height:32px;">
              <i class="far fa-image fs-5"></i>
            </label>
            <input type="file" id="comment-image" name="image" class="d-none" accept="image/*">

            {{-- Emoji --}}
            <button type="button" class="btn p-0 text-warning d-flex align-items-center justify-content-center"
              id="emoji-btn" style="width:32px;height:32px;">
              <i class="far fa-smile fs-5"></i>
            </button>

            {{-- Image Preview Container --}}
            <div id="comment-image-preview" class="ms-2"></div>
          </div>

          {{-- Buttons --}}
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm px-4 cancel_btn">
              Cancel
            </button>
            <button type="submit" class="btn btn-success btn-sm px-4">
              Comment
            </button>
          </div>
        </div>

      </form>

      {{-- Emoji picker --}}
      <div id="emoji-picker-container" class="position-absolute mt-2" style="display:none; z-index:1000;"></div>
    </div>
  </div>
</div>
