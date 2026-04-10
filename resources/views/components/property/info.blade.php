{{-- components/property/info.blade.php --}}
<div class="row g-4">

  {{-- ═══ LEFT COLUMN ═══ --}}
  <div class="col-xl-4 col-lg-5">

    {{-- Property Image Card --}}
    <div class="card shadow-sm rounded-3 mb-4 overflow-hidden">
      <div class="position-relative">
        @php
          $mainImg = $property->attachment?->link
              ? asset('storage/' . $property->attachment->link)
              : asset('assets/img/my_images/dummy_property_image.png');
        @endphp
        <img id="propertyMainImg"
             src="{{ $mainImg }}"
             class="w-100"
             style="height:240px; object-fit:cover;"
             alt="Property Image">

        {{-- Type Badge --}}
        <span class="badge bg-primary bg-opacity-90 position-absolute top-0 start-0 m-3 px-3 py-2 rounded-pill">
          <i class="ti tabler-home me-1"></i>{{ ucfirst($property->type) }}
        </span>

        {{-- Category Badge --}}
        <span class="badge bg-dark bg-opacity-60 position-absolute top-0 end-0 m-3 px-3 py-2 rounded-pill">
          {{ ucfirst($property->category) }}
        </span>

        {{-- Change image button --}}
        @can('edit_property')
          <label class="position-absolute bottom-0 end-0 m-3 btn btn-sm btn-light shadow" title="Change main image">
            <i class="ti tabler-camera me-1"></i>Change
            <input type="file" hidden name="main_pic" form="update_main_pic_form" accept="image/*"
                   onchange="previewPropertyImg(event), this.form.submit()">
          </label>
          <form id="update_main_pic_form" method="POST"
                action="{{ route('property.store') }}"
                enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="section" value="main_pic">
            <input type="hidden" name="property_id" value="{{ $property->id }}">
            <input type="hidden" name="block_id" value="{{ $property->block_id }}">
          </form>
        @endcan
      </div>

      <div class="card-body text-center py-4">
        <h5 class="fw-bold mb-1">
          {{ $property->name ? ucfirst($property->name) : ucfirst($property->type) }}
        </h5>
        <p class="text-muted small mb-2">
          <i class="ti tabler-hash me-1"></i>Property No: <strong>{{ ucfirst($property->property_no) }}</strong>
        </p>

