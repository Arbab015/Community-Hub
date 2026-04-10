<div class="row g-4">
  <!-- Upload Section -->
  <div class="col-12">
    <div class="card shadow-sm rounded-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">
            <i class="fa-solid fa-images text-primary me-2"></i>
            Gallery
          </h5>
          @if (auth()->user()->can('edit_society') && $society->status == 'active')
          <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="fa-solid fa-cloud-upload me-2"></i>Upload Media
          </button>
            @endif
        </div>
        @php
          // Filter only images and videos using the extensions column
          $mediaFiles = $society->attachments->filter(function ($attachment) {
              $ext = strtolower($attachment->extension ?? '');
              return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'webm']);
          });
        @endphp

        @if ($mediaFiles->count() > 0)
          <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="select_all">
              <label class="form-check-label fw-semibold" for="select_all">
                Select All
              </label>
            </div>
            <button type="button" id="bulk_delete_btn" class="btn btn-danger btn-sm d-none bulk_delete_btn"
              data-url="{{ route('societies.bulk_delete') }}">
              <i class="fa-solid fa-trash me-1"></i> Delete Selected
            </button>
          </div>
        @endif

        @if ($mediaFiles->count() > 0)
          <div class="row g-3" id="galleryGrid">
            @foreach ($mediaFiles as $media)
              @php
                $ext = strtolower(pathinfo($media->link, PATHINFO_EXTENSION));
                $isVideo = in_array($media->extension, ['mp4', 'mov', 'avi', 'webm']);
                $mediaUrl = asset('storage/' . $media->link);
              @endphp

              <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 gallery-item">
                <div class="card border-0 shadow-sm h-100 position-relative">
                  <!-- Checkbox (JS depends on this) -->
                  <div class="position-absolute top-0 start-0 p-2" style="z-index:10">
                    <input type="checkbox" class="form-check-input checkbox" value="{{ $media->id }}">
                  </div>
                  <!-- Media -->
                  <div class="media-container" style="height:180px;cursor:pointer"
                    onclick="openLightbox('{{ $mediaUrl }}', {{ $isVideo ? 'true' : 'false' }})">
                    @if ($isVideo)
                      <video class="w-100 h-100" style="object-fit:cover">
                        <source src="{{ $mediaUrl }}" type="video/{{ $ext }}">
                      </video>
                    @else
                      <img src="{{ $mediaUrl }}" class="w-100 h-100" style="object-fit:cover">
                    @endif
                  </div>
                  <!-- Actions -->
                  <!-- Card Body -->
                  <div class="card-body p-3">
                    <h6 class="text-truncate mb-1" title="{{ $media->name }}">
                      {{ $media->name }}
                    </h6>
                    <div class="d-flex justify-content-between align-items-center">
                      <small class="text-muted">
                        <i class="fa-regular fa-calendar me-1"></i>
                        {{ $media->created_at->format('M d, Y') }}
                      </small>
                      <div class="dropdown">
                        <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown">
                          <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                          <li>
                            <a class="dropdown-item" href="{{ $mediaUrl }}" download>
                              <i class="fa-solid fa-download me-2"></i>Download
                            </a>
                          </li>
                          <li>
                            <a href="{{ route('attachment.delete', [$media->id]) }}" class=" dropdown-item text-danger"
                              onclick="confirmDelete(event)">
                              <i class="fa-solid fa-trash me-2"></i>Delete
                            </a>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <!-- Empty State -->
          <div class="text-center py-5">
            <div class="mb-3">
              <i class="fa-solid fa-images text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
            </div>
            <h5 class="text-muted mb-2">No media files yet</h5>
            <p class="text-muted mb-3">Upload images or videos to create your gallery</p>
            @if (auth()->user()->can('edit_society') && $society->status == 'active')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
              <i class="fa-solid fa-cloud-upload me-2"></i>Upload First Media
            </button>
              @endif
          </div>
        @endif
      </div>
    </div>
  </div>
</div>


@include('_partials._modals.upload_media')
@include('_partials._modals.lightbox_model')
@push('scripts')
  <script>
    // Lightbox
    function openLightbox(url, isVideo) {
      console.log(url);
      const modal = new bootstrap.Modal(document.getElementById('lightboxModal'));
      const img = document.getElementById('lightboxImage');
      const video = document.getElementById('lightboxVideo');
      const videoSource = document.getElementById('lightboxVideoSource');

      if (isVideo) {
        img.classList.add('d-none');
        video.classList.remove('d-none');
        videoSource.src = url;
        video.load();
      } else {
        video.classList.add('d-none');
        img.classList.remove('d-none');
        img.src = url;
      }
      modal.show();
    }
  </script>
@endpush
