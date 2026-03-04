<div class="row g-5">
  <div class="">
    <div class="card">
      <!-- Header with buttons -->
      <div class="card-header d-flex justify-content-between ">
        <div class="fw-bolder fs-5">{{ isset($post) ? 'Edit Post' : 'Write a Post' }}</div>
      </div>
      <!-- Form -->
      <div class="card-body">
        <form method="post" id="post_form" action="{{ route('posts.store') }}">
          @csrf
          <input type="hidden" name="category" value="{{ isset($post) ? $post->category : substr($type, 0, -1) }}">
          {{-- hiddent field -society_id --}}
          <input type="hidden" name="society_id" value="{{ $society_id }}">
          <input type="hidden" name="type" value="{{ $type }}">
          @if (isset($post))
            <input type="hidden" name="post_uuid" value="{{ $post->uuid }}">
          @endif

          <!-- Title -->
          <div class="form-control-validation mb-4">
            <label class="form-label-input fw-bolder required" for="title">Title</label>
            <input type="text" id="title" class="form-control @error('title') is-invalid @enderror" required
              placeholder="Title upto 300 characters" name="title" value="{{ old('title', $post->title ?? '') }}" />
            @error('title')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          {{-- hidden slug --}}
          <input type="hidden" id="slug" name="slug" class="form-control mt-2" readonly>

          <!-- Description -->
          <div class="form-control-validation mb-4">
            <label class="form-label-input fw-bolder required">Description</label>
            <div id="post-editor" class="border rounded"></div>
            <input type="hidden" name="description" id="description"
              class="form-control @error('description') is-invalid @enderror" required>
            @error('description')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>
          <div class="d-flex justify-content-between">
            <div class="col-md-6">
              <label for="tags" class="form-label fw-bolder">Tags</label>
              <div class="select2-primary">
                <select id="select2Primary" name="tags[]"
                  class="select2 form-select @error('tags') is-invalid @enderror" multiple required>
                  @foreach ($tags as $tag)
                    <option value="{{ $tag->id }}" @if ((old('tags') && in_array($tag->id, old('tags'))) || (isset($post) && $post->tags->pluck('id')->contains($tag->id))) selected @endif>
                      {{ $tag->name }}
                    </option>
                  @endforeach
                </select>
                @error('tags')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div class="mt-5">
              <button type="submit" class="btn btn-primary">{{ isset($post) ? 'Update Post' : 'Post Now' }}</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>
