@extends('layouts/layoutMaster')
@section('title', 'Unit Details')

@php
  use App\Helpers\GetArea;
  $hasRooms = $unit->rooms->count() > 0;
  $hasDimensions = $unit->dimensions->count() > 0;
  $unit_amenities = is_array($unit->amenities)
    ? $unit->amenities
    : json_decode($unit->amenities ?? '[]', true);
@endphp

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/notyf/notyf.scss','resources/assets/vendor/libs/dropzone/dropzone.scss','resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/dropzone/dropzone.js', 'resources/assets/vendor/libs/jquery-repeater/jquery-repeater.js' ,'resources/assets/vendor/libs/notyf/notyf.js','resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/forms-file-upload.js', 'resources/assets/js/repeater.js', 'resources/assets/js/common_property.js', 'resources/assets/js/edit_construction.js', 'resources/assets/js/ui-toasts.js', 'resources/assets/js/forms-selects.js'])
@endsection

@section('content')

  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      @if(!isset($isOwner))
        <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">
          {{ isset($society) && $user_type ? 'Society Management' : 'Property Management' }}
        </p>
      @endif
      <h4 class="mb-1">Unit Details</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}" class="text-dark opacity-75 text-decoration-none">Home</a>
          </li>
          @if(!isset($isOwner))
            @if(isset($society) && $user_type)
              <li class="breadcrumb-item">
                <a href="{{ route('societies.index', $user_type) }}"
                   class="text-dark opacity-75 text-decoration-none">Societies</a>
              </li>
              <li class="breadcrumb-item">
                <a href="{{ route('societies.show', [$user_type, $society->uuid]) }}"
                   class="text-dark opacity-75 text-decoration-none">Society</a>
              </li>
              <li class="breadcrumb-item">
                <a
                  href="{{ route('society.block.view', [$user_type, $society->uuid, $unit->floor->property->block->uuid]) }}"
                  class="text-dark opacity-75 text-decoration-none">Block</a>
              </li>
              <li class="breadcrumb-item">
                <a
                  href="{{ route('society.property.details', [$user_type, $society->uuid, $unit->floor->property->uuid]) }}"
                  class="text-dark opacity-75 text-decoration-none">Property</a>
              </li>
            @else
              <li class="breadcrumb-item">
                <a href="{{ route('blocks.index') }}" class="text-dark opacity-75 text-decoration-none">Blocks</a>
              </li>
              <li class="breadcrumb-item">
                <a href="{{ route('block.view', $unit->floor->property->block->uuid) }}"
                   class="text-dark opacity-75 text-decoration-none">Block</a>
              </li>
              <li class="breadcrumb-item">
                <a href="{{ route('property.details', $unit->floor->property->uuid) }}"
                   class="text-dark opacity-75 text-decoration-none">Property</a>
              </li>
            @endif

          @else
            <li class="breadcrumb-item">
              <a href="{{ route('owner.properties.show')  }}"
                 class="text-dark opacity-75 text-decoration-none">Properties</a>
            </li>
          @endif
          <li class="breadcrumb-item active text-dark opacity-50">Unit Details</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-home text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  {{-- Alerts --}}
  @if(session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
      <i class="ti tabler-circle-check-filled"></i> {{ session('success') }}
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
      <i class="ti tabler-alert-circle-filled"></i> {{ session('error') }}
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger alert-dismissible d-flex gap-3 mb-3" role="alert">
      <i class="ti tabler-alert-circle-filled fs-4 flex-shrink-0 mt-1"></i>
      <div>
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-1 small">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Unit header --}}
  <div class="d-flex align-items-start gap-3 mb-3">
    <div class="rounded-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
         style="width:48px;height:48px;">
      <i class="ti tabler-home text-primary fs-4"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
        <span class="fw-semibold">Unit {{ $unit->unit_name ? '— '.ucfirst($unit->unit_name) : '' }}</span>
        @if($unit->unit_type)
          <span class="badge rounded-pill bg-secondary bg-opacity-25 text-secondary border border-secondary small">
            {{ ucfirst($unit_types->firstWhere('id', $unit->unit_type)?->title) }}
          </span>
        @endif
      </div>
      <small class="text-muted">
        {{ ucfirst($unit->floor->property->block->society->name) }} →
        {{ ucfirst($unit->floor->property->block->name) }} →
        Property #{{ $unit->floor->property->property_no }}
      </small>
    </div>
  </div>

  {{-- Tabs --}}
  <div class="col-12">
    <ul class="nav nav-pills flex-column flex-sm-row gap-1 border rounded-3 p-1 bg-light">
      <li class="nav-item">
        <button class="nav-link active w-100 text-start" data-bs-toggle="pill" data-bs-target="#unit_info">
          <i class="ti tabler-home me-2 d-none d-sm-inline-block"></i> Unit Info
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#unit_gallery">
          <i class="ti tabler-photo me-2 d-none d-sm-inline-block"></i> Gallery
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#unit_specs">
          <i class="ti {{ $hasRooms ? 'tabler-door' : 'tabler-ruler-2' }} me-2 d-none d-sm-inline-block"></i>
          {{ $hasRooms ? 'Room Details' : 'Specifications' }}
        </button>
      </li>
      @if($unit->listing)
        <li class="nav-item">
          <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#unit_payment_planning">
            <i class="ti tabler-photo me-2 d-none d-sm-inline-block"></i> Payments Planning
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#unit_payments">
            <i class="ti tabler-photo me-2 d-none d-sm-inline-block"></i> Payments
          </button>
        </li>
      @endif
    </ul>
  </div>

  <div class="tab-content mt-3 p-0 mt-5">

    {{-- TAB 1: UNIT INFO --}}
    <div class="tab-pane fade show active" id="unit_info" role="tabpanel">
      <div class="row g-4">
        {{-- LEFT COLUMN --}}
        <div class="col-xl-4 col-lg-4 col-md-5">
          <div class="card mb-4 shadow-sm rounded-3">
            <div class="card-body text-center p-4">
              <div class="position-relative d-inline-block mb-3">
                <img id="unitAvatarPreview" class="rounded border img-fluid"
                     src="{{ $unit->attachment ? asset('storage/'.$unit->attachment->link) : asset('assets/img/my_images/dummy_property_image.png') }}"
                     width="150" height="150" style="object-fit:cover;">
                @can('edit_property')
                  <span class="position-absolute bottom-0 end-0" title="Click to change picture">
                    <i class="fa-solid fa-camera text-white bg-primary p-2 cursor-pointer"
                       onclick="document.getElementById('unitAvatarInput').click();"></i>
                  </span>
                  <form method="POST" action="{{ route('unit.store') }}"
                        enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                    <input type="hidden" name="section" value="documents">
                    <input type="hidden" name="request_type" value="document">
                    <input type="file" id="unitAvatarInput" name="main_pic" class="d-none"
                           accept="image/*"
                           onchange="previewAvatar(event), this.form.submit();">
                  </form>
                @endcan
              </div>
              <h5 class="mb-0 fw-semibold">{{ ucfirst($unit->name) }}</h5>
              <span class="badge {{ $unit->owner_id  ? 'bg-primary' : 'bg-secondary' }} mt-2">
                {{ ucfirst($unit->owner_id ? 'Sold-out' : 'Un-Sold') }}
              </span>

              @can('listing_property')
                <div class="mt-3 pt-3 border-top text-start">
                  <h6 class="fw-bold mb-1 d-flex align-items-center gap-2">Listing Status</h6>
                  <div
                    class="rounded-3 px-3 py-2 mb-3 border {{ $unit->listing ? 'border-success border-opacity-50 bg-success bg-opacity-10' : 'border-dashed' }}">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <div class="d-flex align-items-center gap-2">
                      <span
                        class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 listing-status-icon {{ $unit->listing ? 'bg-success bg-opacity-25' : '' }}">
                        <i
                          class="ti {{ $unit->listing ? 'tabler-tag text-success' : 'tabler-tag-off listing-status-icon-i' }}"></i>
                      </span>
                        <span class="fw-semibold small {{ $unit->listing ? 'text-success' : 'text-primary' }}">
                        {{ $unit->listing ? 'Listed' : 'Not Listed' }}
                      </span>
                      </div>
                      @if($unit->listing)
                        <a href="{{ route('listing.destroy', $unit->listing->uuid) }}" onclick="confirmDelete(event)"
                           class="d-inline-flex align-items-center gap-1 text-decoration-none badge bg-danger">
                          <i class="ti tabler-tag-off"></i> Unlist
                        </a>
                      @endif
                    </div>

                    @if(auth()->user()->can('see_inquiries') && $unit->listing?->inquiries?->count())
                      @if(isset($society) && $user_type)
                        <a class="btn btn-xs bg-secondary text-white rounded mb-1"
                           href="{{ route('society.property.inquiries.show', [$user_type, $society->uuid, $unit->listing->uuid]) }}">
                          See Inquiries
                        </a>
                      @else
                        <a class="btn btn-xs bg-secondary text-white rounded mb-1"
                           href="{{ route('property.inquiries.show', ['unit', $unit->uuid, $unit->listing?->uuid]) }}">
                          See Inquiries
                        </a>
                      @endif
                    @endif
                    <p class="mb-0 text-muted listing_description">
                      {{ $unit->listing ? 'This property has listed for ' . ucfirst($unit->listing?->listing_type) : 'This property has no active listing' }}
                    </p>
                  </div>
                  <button class="btn btn-sm w-100 d-flex align-items-center gap-2 fw-semibold sell_rent_btn"
                          data-bs-toggle="modal" data-bs-target="#sell_rent_modal"
                          data-type="unit" data-id="{{ $unit->id }}"
                          data-listing='@json($unit->listing)'>
                    <i class="ti tabler-tag sell_rent_icon"></i>
                    {{ $unit->listing ? 'Update Your Listing' : 'List for Sell / Rent' }}
                    <i class="ti tabler-arrow-right ms-auto listing_icon"></i>
                  </button>
                </div>
              @else
                <div class="mt-4 pt-4 border-top text-center">
                  <div class="avatar avatar-sm mb-2 mx-auto">
                   <span class="avatar-initial rounded bg-label-primary">
                   <i class="fa-regular fa-calendar"></i>
                  </span>
                  </div>
                  <small class="text-muted d-block">Created</small>
                  <h6 class="mb-0">{{ $unit->created_at->format('M d, Y') }}</h6>
                </div>
              @endcan

            </div>
          </div>

          {{-- Documents Card --}}
          <div class="card border-0 shadow-sm rounded-3 p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
              <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <span class="d-inline-block bg-primary rounded hr_bar"></span> Documents
              </h5>
              @can('edit_property')
                <div class="d-flex align-items-center gap-2">
                  <label class="btn btn-sm btn-outline-info d-flex align-items-center gap-1 mb-0">
                    <i class="fa-solid fa-plus"></i> Add File
                    <input type="file" class="file_trigger" multiple hidden>

                  </label>
                  <form id="unit_add_files_form" method="POST" action="{{ route('unit.store') }}"
                        enctype="multipart/form-data" class="d-flex align-items-center gap-2 m-0">
                    @csrf
                    <input type="file" name="documents[]" class="documents_input" multiple hidden>
                    <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                    <input type="hidden" name="section" value="documents">
                    <input type="hidden" name="request_type" value="document">
                    <button type="submit"
                            class="btn btn-sm btn-outline-success d-none d-flex align-items-center gap-1 px-3 save_files_btn">
                      <i class="fa-solid fa-floppy-disk"></i> Save
                    </button>
                  </form>
                </div>
              @endcan
            </div>
            @if (auth()->user()->can('edit_property'))
              <div class="d-flex justify-content-between align-items-center mt-1 mb-2">
                <div class="d-flex align-items-center gap-2 @if ($unit->attachments->count() == 0) d-none @endif">
                  <div class="form-check d-flex align-items-center m-0">
                    <input class="form-check-input checkbox me-2" type="checkbox" id="select_all">
                    <label class="form-check-label fw-semibold mb-0 cursor-pointer"
                           for="select_all">Select All</label>
                  </div>
                </div>
                <span data-url="{{ route('attachments.bulk_delete') }}" id="bulk_btn"
                      class="btn btn-xs btn-outline-danger waves-effect d-none bulk_delete_btn d-flex align-items-center gap-1 bulk_btn">
              <i class="fa-solid fa-trash fa-sm"></i> Delete
             </span>
              </div>
            @endif
            @php
              $documents = $unit->attachments->filter(fn($a) =>
                !in_array(strtolower($a->extension ?? ''), ['jpg','jpeg','png','gif','webp','mp4','mov','avi','webm'])
              );
            @endphp

            <div class="card-body p-0 mt-1">
              @if($documents->count() > 0)
                <div class="overflow-y-auto" style="max-height:400px;">
                  <ul class="list-unstyled mb-0">
                    @foreach($documents as $attachment)
                      <li class="py-2 border-bottom px-2">
                        <div class="d-flex align-items-center">
                          @if (auth()->user()->can('edit_property'))
                            <div class="form-check ps-0 pt-2 me-3">
                              <input type="checkbox" class="form-check-input-sm checkbox"
                                     value="{{ $attachment->id }}">
                            </div>
                          @endif
                          <div class="avatar p-2 avatar-sm me-3">
                            <span class="avatar-initial rounded bg-label-info">
                              <i class="fa-regular {{ \App\Helpers\FileHelper::getFileIcon($attachment->link) }}"></i>
                            </span>
                          </div>
                          <div class="flex-grow-1 min-w-0 me-2">
                            <h6 class="mb-0 text-break">{{ $attachment->name }}</h6>
                            <small class="text-muted">{{ $attachment->created_at->format('M d, Y') }}</small>
                          </div>
                          <div class="d-flex flex-column align-items-center gap-1">
                            @php $ext = pathinfo($attachment->link, PATHINFO_EXTENSION); @endphp
                            @if(!in_array($ext, ['xls','xlsx','csv']))
                              <a href="{{ asset('storage/'.$attachment->link) }}"
                                 target="_blank"
                                 class="btn btn-xs btn-icon btn-outline-primary">
                                <i class="fa-regular fa-eye fa-sm"></i>
                              </a>
                            @endif
                            <a href="{{ asset('storage/'.$attachment->link) }}" download
                               class="btn btn-xs btn-icon btn-outline-success">
                              <i class="fa-solid fa-download fa-sm"></i>
                            </a>
                            @can('edit_property')
                              <a href="{{ route('attachment.delete', $attachment->id) }}"
                                 onclick="confirmDelete(event)"
                                 class="btn btn-xs btn-icon btn-outline-danger">
                                <i class="fa-solid fa-trash fa-sm"></i>
                              </a>
                            @endcan
                          </div>
                        </div>
                      </li>
                    @endforeach
                  </ul>
                </div>
              @else
                <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
                  <i class="ti tabler-files-off fs-2 opacity-75 mb-1"></i>
                  <small>No documents uploaded</small>
                </div>
              @endif
            </div>
          </div>

        </div>

        {{-- RIGHT COLUMN --}}
        <div class="col-xl-8 col-lg-8 col-md-7">

          {{-- Basic Info Card --}}
          <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <span class="d-inline-block bg-primary rounded hr_bar"></span> Basic Information
              </h5>
              @can('edit_property')
                <span class="text-primary cursor-pointer" data-bs-toggle="modal"
                      data-bs-target="#edit_basic_unit"
                      title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </span>
              @endcan
            </div>
            <div class="row g-3">
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Unit Name</p>
                  <p class="fw-bold text-dark mb-0">{{ ucfirst($unit->unit_name) ?? '—' }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Unit Type</p>
                  <p
                    class="fw-semibold text-dark mb-0">{{ ucfirst($unit_types->firstWhere('id', $unit->unit_type)?->title) ?? '—' }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Floor</p>
                  <p
                    class="fw-semibold text-primary mb-0">{{ ucfirst($floor_types->firstWhere('id', $unit->floor->floor_type)?->title) }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Property</p>
                  <p class="fw-semibold text-primary mb-0">
                    #{{ $unit->floor->property->property_no }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Block</p>
                  <p class="fw-semibold text-primary mb-0">{{ $unit->floor->property->block->name }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Society</p>
                  <p class="fw-semibold text-primary mb-0">{{ $unit->floor->property->block->society->name }}</p>
                </div>
              </div>
            </div>
          </div>

          @can('delete_property')
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">

              <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <span class="d-inline-block bg-primary rounded hr_bar"></span> Delete Unit
              </h5>

              <p class="mb-4">
                Deleting this property unit will
                <strong class="text-danger fst-italic">permanently</strong>
                remove the society and all associated data.
                This action cannot be undone.
              </p>
              @php
                $unit_destroy_url = route('unit.destroy',isset($user_type)? [$unit->uuid, $user_type]: [$unit->uuid]);
              @endphp

              <a href="{{ $unit_destroy_url }}" class="btn btn-danger"
                 onclick="confirmDelete(event, null)">
                <i class="fa-solid fa-trash me-3"></i>
                Delete Unit
              </a>
            </div>
          @else
            <div class="card shadow-sm rounded-3">
              <div class="card-header">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                  <span class="d-inline-block bg-primary rounded hr_bar"></span> Timeline Information
                </h5>
              </div>
              <div class="card-body">
                <div class="row g-4">
                  <div class="col-md-6">
                    <div class="info-container">
                      <small class="text-muted text-uppercase d-block mb-1">Created At</small>
                      <h6 class="mb-0">{{ $unit->created_at->format('F d, Y h:i A') }}</h6>
                      <small class="text-muted">{{ $unit->created_at->diffForHumans() }}</small>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="info-container">
                      <small class="text-muted text-uppercase d-block mb-1">Last Updated</small>
                      <h6 class="mb-0">{{ $unit->updated_at->format('F d, Y h:i A') }}</h6>
                      <small class="text-muted">{{ $unit->updated_at->diffForHumans() }}</small>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          @endcan

        </div>
      </div>
    </div>

    {{-- TAB 2: GALLERY --}}
    <div class="tab-pane fade" id="unit_gallery" role="tabpanel">
      @include('components.gallery', ['model' => $unit])
    </div>

    {{-- TAB 3: ROOM DETAILS / SPECIFICATIONS --}}
    <div class="tab-pane fade" id="unit_specs" role="tabpanel">

      @if($hasRooms)
        {{-- Has rooms: show room grid --}}
        <div class="card border-0 shadow-sm rounded-3 p-4" id="specs_rooms_section">
          <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2 flex-wrap">
              <span class="d-inline-block bg-primary rounded hr_bar"></span>Room Details
              <span class="badge bg-primary bg-opacity-10 text-primary fw-normal">
            {{ $unit->rooms->count() }} {{ \Illuminate\Support\Str::plural('room', $unit->rooms->count()) }}
          </span>
            </h5>
            @can('edit_property')
              <span class="cursor-pointer btn btn-primary btn-sm"
                    data-bs-toggle="modal" data-bs-target="#room_modal"
                    data-floor-id="{{ $unit->floor_id }}" data-unit-id="{{ $unit->id }}">
                    <i class="ti tabler-plus me-1"></i> Add Room
              </span>
            @endcan

          </div>
          <div class="room-grid">
            @foreach($unit->rooms as $room)
              @php
                $room_amenities_data = is_array($room->amenities)
                    ? $room->amenities
                    : json_decode($room->amenities ?? '[]', true);
                $room_dimensions_data = $room->dimensions->map(function($d) {
                    return [
                        'id'   => $d->id,
                        'name' => $d->name,
                        'size' => round(app(\App\Http\Controllers\PropertiesController::class)->convertForUser($d->size, $d->unit), 4),
                        'unit' => $d->unit,
                    ];
                })->values()->all();
              @endphp
              @include('components.property.room_card', [
                'room'                 => $room,
                'room_modal_target'    => '#room_modal',
                'room_amenities_data'  => $room_amenities_data,
                'room_dimensions_data' => $room_dimensions_data,
              ])
            @endforeach
          </div>
        </div>

      @elseif(!$hasDimensions && count($unit_amenities) == 0)
        {{-- Nothing set yet — show choice screen --}}
        <div id="specs_choice_screen">
          <div class="card border-0 shadow-sm rounded-3 p-4 text-center">
            <i class="ti tabler-layout-grid fs-1 text-primary opacity-50 mb-3 d-block mx-auto"></i>
            <h5 class="fw-semibold mb-1">How is this unit structured?</h5>
            <p class="text-muted small mb-4">Choose how you'd like to add specifications for this unit.</p>
            <div class="row g-3 justify-content-center">
              <div class="col-sm-5">
                <button id="btn_unit_is_room"
                        class="btn btn-outline-primary w-100 p-3 d-flex flex-column align-items-center gap-2">
                  <i class="ti tabler-home fs-3"></i>
                  <span class="fw-semibold">Single Space</span>
                  <small class="text-muted fw-normal">Add dimensions &amp; amenities directly to this
                    unit</small>
                </button>
              </div>
              <div class="col-sm-5">
                <button id="btn_unit_has_rooms"
                        class="btn btn-outline-secondary w-100 p-3 d-flex flex-column align-items-center gap-2"
                        data-bs-toggle="modal" data-bs-target="#room_modal" data-mode="add">
                  <i class="ti tabler-door fs-3"></i>
                  <span class="fw-semibold">Multiple Rooms</span>
                  <small class="text-muted fw-normal">Add individual rooms with their own
                    dimensions</small>
                </button>
              </div>
            </div>
          </div>
        </div>

        {{-- Unit-as-room: specs form (hidden until choice made) --}}
        <div id="specs_unit_form" class="d-none">
          <div class="card border-0 shadow-sm rounded-3 p-4">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
              <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <span class="d-inline-block bg-primary rounded hr_bar"></span> Specifications
              </h5>
            </div>
            <form method="POST" action="{{ route('unit.store') }}">
              @csrf
              <input type="hidden" name="unit_id" value="{{ $unit->id }}">
              <input type="hidden" name="section" value="specifications">
              <p class="text-muted text-uppercase fw-semibold mb-0 specs_labels">Dimensions</p>
              @include('components.property.dimensions', ['isUnitDetailsPage' => true])
              <p class="text-muted text-uppercase fw-semibold mb-3 mt-4 specs_labels">Amenities</p>
              <div class="row g-2 mb-4">
                @foreach($amenities as $a)
                  <div class="col-sm-6 col-lg-4">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="amenities[]"
                             value="{{ $a->id }}" id="amenity_{{ $a->id }}"
                        {{ in_array($a->id, $unit_amenities) ? 'checked' : '' }}>
                      <label class="form-check-label small"
                             for="amenity_{{ $a->id }}">{{ ucwords($a->title) }}</label>
                    </div>
                  </div>
                @endforeach
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary"
                        onclick="document.getElementById('specs_choice_screen').classList.remove('d-none'); document.getElementById('specs_unit_form').classList.add('d-none');">
                  Back
                </button>
                <button type="submit" class="btn btn-primary">
                  <i class="fa-solid fa-floppy-disk me-1"></i> Save Specifications
                </button>
              </div>
            </form>
          </div>
        </div>

      @else
        {{-- Has dimensions or amenities — show specs + edit modal --}}
        <div class="card border-0 shadow-sm rounded-3 p-4">
          <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
              <span class="d-inline-block bg-primary rounded hr_bar"></span> Specifications
            </h5>
            @can('edit_property')
              <span class="text-primary cursor-pointer" data-bs-toggle="modal"
                    data-bs-target="#edit_specs_modal"
                    title="Edit Specifications">
            <i class="fa-solid fa-pen-to-square"></i>
          </span>
            @endcan
          </div>

          @if($hasDimensions)
            <p class="text-muted text-uppercase fw-semibold mb-3 specs_labels">Dimensions</p>
            <div class="row g-3 mb-3">
              @foreach($unit->dimensions as $dim)
                @php $val = app()->make(\App\Http\Controllers\PropertiesController::class)->convertForUser($dim->size, $dim->unit); @endphp
                <div class="col-sm-6 col-lg-4">
                  <div class="bg-light rounded-3 p-3 h-100 d-flex align-items-center gap-3">
                <span
                  class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                  style="width:36px;height:36px;">
                  <i class="ti tabler-ruler-2 text-primary"></i>
                </span>
                    <div>
                      <p class="text-muted fw-semibold mb-0 card_label">{{ ucfirst($dim->name) }}</p>
                      <p class="fw-bold text-dark mb-0">
                        {{ round($val, 2) }}
                        <span class="fw-normal text-muted small">{{ ucfirst($dim->unit) }}</span>
                      </p>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
            <div
              class="rounded-3 p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2 bg-primary">
              <div class="d-flex align-items-center gap-3">
            <span
              class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0"
              style="width:36px;height:36px;">
              <i class="ti tabler-square text-white"></i>
            </span>
                <div>
                  <span class="d-block fw-semibold text-white">Total Area</span>
                  <small class="text-white text-opacity-75">Combined area of all dimensions</small>
                </div>
              </div>
              <div class="text-end">
                <span class="fs-4 fw-bold text-white">{{ round(GetArea::calculate($unit->dimensions), 2) }}</span>
                <span class="text-white text-opacity-75 small ms-1">sq ft</span>
              </div>
            </div>
          @endif

          @if(count($unit_amenities) > 0)
            @if($hasDimensions)
              <hr class="my-1 mb-4">
            @endif
            <p class="text-muted text-uppercase fw-semibold mb-3 specs_labels">
              Amenities
              <span
                class="badge bg-primary bg-opacity-10 text-primary fw-normal ms-1">{{ count($unit_amenities) }}</span>
            </p>
            <div class="row g-2">
              @foreach($amenities->whereIn('id', $unit_amenities) as $a)
                <div class="col-sm-6 col-lg-4 col-xl-3">
                  <div class="bg-light rounded-3 p-2 px-3 d-flex align-items-center gap-2">
                <span
                  class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                  style="width:28px;height:28px;">
                  <i class="ti tabler-check text-success" style="font-size:.8rem;"></i>
                </span>
                    <span class="fw-semibold small text-dark">{{ ucwords($a->title) }}</span>
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
    </div>
    {{-- Edit Specifications Modal --}}
    @can('edit_property')
      <div class="modal fade" id="edit_specs_modal" tabindex="-1" aria-labelledby="editSpecsModalLabel"
           aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-bold d-flex align-items-center gap-2"
                  id="editSpecsModalLabel">
                <span class="d-inline-block bg-primary rounded hr_bar"></span>
                Edit Specifications
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"
                      aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('unit.store') }}">
              @csrf
              <input type="hidden" name="unit_id" value="{{ $unit->id }}">
              <input type="hidden" name="section" value="specifications">
              <div class="modal-body pt-3">
                <p class="text-muted text-uppercase fw-semibold mb-0 specs_labels">
                  Dimensions</p>
                @include('components.property.dimensions', ['isUnitDetailsPage' => true])
                <p class="text-muted text-uppercase fw-semibold mb-3 mt-4 specs_labels">
                  Amenities</p>
                <div class="row g-2">
                  @foreach($amenities as $a)
                    <div class="col-sm-6 col-lg-4">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="amenities[]"
                               value="{{ $a->id }}" id="amenity_{{ $a->id }}"
                          {{ in_array($a->id, $unit_amenities) ? 'checked' : '' }}>
                        <label class="form-check-label small"
                               for="amenity_{{ $a->id }}">{{ ucwords($a->title) }}</label>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
              <div class="modal-footer border-0 d-flex flex-column pt-0 gap-1">
                <button type="button" class="btn btn-outline-secondary w-100 me-0"
                        data-bs-dismiss="modal">Cancel
                </button>
                <button type="submit" class="btn btn-primary w-100 ms-0">
                  <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endcan
    @endif


    <div class="tab-pane fade" id="unit_payment_planning" role="tabpanel">
      @include('components.property.payments-planning' , ['property' => $unit])
    </div>
    {{-- TAB 3: Payments --}}
    <div class="tab-pane fade" id="unit_payments" role="tabpanel">
      @include('components.property.payments')
    </div>
  </div>

  {{-- Edit Basic Unit Modal --}}
  @can('edit_property')
    <div class="modal fade" id="edit_basic_unit" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">

          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="editSpecsModalLabel">
              <span class="d-inline-block bg-primary rounded " style="width:3px;height:18px;"></span>
              Edit Unit
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <form method="POST" action="{{ route('unit.store') }}">
            @csrf
            <input type="hidden" name="unit_id" value="{{ $unit->id }}">
            <input type="hidden" name="section" value="basic">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">Unit Name</label>
                <input type="text" name="unit_name" class="form-control" value="{{ $unit->unit_name }}">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Unit Type</label>
                <select name="unit_type" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach($unit_types as $ut)
                    <option value="{{ $ut->id }}" {{ $unit->unit_type == $ut->id ? 'selected' : '' }}>
                      {{ ucfirst($ut->title) }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel
              </button>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endcan

  @include('_partials._modals.room')
  @include('components.property.templates.dimension')
  @include('components.property.templates.room')
  @include('_partials._modals.listing_property')

@endsection

@push('styles')
  <style>

    .sell_rent_btn {
      background: linear-gradient(135deg, #6c63ff, #534ab7);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 10px;
      font-size: .82rem;
    }

    .sell_rent_btn:hover {
      background: linear-gradient(135deg, #534ab7, #3c3489);
      color: #fff;
    }

    .listing-status-icon {
      width: 32px;
      height: 32px;
      background: #eeedfe;
    }

    .listing-status-icon-i {
      color: #7f77dd;
      font-size: .9rem;
    }

    .listing_description {
      font-size: .65rem;
      color: #6c757d;
    }

    .border-dashed {
      border-style: dashed !important;
    }

    .specs_labels {
      font-size: .68rem;
      letter-spacing: .07em;
    }
  </style>
@endpush

@push('scripts')
  <script>
    window.unitUpdateRoute = "{{ route('unit.update') }}";

    // Listing modal
    var sellRentModal = document.getElementById('sell_rent_modal');
    if (sellRentModal) {
      sellRentModal.addEventListener('show.bs.modal', function(e) {
        var btn = e.relatedTarget;
        if (!btn) return;
        var listing = btn.dataset.listing ? JSON.parse(btn.dataset.listing) : null;
        var form = document.getElementById('listing_form');
        form.querySelector('[name="type"]').value = btn.dataset.type;
        form.querySelector('[name="id"]').value = btn.dataset.id;
        form.querySelector('[name="ids"]').value = '';
        document.getElementById(listing?.listing_type === 'rent' ? 'type_rent' : 'type_sell').checked = true;
        document.getElementById('price').value = listing?.price ?? '';
        form.querySelector('[name="unit"]').value = listing?.unit ?? 'PKR';
        document.getElementById('show_price').checked = listing ? !!listing.show_price : true;
        document.getElementById('contact').value = listing?.contact_no ?? '';
        document.getElementById('show_contact').checked = listing ? !!listing.show_contact_no : true;
        document.getElementById('email').value = listing?.email ?? '';
        document.getElementById('description').value = listing?.description ?? '';
      });
    }


    // Specs tab — unit-type choice
    document.getElementById('btn_unit_is_room')?.addEventListener('click', function() {
      document.getElementById('specs_choice_screen').classList.add('d-none');
      document.getElementById('specs_unit_form').classList.remove('d-none');
    });

    document.getElementById('btn_unit_has_rooms')?.addEventListener('click', function() {
      document.getElementById('specs_choice_screen').classList.add('d-none');
      document.getElementById('specs_rooms_section').classList.remove('d-none');
    });
  </script>
@endpush
