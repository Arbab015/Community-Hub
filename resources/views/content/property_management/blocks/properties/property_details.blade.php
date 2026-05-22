@extends('layouts/layoutMaster')
@section('title', 'Property details')
@php
  use Illuminate\Support\Str;
use App\Helpers\GetArea;
$marla_size = $property->block->society->marla_size;
$default_marla_size = $marla_size != 0.0 ? $marla_size : 272.25;
 $address = '
    '
        . ucfirst($property->street) . ', ' .
        ($property->landmark
            ? '<span class="fst-italic">Near, ' . ucfirst($property->landmark) . '</span>, '
            : '') .
        '<span class="fst-italic">' . ucfirst($property->block->name) . '</span>,
        <span class="fst-italic">' . ucfirst($property->block->society->name) . '</span>,
        <span class="fst-italic fw-semibold">' . ucfirst($property->block->society->city) . '</span>,
        <span class="fst-italic fw-semibold">' . ucfirst($property->block->society->country) . '</span>
';
@endphp

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/notyf/notyf.scss','resources/assets/vendor/libs/dropzone/dropzone.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/dropzone/dropzone.js', 'resources/assets/vendor/libs/notyf/notyf.js','resources/assets/vendor/libs/jquery-repeater/jquery-repeater.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/forms-file-upload.js','resources/assets/js/repeater.js', 'resources/assets/js/common_property.js','resources/assets/js/edit_construction.js','resources/assets/js/ui-toasts.js' ])
@endsection

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1"> @if(isset($society) && $user_type)
          Society Management
        @else
          Property Management
        @endif</p>
      <h4 class="mb-1">Property Details</h4>
      @php
        $block = $property->block;
      @endphp
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}" class="text-dark opacity-75 text-decoration-none">Home</a>
          </li>
          @if(isset($society) && $user_type)
            <li class="breadcrumb-item">
              <a href="{{ route('societies.index', $user_type) }}" class="text-dark opacity-75 text-decoration-none">Societies</a>
            </li>
            <li class="breadcrumb-item">
              <a href="{{ route('societies.show', [$user_type, $society->uuid]) }}"
                 class="text-dark opacity-75 text-decoration-none">Society</a>
            </li>

            <li class="breadcrumb-item">
              <a href="{{ route('society.block.view', [$user_type, $society->uuid, $property->block->uuid]) }}"
                 class="text-dark opacity-75 text-decoration-none">Block details</a>
            </li>
          @else
            <li class="breadcrumb-item">
              <a href="{{ route('blocks.index') }}" class="text-dark opacity-75 text-decoration-none">Blocks</a>
            </li>
            <li class="breadcrumb-item">
              <a href="{{ route('block.view', $property->block->uuid)  }}"
                 class="text-dark opacity-75 text-decoration-none">Block details</a>
            </li>
          @endif
          <li class="breadcrumb-item active text-dark opacity-50">Property details</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-building-estate text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  {{-- ALERTS --}}
  @if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
      <i class="ti tabler-circle-check-filled"></i> {{ session('success') }}
    </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
      <i class="ti tabler-alert-circle-filled"></i> {{ session('error') }}
    </div>
  @endif

  {{--GLOBAL VALIDATION SUMMARY (shows which tab has errors) --}}
  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible d-flex gap-3 mb-3" role="alert">
      <i class="ti tabler-alert-circle-filled fs-4 flex-shrink-0 mt-1"></i>
      <div>
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-1 small">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
  @endif


  <div class="d-flex align-items-start gap-3 mb-3">
    {{-- Icon --}}
    <div class="rounded-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
         style="width:48px; height:48px;">
      <i class="ti tabler-building-estate text-primary fs-4"></i>
    </div>
    <div class="flex-grow-1 min-w-0">

      {{-- Type, property no + badges --}}
      <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
        <span class="fw-semibold">{{ ucfirst($property->type) }} #{{ $property->property_no }}</span>
        <span class="badge rounded-pill bg-secondary bg-opacity-25 text-secondary border border-secondary  small">
          {{ ucfirst($property->category) }}
        </span>
        <span
          class="badge rounded-pill bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 small">
          {{ ucwords(str_replace('_', ' ', $property->construction_status)) }}
        </span>
      </div>

      {{-- Name --}}
      @if($property->name)
        <h6 class="mb-0 fw-semibold ">
          {{ $property->name }}
        </h6>
      @else
        <span>
        {!! $address !!}
        </span>
      @endif
    </div>
  </div>


  <div class="col-12">
    <ul class="nav nav-pills flex-column flex-sm-row gap-1 border rounded-3 p-1 bg-light">
      <li class="nav-item">
        <button class="nav-link active  w-100 text-start" data-bs-toggle="pill" data-bs-target="#info">
          <i class="ti tabler-home me-2 d-none d-sm-inline-block"></i> Property Info
        </button>
      </li>

      <li class="nav-item">
        <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#gallery">
          <i class="ti tabler-photo me-2 d-none d-sm-inline-block"></i> Gallery
        </button>
      </li>

      @if($property->construction_status !== "pending")
        <li class="nav-item">
          <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#construction_details">
            <i class="ti tabler-building me-2 d-none d-sm-inline-block"></i> Construction Details
          </button>
        </li>
      @endif
    </ul>
  </div>
  <div class="tab-content mt-3 p-0 mt-5">

    {{-- TAB 1: PROPERTY DETAILS --}}
    <div class="tab-pane fade show active" id="info" role="tabpanel">
      <div class="row g-4">

        {{-- LEFT COLUMN --}}
        <div class="col-xl-4 col-lg-4 col-md-5">

          <div class="card mb-4 shadow-sm rounded-3">
            <div class="card-body text-center p-4">
              <div class="position-relative d-inline-block mb-3">
                <img id="societyAvatarPreview" class="rounded border img-fluid"
                     src="{{ $property->attachment ? asset('storage/' . $property->attachment->link) : asset('assets/img/my_images/dummy_property_image.png') }}"
                     width="150" height="150" style="object-fit:cover;">
                @if (auth()->user()->can('edit_property') )
                  <span class="position-absolute bottom-0 end-0" title="Click to change picture">
                <i class="fa-solid fa-camera text-white bg-primary p-2 cursor-pointer"
                   onclick="document.getElementById('avatarInput').click();"></i>
              </span>
                  <form id="add_img_form" method="POST" action="{{ route('property.store') }}"
                        enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="block_id" value="{{ $block->id }}">
                    <input type="hidden" name="section" value="documents">
                    <input type="hidden" name="property_id" value="{{ $property->id ?? '' }}">
                    <input type="hidden" name="request_type" value="document">
                    <input type="file" id="avatarInput" name="main_pic" class="d-none" accept="image/*"
                           onchange="previewAvatar(event), this.form.submit();">
                  </form>
                @endif
              </div>
              <h5 class="mb-0 fw-semibold">{{ ucfirst($property->name) }}</h5>
              <span class="badge {{ $property->construction_status == 'active' ? 'bg-primary' : 'bg-secondary' }} mt-2">
            {{ ucfirst($property->construction_status) }}
          </span>
              <div class="mt-3 pt-3 border-top text-center">
                <div class="avatar avatar-sm mb-2 mx-auto">
              <span class="avatar-initial rounded bg-label-primary">
                <i class="fa-regular fa-calendar"></i>
              </span>
                </div>
                <small class="text-muted d-block">Created</small>
                <h6 class="mb-0">{{ $property->created_at->format('M d, Y') }}</h6>
              </div>
            </div>
          </div>

          {{-- Documents Card --}}
          <div class="card border-0 shadow-sm rounded-3 p-4 ">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
              <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <span class="d-inline-block bg-primary rounded hr_bar"></span>
                Documents
              </h5>
              @can('edit_property')
                <div class="d-flex align-items-center gap-2">
                  <label class="btn btn-sm btn-outline-info d-flex align-items-center gap-1 mb-0">
                    <i class="fa-solid fa-plus"></i> Add File
                    <input type="file" id="file_trigger" multiple hidden>
                  </label>
                  <form id="add_files_form" method="POST" action="{{ route('property.store') }}"
                        enctype="multipart/form-data" class="d-flex align-items-center gap-2 m-0">
                    @csrf
                    <input type="file" name="documents[]" id="documents_input" multiple hidden>
                    <input type="hidden" name="block_id" value="{{ $property->block->id }}">
                    <input type="hidden" name="section" value="documents">
                    <input type="hidden" name="request_type" value="document">
                    <input type="hidden" name="property_id" value="{{ $property->id ?? '' }}">
                    <button type="submit" id="save_files_btn"
                            class="btn btn-sm btn-outline-success d-none d-flex align-items-center gap-1 px-3">
                      <i class="fa-solid fa-floppy-disk"></i> Save
                    </button>
                  </form>
                </div>
              @endcan
            </div>

            @if (auth()->user()->can('edit_property'))
              <div class="d-flex justify-content-between align-items-center mt-1 mb-2">
                <div class="d-flex align-items-center gap-2 @if ($property->attachments->count() == 0) d-none @endif">
                  <div class="form-check d-flex align-items-center m-0">
                    <input class="form-check-input checkbox me-2" type="checkbox" id="select_all">
                    <label class="form-check-label fw-semibold mb-0 cursor-pointer" for="select_all">Select All</label>
                  </div>
                </div>
                <span data-url="{{ route('attachments.bulk_delete') }}" id="bulk_btn"
                      class="btn btn-xs btn-outline-danger waves-effect d-none bulk_delete_btn d-flex align-items-center gap-1">
              <i class="fa-solid fa-trash fa-sm"></i> Delete
            </span>
              </div>
            @endif

            @php
              $documents = $property->attachments->filter(function($a){
                return !in_array(strtolower($a->extension ?? ''), ['jpg','jpeg','png','gif','webp','mp4','mov','avi','webm']);
              });
            @endphp

            <div class="card-body p-0 mt-1">
              @if($documents->count() > 0)
                <div class="overflow-y-auto" style="max-height:400px;">
                  <ul class="list-unstyled mb-0">
                    @foreach ($documents as $attachment)
                      <li class="py-2 border-bottom file_item px-2">
                        <div class="d-flex align-items-center">
                          @if (auth()->user()->can('edit_property'))
                            <div class="form-check ps-0 pt-2 me-3">
                              <input type="checkbox" class="form-check-input-sm checkbox" value="{{ $attachment->id }}">
                            </div>
                          @endif
                          <div class="avatar p-2 avatar-sm me-3">
                        <span class="avatar-initial rounded bg-label-info">
                          <i class="fa-regular {{ \App\Helpers\FileHelper::getFileIcon($attachment->link) }}"></i>
                        </span>
                          </div>
                          <div class="flex-grow-1 d-flex align-items-center justify-content-between min-w-0">
                            <div class="min-w-0 me-2">
                              <h6 class="mb-0 text-break">{{ $attachment->name }}</h6>
                              <small class="text-muted">{{ $attachment->created_at->format('M d, Y') }}</small>
                            </div>
                          </div>
                          <div class="d-flex flex-column align-items-center gap-1 ">
                            @cannot('edit_property')
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
                            <a href="{{ route('attachment.delete', [$attachment->id]) }}" title="Delete"
                               onclick="confirmDelete(event)" class="btn btn-xs btn-icon btn-outline-danger">
                              <i class="fa-solid fa-trash fa-sm"></i>
                            </a>
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

          {{-- Basic Information Card --}}
          <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><h5
                class="fw-bold mb-0 d-flex align-items-center gap-2"><span
                  class="d-inline-block bg-primary rounded hr_bar"></span> Basic Information </h5> <span
                class="text-primary cursor-pointer" data-bs-toggle="modal" data-bs-target="#edit_basic_property"
                title="Edit"> <i class="fa-solid fa-pen-to-square"></i> </span></div>
            <div class="row g-3 mb-3">
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3"><p
                    class="text-muted small text-uppercase fw-semibold mb-1 card_label">Property no</p>
                  <p class="fw-bold text-dark mb-0">#{{ $property->property_no }}</p></div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3"><p
                    class="text-muted small text-uppercase fw-semibold mb-1 card_label">Category</p>
                  <p class="fw-semibold text-dark mb-0">{{ ucfirst($property->category) }}</p></div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3"><p
                    class="text-muted small text-uppercase fw-semibold mb-1 card_label">Type</p>
                  <p class="fw-semibold text-dark mb-0">{{ ucfirst($property->type) }}</p></div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3"><p
                    class="text-muted small text-uppercase fw-semibold mb-1 card_label">Status</p>
                  <p
                    class="fw-semibold mb-0 text-primary">{{ ucwords(str_replace('_', ' ', $property->construction_status)) }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3"><p
                    class="text-muted small text-uppercase fw-semibold mb-1 card_label">Block</p>
                  <p class="fw-semibold mb-0 text-primary">{{ $property->block->name }}</p></div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3"><p
                    class="text-muted small text-uppercase fw-semibold mb-1 card_label">Society</p>
                  <p class="fw-semibold mb-0 text-primary">{{ $property->block->society->name }}</p></div>
              </div>
            </div>
            <hr>
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2"><span
                class="d-inline-block bg-primary rounded hr_bar"></span> Address </h5>
            <p class="text-muted small mb-0 ps-1">
              <i class="ti tabler-map-pin me-1 "></i>
              {!! $address !!}
            </p>
          </div>

          {{-- Dimensions Card --}}
          <div class="card border-0 shadow-sm rounded-3 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><h5
                class="fw-bold mb-0 d-flex align-items-center gap-2"><span
                  class="d-inline-block bg-primary rounded hr_bar"></span> Property Dimensions </h5> <span
                class="text-primary cursor-pointer" data-bs-toggle="modal" data-bs-target="#edit_dimensions_modal"
                title="Edit"> <i class="fa-solid fa-pen-to-square"></i> </span>
            </div> @foreach($property->dimensions as $dimension)
              @php $converted_size = app()->make(\App\Http\Controllers\PropertiesController::class)->convertForUser($dimension->size, $dimension->unit); @endphp
              <div class="border border-secondary rounded mb-2 p-2">
                <div class="d-flex justify-content-between align-items-center"><span
                    class="fw-semibold">{{ ucfirst($dimension->name) }}</span>
                  <div class="d-flex align-items-center gap-1"><span
                      class="fw-semibold">{{ round($converted_size, 2) }}</span> <span
                      class="badge bg-label-secondary">{{ ucfirst($dimension->unit) }}</span></div>
                </div>
              </div>
            @endforeach @php $area_sqft = GetArea::calculate($property->dimensions);  $area = ""; $unit = ""; if ($area_sqft >= $default_marla_size) { $in_marla = $area_sqft / $default_marla_size; if ($in_marla >= 20) { $area = $in_marla / 20; $unit = $area > 1 ? "Kanals" : "Kanal"; } else { $area = $in_marla; $unit = $in_marla > 1 ? "Marlas" : "Marla"; } } else { $area = $area_sqft; $unit = "Square Feet"; } @endphp
            <div class="border border-secondary rounded mb-2 p-2">
              <div class="d-flex justify-content-between align-items-center"><span class="fw-semibold">Total Area</span>
                <div class="d-flex align-items-center gap-1"><span class="fw-semibold">{{ round($area, 2) }}</span>
                  <span class="badge bg-label-secondary">{{ $unit }}</span></div>
              </div>
            </div>

          </div>

        </div>
      </div>
    </div>

    {{-- TAB 2: GALLERY --}}
    <div class="tab-pane fade" id="gallery" role="tabpanel">
      @include('components.gallery')
    </div>

    {{-- TAB 3: CONSTRUCTION DETAILS --}}
    <div class="tab-pane fade" id="construction_details" role="tabpanel">

      {{-- Header --}}
      <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-2">
          <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
            <span class="d-inline-block bg-primary rounded hr_bar"></span>
            Construction Details
          </h5>
          {{-- large screens: inline next to heading --}}
          <span
            class="badge bg-primary bg-opacity-10 text-primary fw-normal d-none d-sm-inline-flex">{{ $property?->property_no }}</span>
        </div>
        <span class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 cursor-pointer"
              data-bs-toggle="modal" data-bs-target="#edit_construction_modal">
    <i class="fa-solid fa-pen-to-square fa-xs"></i> Edit details
  </span>
      </div>

      {{-- small screens: own row below --}}
      <div class="d-sm-none mb-4 mt-n3">
        <span class="badge bg-primary bg-opacity-10 text-primary fw-normal">{{ $property?->property_no }}</span>
      </div>

      @if($property->construction_status !== "pending" && $property->floors->count())

        @php
          $totalUnits = $property->floors->sum(fn($f) => $f->units->count());
          $totalRooms = $property->floors->sum(fn($f) =>
            $f->rooms->count() + $f->units->sum(fn($u) => $u->rooms->count())
          );
        @endphp

        {{-- Floors --}}
        @foreach($property->floors as $floor)
          <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-3">

            {{-- Floor header --}}
            <div class="floor-header-border bg-label-primary d-flex  justify-content-between flex-wrap gap-2 px-3 py-2">
              <div class="d-flex align-items-center gap-2">
                <div
                  class="floor-icon-wrap d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                  <i class="ti tabler-building"></i>
                </div>
                <div>
                  <div
                    class="floor-name fw-semibold">{{ ucfirst($floor_types->firstWhere('id', $floor->floor_type)?->title) }}</div>
                  <div class="floor-meta">
                    @if($floor->units->count())
                      {{ $floor->units->count() }} {{ Str::plural('unit', $floor->units->count()) }}
                      · {{ $floor->units->sum(fn($u) => $u->rooms->count()) }} {{ Str::plural('room', $floor->units->sum(fn($u) => $u->rooms->count())) }}
                    @elseif($floor->rooms->count())
                      {{ $floor->rooms->count() }} {{ Str::plural('room', $floor->rooms->count()) }}
                    @else
                      No rooms or units
                    @endif
                  </div>
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <a href="{{ route('property.destroy', ['section'=>'floor', $floor->uuid]) }}"
                   onclick="confirmDelete(event, null)"
                   class="btn-del-labeled text-decoration-none d-inline-flex align-items-center gap-1">
                  <i class="ti tabler-trash"></i> Delete floor
                </a>
                <button class="btn btn-sm  btn-link text-primary p-0" onclick="cdToggle(this, '.card-body')">
                  <span class="chevron  "></span>
                </button>
              </div>
            </div>

            <div class="card-body px-3 py-3">

              {{-- Units --}}
              @if($floor->units->count())
                <p class="section-sublabel mb-2">Units</p>
                @foreach($floor->units as $unit)
                  <div class="border rounded-3 overflow-hidden mb-3">
                    <div class="unit-header-bg d-flex align-items-center flex-wrap gap-2 px-3 py-2">
                      <div
                        class="unit-icon-wrap d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                        <i class="ti tabler-home"></i>
                      </div>
                      <span class="unit-name fw-semibold">
    Unit {{ $unit->unit_name ? ' — '.ucfirst($unit->unit_name) : '' }}
  </span>
                      @if($unit->unit_type)
                        {{-- large screens: inline next to name --}}
                        <div class="badge rounded-pill unit-type-pill d-none d-sm-inline-flex">
                          {{ ucfirst($unit_types->firstWhere('id', $unit->unit_type)?->title) }}
                        </div>
                      @endif
                      <a href="{{ route('property.destroy', ['section'=>'unit', $unit->uuid]) }}"
                         onclick="confirmDelete(event, null)"
                         class="btn-del-labeled text-decoration-none d-inline-flex align-items-center gap-1 ms-auto">
                        <i class="ti tabler-trash"></i> Delete unit
                      </a>

                      @if($unit->unit_type)
                        {{-- small screens: own row below --}}
                        <div class="d-sm-none w-100">
                          <div class="badge rounded-pill unit-type-pill">
                            {{ ucfirst($unit_types->firstWhere('id', $unit->unit_type)?->title) }}
                          </div>
                        </div>
                      @endif
                    </div>

                    @if($unit->rooms->count())
                      <div class="p-3">
                        <p class="section-sublabel mb-2">Rooms</p>
                        <div class="room-grid">
                          @foreach($unit->rooms as $room)
                            @include('components.property.room_card', ['room' => $room])
                          @endforeach
                        </div>
                      </div>
                    @else
                      <div class="p-2">
                        <div class="d-flex flex-wrap gap-1 mb-2">
                          @php
                            $unit_amenities = json_decode($unit->amenities ?? '[]', true);
                          @endphp
                          @foreach($amenities->whereIn('id', $unit_amenities) as $a)
                            <span class="amenity-chip d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1">
                               <i class="ti tabler-check text-success"></i> {{ ucwords($a->title) }}
                            </span>
                          @endforeach
                        </div>
                        <div class="d-flex flex-column gap-1">
                          <div class="d-flex flex-wrap gap-1">
                            @foreach($unit['dimensions'] as $dim)
                              @php $val = app()->make(\App\Http\Controllers\PropertiesController::class)->convertForUser($dim->size, $dim->unit); @endphp
                              <span class="dim-side-chip d-inline-flex align-items-center gap-1 rounded px-2 py-1">
                             <span class="dim-side-name">{{ ucfirst($dim->name) }}</span>
                                 {{ round($val, 2) }}
                                                    <span class="dim-side-unit ">{{ $dim->unit }}</span>
                               </span>
                            @endforeach
                          </div>
                          <span
                            class="area-chip d-inline-flex align-items-center gap-1 rounded px-2 py-1 align-self-start">
                             <span class="area-val">{{ GetArea::calculate($unit->dimensions) }}</span>
                             <span class="area-unit">sq ft</span>
                           </span>
                        </div>
                      </div>
                    @endif
                  </div>
                @endforeach
              @endif

              {{-- Direct floor rooms --}}
              @if($floor->rooms->count())
                <p class="section-sublabel mb-2">Rooms</p>
                <div class="room-grid">
                  @foreach($floor->rooms as $room)
                    @include('components.property.room_card', ['room' => $room])
                  @endforeach
                </div>
              @elseif(!$floor->units->count())
                <p class="text-muted small fst-italic mb-0">No rooms added to this floor.</p>
              @endif

            </div>
          </div>
        @endforeach

      @else
        <div class="card border-0 shadow-sm rounded-3 p-5 d-flex flex-column align-items-center justify-content-center">
          <i class="ti tabler-building-off fs-1 text-muted mb-3"></i>
          <p class="text-muted mb-0">No construction details available.</p>
        </div>
      @endif

    </div>

  </div>
  @include('_partials._modals.edit_property')

@endsection

@push('styles')
  <style>
    .card_label {
      font-size: .65rem;
      letter-spacing: .06em;
    }

    .hr_bar {
      width: 3px;
      height: 16px;
    }

    .floor-header-border {
      border-bottom: 1px solid #b5aeff;
    }

    .floor-icon-wrap {
      width: 30px;
      height: 30px;
      background: #B5D4F4;
      color: #0C447C;
      font-size: .85rem;
    }

    .floor-name {
      font-size: .85rem;
      color: #0C447C;
    }

    .floor-meta {
      font-size: .68rem;
      color: #185FA5;
      letter-spacing: .03em;
    }

    .unit-header-bg {
      background: #dfdfe3;
      border-bottom: 1px solid #a4a4a4;
    }

    .unit-icon-wrap {
      width: 22px;
      height: 22px;
      background: #b1b1b7a8;
      color: #27500A;
      font-size: .68rem;
    }

    .unit-name {
      font-size: .75rem;
      color: #27500A;
    }

    .unit-type-pill {
      background: #b1b1b7a8;
      color: #27500A;
      font-size: .6rem;
    }

    /* Room header */
    .room-header-bg {
      background: #c6dbef;
      border-bottom: 1px solid #eaedf5;
    }

    .room-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #5477a1;
      flex-shrink: 0;
    }

    .room-name {
      font-size: .75rem;
    }

    /* Dimension chips */
    .dim-side-chip {
      background: #f1f3f5;
      border: 0.5px solid #dee2e6;
      font-size: .68rem;
    }

    .dim-side-name {
      font-size: .68rem;
      font-weight: 600;
      color: #212529;
    }

    .dim-side-unit {
      background: #c8cfe4;
      color: #6c757d;
      font-size: .6rem;
      border-radius: 3px;
      padding: 1px 4px;
    }

    .area-chip {
      background: #E6F1FB;
      border: 0.5px solid #85B7EB;
      font-size: .68rem;
    }

    .area-val {
      font-weight: 600;
      color: #0C447C;
    }

    .area-unit {
      font-size: .6rem;
      color: #185FA5;
    }

    .amenity-chip {
      background: #f8f9fc;
      border: 0.5px solid #dee2e6;
      font-size: .65rem;
      color: #6c757d;
    }

    .btn-del-labeled {
      font-size: .68rem;
      padding: 4px 10px;
      border: 0.5px solid #f09595;
      color: #a32d2d;
      border-radius: 6px;
      white-space: nowrap;
    }

    .btn-del-labeled:hover {
      background: #FCEBEB;
      color: #a32d2d;
    }

    .btn-del-icon {
      font-size: .54rem;
      padding: 3px 7px;
      border: 0.5px solid #f09595;
      color: #a32d2d;
      border-radius: 5px;
    }

    .btn-del-icon:hover {
      background: #FCEBEB;
      color: #a32d2d;
    }

    .section-sublabel {
      font-size: .62rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #6c757d;
    }

    .room-grid {
      display: grid;
      grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
      gap: .5rem;
    }

    .chevron {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-right: 1.5px solid currentColor;
      border-bottom: 1.5px solid currentColor;
      transform: rotate(45deg);
      margin-top: -3px;
      transition: transform .2s;
    }

    .chevron.up {
      transform: rotate(-135deg);
      margin-top: 2px;
      color: #02488f;
    }
  </style>
@endpush

@push('scripts')
  <script>
    function cdToggle(btn, target) {
      const body = btn.closest('.card').querySelector(target);
      const icon = btn.querySelector('.chevron');

      body.classList.toggle('collapse');
      icon.classList.toggle('up');
    }

    // Edit Basic modal: category → type + construction status
    const edit_type = document.getElementById('type');
    const edit_cat = document.getElementById('category');

    // populate on category change
    edit_cat.addEventListener('change', () => fillTypes(edit_type, edit_cat.value, ''));

    // populate + restore saved type when modal opens
    document.getElementById('edit_basic_property').addEventListener('show.bs.modal', () =>
      fillTypes(edit_type, edit_cat.value, edit_type.dataset.old)
    );

    let editIsResidential = {{ $property->category === 'residential' ? 'true' : 'false' }};
  </script>
@endpush