{{--        --}}{{-- Construction status pill --}}
{{--        @if($property->is_constructed)--}}
{{--          <span class="badge  bg-opacity-15 text-success border bord border-opacity-25 px-3 py-2 rounded-pill">--}}
{{--            <i class="ti tabler-check me-1"></i>Construction Completed--}}
{{--          </span>--}}
{{--        @else--}}
{{--          <span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-25 px-3 py-2 rounded-pill">--}}
{{--            <i class="ti tabler-clock me-1"></i>In Progress--}}
{{--          </span>--}}
{{--        @endif--}}
      </div>

      {{-- Quick stats row --}}
      <div class="border-top d-flex text-center">
        <div class="flex-fill py-3 border-end">
          <p class="mb-0 fw-bold text-primary">{{ $property->dimensions->count() }}</p>
          <small class="text-muted">Dimensions</small>
        </div>
        <div class="flex-fill py-3 border-end">
          <p class="mb-0 fw-bold text-success">{{ $property->floors->count() }}</p>
          <small class="text-muted">Floors</small>
        </div>
        <div class="flex-fill py-3">
          <p class="mb-0 fw-bold text-info">{{ $property->attachments->count() }}</p>
          <small class="text-muted">Files</small>
        </div>
      </div>
    </div>

    {{-- Documents Card --}}
    <div class="card shadow-sm rounded-3 mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
          <i class="ti tabler-files me-2 text-primary"></i>Documents
        </h6>
        @can('edit_property')
          <label class="btn btn-xs btn-outline-primary">
            <i class="ti tabler-plus me-1"></i>Add
            <input type="file" name="documents[]" form="add_docs_form" multiple hidden>
          </label>
        @endcan
      </div>
      <div class="card-body p-0">
        @php
          $docs = $property->attachments->filter(function($a){
            return !in_array(strtolower($a->extension ?? ''), ['jpg','jpeg','png','gif','webp','mp4','mov','avi','webm']);
          });
        @endphp

        @if($docs->count())
          <ul class="list-unstyled mb-0">
            @foreach($docs as $doc)
              <li class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom hover-bg-light">
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar avatar-sm">
                    <span class="avatar-initial rounded bg-label-info">
                      <i class="fa-regular {{ \App\Helpers\FileHelper::getFileIcon($doc->link) }}"></i>
                    </span>
                  </div>
                  <div>
                    <p class="mb-0 small fw-semibold text-truncate" style="max-width:150px">{{ $doc->name }}</p>
                    <small class="text-muted">{{ $doc->created_at->format('M d, Y') }}</small>
                  </div>
                </div>
                <div class="d-flex gap-1">
                  <a href="{{ asset('storage/'.$doc->link) }}" download class="btn btn-xs btn-icon btn-outline-success">
                    <i class="ti tabler-download fa-sm"></i>
                  </a>
                  @can('edit_property')
                    <a href="{{ route('attachment.delete', $doc->id) }}" onclick="confirmDelete(event)"
                       class="btn btn-xs btn-icon btn-outline-danger">
                      <i class="ti tabler-trash fa-sm"></i>
                    </a>
                  @endcan
                </div>
              </li>
            @endforeach
          </ul>

          @can('edit_property')
            <form id="add_docs_form" method="POST" action="{{ route('property.store') }}" enctype="multipart/form-data">
              @csrf
              <input type="hidden" name="section" value="documents">
              <input type="hidden" name="property_id" value="{{ $property->id }}">
              <input type="hidden" name="block_id" value="{{ $property->block_id }}">
            </form>
          @endcan
        @else
          <div class="text-center py-4 text-muted">
            <i class="ti tabler-files-off fs-2 opacity-25 d-block mb-1"></i>
            <small>No documents uploaded</small>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- ═══ RIGHT COLUMN ═══ --}}
  <div class="col-xl-8 col-lg-7">

    {{-- Basic Information --}}
    <div class="card shadow-sm rounded-3 mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
          <i class="ti tabler-info-circle me-2 text-primary"></i>Basic Information
        </h6>
        @can('edit_property')
          <button type="button" class="btn btn-sm btn-text-primary" data-bs-toggle="modal" data-bs-target="#editPropertyModal">
            <i class="ti tabler-edit me-1"></i>Edit
          </button>
        @endcan
      </div>
      <div class="card-body">
        <div class="row g-4">
          <div class="col-sm-6 col-md-4">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Property Name</small>
            <h6 class="mb-0">{{ $property->name ? ucfirst($property->name) : '—' }}</h6>
          </div>
          <div class="col-sm-6 col-md-4">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Property No</small>
            <h6 class="mb-0">#{{ ucfirst($property->property_no) }}</h6>
          </div>
          <div class="col-sm-6 col-md-4">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Type</small>
            <h6 class="mb-0">{{ ucfirst($property->type) }}</h6>
          </div>
          <div class="col-sm-6 col-md-4">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Category</small>
            <h6 class="mb-0">{{ ucfirst($property->category) }}</h6>
          </div>
          <div class="col-sm-6 col-md-4">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Block</small>
            <h6 class="mb-0">{{ ucfirst($property->block->name) }}</h6>
          </div>
          <div class="col-sm-6 col-md-4">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Society</small>
            <h6 class="mb-0">{{ ucfirst($property->block->society->name) }}</h6>
          </div>
          <div class="col-12">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Address</small>
            <h6 class="mb-0">{{ ucfirst($property->address) ?? '—' }}</h6>
          </div>
          <div class="col-sm-6 col-md-4">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Construction</small>
            <h6 class="mb-0">
              @if($property->is_constructed)
                <span class="badge bg-label-success">Completed</span>
              @else
                <span class="badge bg-label-warning">In Progress</span>
              @endif
            </h6>
          </div>
        </div>
      </div>
    </div>

    {{-- Dimensions Card --}}
    <div class="card shadow-sm rounded-3 mb-4">
      <div class="card-header">
        <h6 class="mb-0 fw-semibold">
          <i class="ti tabler-ruler me-2 text-primary"></i>Dimensions
        </h6>
      </div>
      <div class="card-body p-0">
        @if($property->dimensions && $property->dimensions->count())
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead class="table-light">
              <tr>
                <th class="ps-3 text-uppercase small fw-semibold text-muted" style="font-size:.7rem">Side</th>
                <th class="text-uppercase small fw-semibold text-muted" style="font-size:.7rem">Size</th>
                <th class="text-uppercase small fw-semibold text-muted" style="font-size:.7rem">Unit</th>
              </tr>
              </thead>
              <tbody>
              @foreach($property->dimensions as $dim)
                <tr>
                  <td class="ps-3 fw-semibold">{{ ucfirst($dim->name) }}</td>
                  <td>{{ $dim->size }}</td>
                  <td><span class="badge bg-label-primary">{{ ucfirst($dim->unit) }}</span></td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="text-center py-4 text-muted">
            <i class="ti tabler-ruler-off fs-2 opacity-25 d-block mb-1"></i>
            <small>No dimensions recorded</small>
          </div>
        @endif
      </div>
    </div>

    {{-- Timeline --}}
    <div class="card shadow-sm rounded-3">
      <div class="card-header">
        <h6 class="mb-0 fw-semibold">
          <i class="ti tabler-clock me-2 text-primary"></i>Timeline
        </h6>
      </div>
      <div class="card-body">
        <div class="row g-4">
          <div class="col-sm-6">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Created At</small>
            <h6 class="mb-0">{{ $property->created_at->format('F d, Y h:i A') }}</h6>
            <small class="text-muted">{{ $property->created_at->diffForHumans() }}</small>
          </div>
          <div class="col-sm-6">
            <small class="text-muted text-uppercase d-block mb-1 fw-semibold" style="font-size:.7rem; letter-spacing:.06em">Last Updated</small>
            <h6 class="mb-0">{{ $property->updated_at->format('F d, Y h:i A') }}</h6>
            <small class="text-muted">{{ $property->updated_at->diffForHumans() }}</small>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

@push('scripts')
  <script>
    function previewPropertyImg(event) {
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('propertyMainImg').src = e.target.result;
      };
      reader.readAsDataURL(event.target.files[0]);
    }
  </script>
@endpush
