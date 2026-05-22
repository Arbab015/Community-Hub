@php
  $modal = $property ?? $society;
  $canDelete = (auth()->user()->can('edit_society') && $modal->status == 'active') || (isset($property) && auth()->user()->can('delete_property'));
  $canEdit = (auth()->user()->can('edit_society') && $modal->status == 'active') || (isset($property) && auth()->user()->can('edit_property'))
@endphp
<div class="row g-4">
  <div class="col-12">
    <div class="card shadow-sm rounded-3">
      <div class="card-body">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">
            <i class="fa-solid fa-images text-primary me-2"></i>
            Gallery
          </h5>
          @if ($canEdit)
            <button type="button" class="btn btn-primary btn-sm" onclick="showUploadMediaSection()">
              <i class="fa-solid fa-cloud-upload me-2"></i>Upload Media
            </button>
          @endif
        </div>

        {{-- Upload Section (hidden by default, slides in on button click) --}}
        <div class="upload_media_section" id="upload_media_section">
          <div class="upload_media_inner border rounded-3 p-3 mb-3 bg-light">
            <form method="POST" id="upload_media_property"
                  action="{{ isset($society) && $user_type ? route('society.store', [$user_type, $society->uuid]) : route('property.store') }}"
                  enctype="multipart/form-data">
              @csrf
              <input type="hidden" name="request_type" value="media">
              @if(isset($property))
                <input type="hidden" name="block_id" value="{{ $property->block->id }}">
                <input type="hidden" name="section" value="documents">
                <input type="hidden" name="property_id" value="{{ $property->id ?? '' }}">
              @endif

              {{-- Scrollable dropzone wrapper --}}
              <div class="dropzone-scroll-wrapper mb-3">
                <div class="dropzone needsclick dz-clickable dropzone_multi" isRestricted="yes">
                  <div class="dz-message needsclick">
                    <i class="bx bx-upload p-0" style="font-size: 3rem; color: #999;"></i>
                    <h6 class="m-0 pb-1">Drop files here or click to upload</h6>
                    <span class="note needsclick text-muted d-block mt-0">
                      Upload multiple media files related to {{ isset($society) && $user_type ? "Society" : "Property" }}
                    </span>
                  </div>
                </div>
              </div>
            </form>
            {{-- Save button OUTSIDE the form, full width at bottom --}}
            <div class="d-flex justify-content-end gap-2 pt-2 border-top">
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showUploadMediaSection()">
                <i class="ti tabler-x me-1"></i> Cancel
              </button>

              <button type="button" id="upload_files_btn"
                      class="btn btn-sm btn-primary d-flex align-items-center gap-1">
                <i class="ti tabler-upload me-1"></i> Save Files
              </button>
            </div>
          </div>
        </div>

        {{-- Gallery Grid --}}
        @php
          $mediaFiles = $modal->attachments->filter(function ($attachment) {
            $ext = strtolower($attachment->extension ?? '');
            return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'webm']);
          });
        @endphp

        @if ($mediaFiles->count() > 0)
          @if ($canDelete)
            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="select_all">
                <label class="form-check-label fw-semibold" for="select_all">Select All</label>
              </div>
              <button type="button" id="bulk_delete_btn" class="btn btn-danger btn-sm d-none bulk_delete_btn"
                      data-url="{{ route('attachments.bulk_delete') }}">
                <i class="fa-solid fa-trash me-1"></i>
                <span class="d-none d-sm-block">Delete Selected</span>
              </button>
            </div>
          @endif

          <div class="row g-3" id="galleryGrid">
            @foreach ($mediaFiles as $media)
              @php
                $ext = strtolower(pathinfo($media->link, PATHINFO_EXTENSION));
                $isVideo = in_array($media->extension, ['mp4', 'mov', 'avi', 'webm']);
                $mediaUrl = asset('storage/' . $media->link);
              @endphp
              <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 gallery-item">
                <div class="card border-0 shadow-sm h-100 position-relative">
                  @if ($canDelete)
                    <div class="position-absolute top-0 start-0 p-2" style="z-index:10">
                      <input type="checkbox" class="form-check-input checkbox" value="{{ $media->id }}">
                    </div>
                  @endif
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
                  <div class="card-body p-3">
                    <h6 class="text-truncate mb-1" title="{{ $media->name }}">{{ $media->name }}</h6>
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
                          @if ($canDelete)
                            <li>
                              <a href="{{ route('attachment.delete', [$media->id]) }}"
                                 class="dropdown-item text-danger" onclick="confirmDelete(event)">
                                <i class="fa-solid fa-trash me-2"></i>Delete
                              </a>
                            </li>
                          @endif
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>

        @else
          <div class="text-center py-5">
            <div class="mb-3">
              <i class="fa-solid fa-images text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
            </div>
            <h5 class="text-muted mb-2">No media files yet</h5>
            <p class="text-muted mb-3">Upload images or videos to create your gallery</p>
          </div>
        @endif

      </div>
    </div>
  </div>
</div>

@include('_partials._modals.lightbox_model')

@push('styles')
  <style>
    /* Upload section: hidden by default, animates open */
    .upload_media_section {
      display: grid;
      grid-template-rows: 0fr;
      opacity: 0;
      transition: grid-template-rows 0.35s ease, opacity 0.3s ease;
    }

    .upload_media_section.show {
      grid-template-rows: 1fr;
      opacity: 1;
    }

    .upload_media_inner {
      overflow: hidden; /* required for grid-template-rows trick */
    }

    /* Scrollable dropzone: lets previews grow and scroll */
    .dropzone-scroll-wrapper {
      max-height: 280px;
      overflow-y: auto;
      overflow-x: hidden;
      border-radius: .375rem;
    }

    .dropzone-scroll-wrapper .dropzone {
      min-height: 120px;
      border: 2px dashed #c9d3e0;
      border-radius: .375rem;
      background: #fff;
    }

    .dropzone-scroll-wrapper .dropzone .dz-preview {
      margin: 6px;
    }
  </style>
@endpush

@push('scripts')
  <script>
    function openLightbox(url, isVideo) {
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

    function showUploadMediaSection() {
      const section = document.getElementById('upload_media_section');
      section.classList.toggle('show');


    }
  </script>
@endpush
