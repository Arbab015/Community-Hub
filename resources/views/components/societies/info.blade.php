{{-- OverView --}}
<div class="row g-4">
  {{-- Left Column --}}
  <div class="col-xl-4 col-lg-5 col-md-5">
    <div class="card mb-4 shadow-sm rounded-3">
      <div class="card-body text-center">
        <div class="position-relative d-inline-block mb-3">
          <img id="societyAvatarPreview" class="rounded border img-fluid"
            src="{{ $society->attachment ? asset('storage/' . $society->attachment->link) : asset('assets/img/my_images/dummy_society_image.png') }}"
            width="165" height="165">
          @if (auth()->user()->can('edit_society') && $society->status == 'active')
            <span class="position-absolute bottom-0 end-0" title="Click to change picture">
              <i class="fa-solid fa-camera text-white bg-primary  p-2 cursor-pointer"
                onclick="document.getElementById('avatarInput').click();"></i>
            </span>
            <form id="add_img_form" method="POST" action="{{ route('society.store', [$slug, $society->uuid]) }}"
              enctype="multipart/form-data">
              @csrf
              <input type="file" id="avatarInput" name="main_pic" class="d-none" accept="image/*"
                onchange="previewAvatar(event), this.form.submit();">
            </form>
          @endif
        </div>

        <h5 class="mb-0">{{ ucfirst($society->name) }}</h5>
        <span class="badge {{ $society->status == 'active' ? 'bg-primary' : 'bg-secondary' }} bg-glow mt-2">
          {{ ucfirst($society->status) }}
        </span>
        <div class="mt-4 pt-4 border-top text-center">
          <div class="avatar avatar-sm mb-2 mx-auto">
            <span class="avatar-initial rounded bg-label-primary">
              <i class="fa-regular fa-calendar"></i>
            </span>
          </div>
          <small class="text-muted d-block">Created</small>
          <h6 class="mb-0">{{ $society->created_at->format('M d, Y') }}</h6>
        </div>
      </div>
    </div>

    {{-- Documents Card --}}
    <div class="card mb-4 shadow-sm rounded-3">
      <div class="card-header pb-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h5 class="card-title mb-0">
            <i class="fa-solid fa-file-lines fa-lg text-primary me-2 "></i>Documents
          </h5>
          @if (auth()->user()->can('edit_society') && $society->status == 'active')
            <label class="btn btn-outline-info waves-effect btn-xs fw-bolder">
              <i class="fa-solid fa-plus pe-1"></i>Add File
              <input type="file" name="documents[]" form="add_files_form" multiple hidden>
            </label>
          @endif
        </div>
        @if (auth()->user()->can('edit_society') && $society->status == 'active')
          <div class="form-check d-flex justify-content-between align-items-center p-0 ">
            <div class="d-flex align-items-center @if ($documents->count() == 0) d-none @endif">

              <input class="form-check-input-sm checkbox me-2 " type="checkbox" id="select_all">
              <label class="form-check-label fw-semibold mb-0 " for="select_all">
                Select All
              </label>
            </div>

            <div class="d-flex align-items-center gap-1">
              <span data-url="{{ route('societies.bulk_delete') }}"
                class="btn btn-xs btn-icon btn-outline-danger waves-effect d-none bulk_delete_btn" id="bulk_btn">
                <i class="fa-solid fa-trash fa-sm"></i>
              </span>
              <form id="add_files_form" method="POST" action="{{ route('society.store', [$slug, $society->uuid]) }}"
                enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="document"></input>
                <button type="submit" id="save_files_btn"
                  class="btn btn-xs btn-icon btn-outline-info waves-effect d-none">
                  <i class="fa-solid fa-floppy-disk fa-sm"></i>
                </button>
              </form>
            </div>
          </div>
        @endif
      </div>

      <div class="card-body">
        @if ($documents && $documents->count() > 0)
          <div class="overflow-y-scroll" style="max-height: 400px;">
            <ul class="list-unstyled mb-0">
              @foreach ($documents as $attachment)
                <li class="my-1 py-2 border-bottom file_item px-2">
                  <div class="d-flex align-items-center">
                    @if (auth()->user()->can('edit_society') && $society->status == 'active')
                      <div class="form-check ps-0 pt-2 me-3">
                        <input type="checkbox" class="form-check-input-sm checkbox" value="{{ $attachment->id }}">
                      </div>
                    @endif
                    <div class="avatar p-2 avatar-sm me-3">
                      <span class="avatar-initial rounded bg-label-info">
                        <i class="fa-regular {{ \App\Helpers\FileHelper::getFileIcon($attachment->link) }} "></i>
                      </span>
                    </div>

                    <div class="flex-grow-1 d-flex align-items-center justify-content-between ">
                      <div>
                        <h6 class="mb-0 text-break">{{ $attachment->name }}</h6>
                        <small class="text-muted">{{ $attachment->created_at->format('M d, Y') }}</small>
                      </div>
                      <div class="d-flex flex-column align-items-center gap-1 ">
                        @cannot('edit_society')
                          @php
                            $ext = pathinfo($attachment->link, PATHINFO_EXTENSION);
                          @endphp
                          @if (!in_array($ext, ['xls', 'xlsx', 'csv']))
                            <a href="{{ asset('storage/' . $attachment->link) }}" target="_blank"
                              class="btn btn-xs btn-icon btn-outline-primary">
                              <i class="fa-regular fa-eye fa-sm"></i>
                            </a>
                          @endif
                        @endcan
                        <a href="{{ asset('storage/' . $attachment->link) }}" download title="Download"
                          class="btn btn-xs btn-icon btn-outline-success">
                          <i class="fa-solid fa-download fa-sm"></i>
                        </a>
                        @if (auth()->user()->can('edit_society') && $society->status == 'active')
                          <a href="{{ route('attachment.delete', [$attachment->id]) }}" title="Delete"
                            onclick="confirmDelete(event)" class="btn btn-xs btn-icon btn-outline-danger">
                            <i class="fa-solid fa-trash fa-sm"></i>
                          </a>
                        @endif
                      </div>
                    </div>
                  </div>
                </li>
              @endforeach
            </ul>
          </div>
        @else
          <div class="text-center py-5 text-muted">
            <div class="mb-3">
              <i class="ti ti tabler-files-off fs-1"></i>
            </div>
            <h6 class="mb-1">No Documents Found</h6>
            <p class="mb-0 small">
              There are currently no documents available for this section.
            </p>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Right Column --}}
  <div class="col-xl-8 col-lg-7 col-md-7">
    <div class="card mb-4 shadow-sm rounded-3">
      <div class="card-header">
        <h5 class="card-title d-flex justify-content-between align-items-center mb-0">
          <span>
            <i class="fa-solid fa-circle-info fa-lg me-2 text-primary"></i>Basic Information
          </span>
          @if (auth()->user()->can('edit_society') && $society->status == 'active')
            <button type="button" class="btn btn-text-info waves-effect" data-bs-toggle="modal"
              data-bs-target="#edit_society_info"><i class="fa-solid fa-pen-to-square"></i></button>
          @endif
        </h5>
      </div>
      <div class="card-body">
        <div class="row g-4">
          <div class="col-md-6">
            <div class="info-container">
              <small class="text-muted text-uppercase d-block mb-1">Society Name</small>
              <h6 class="mb-0">{{ ucfirst($society->name) ?? 'N/A' }}</h6>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-container">
              <small class="text-muted text-uppercase d-block mb-1">Owner</small>
              <h6 class="mb-0">{{ ucfirst($society->owner->first_name) }}
                {{ ucfirst($society->owner->last_name) }}</h6>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-container">
              <small class="text-muted text-uppercase d-block mb-1">Address</small>
              <h6 class="mb-0">{{ ucfirst($society->address) ?? 'N/A' }}</h6>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-container">
              <small class="text-muted text-uppercase d-block mb-1">City</small>
              <h6 class="mb-0">{{ ucfirst($society->city) ?? 'N/A' }}</h6>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-container">
              <small class="text-muted text-uppercase d-block mb-1">Country</small>
              <h6 class="mb-0">{{ ucfirst($society->country) ?? 'N/A' }}</h6>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-container">
              <small class="text-muted text-uppercase d-block mb-1">Status</small>
              <h6 class="mb-0">
                <span class="badge {{ $society->status == 'active' ? 'bg-primary' : 'bg-secondary' }} bg-glow">
                  {{ ucfirst($society->status) }}
                </span>
              </h6>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Timeline Card --}}
    <div class="card shadow-sm rounded-3">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fa-solid fa-clock me-2 fa-lg text-primary"></i>Timeline Information
        </h5>
      </div>
      <div class="card-body">
        <div class="row g-4">
          <div class="col-md-6">
            <div class="info-container">
              <small class="text-muted text-uppercase d-block mb-1">Created At</small>
              <h6 class="mb-0">{{ $society->created_at->format('F d, Y h:i A') }}</h6>
              <small class="text-muted">{{ $society->created_at->diffForHumans() }}</small>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-container">
              <small class="text-muted text-uppercase d-block mb-1">Last Updated</small>
              <h6 class="mb-0">{{ $society->updated_at->format('F d, Y h:i A') }}</h6>
              <small class="text-muted">{{ $society->updated_at->diffForHumans() }}</small>
            </div>
          </div>

        </div>
      </div>
    </div>

    @php
      $canDelete = auth()->user()->can('delete_society');
      $canBlock =
          auth()->user()->can('block_society') && auth()->user()->can('all_societies') && $society->status === 'active';
    @endphp

    @if ($canDelete || $canBlock)
      <!-- DANGER / ALERT ZONE -->
      <div class="card border-warning shadow-sm rounded-3 mt-4">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="me-2 text-primary">
              <i class="fa-solid fa-triangle-exclamation fa-lg fs-4"></i>
            </div>
            <h5 class="mb-0 fw-semibold">Alert</h5>
          </div>

          {{-- DELETE --}}
          @if ($canDelete)
            <p class="mb-4">
              Deleting this society will
              <strong class="text-danger fst-italic">permanently</strong>
              remove the society and all associated data.
              This action cannot be undone.
            </p>
            <a href="{{ route('society.delete', [$slug, $society->uuid]) }}" class="btn btn-danger"
              onclick="confirmDelete(event)">
              <i class="fa-solid fa-trash me-1"></i>
              Delete Society
            </a>
          @endif

          {{-- BLOCK --}}
          @if ($canBlock)
            <p class="mt-4 mb-3">
              Blocking this society will
              <strong class="text-warning fst-italic">temporarily</strong>
              disable all activities for society users.
              You can unblock the society at any time.
            </p>
            <a href="{{ route('society.block', [$slug, $society->uuid]) }}" class="btn btn-warning"
              onclick="confirmBlock(event)">
              <i class="fa-solid fa-ban me-1"></i>
              Block Society
            </a>
          @endif
        </div>
      </div>
    @endif
  </div>
</div>
{{-- Edit basic info --}}
@include('_partials._modals.edit_society_model')
