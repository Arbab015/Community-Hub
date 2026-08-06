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
  @vite(['resources/assets/vendor/libs/notyf/notyf.scss','resources/assets/vendor/libs/dropzone/dropzone.scss', 'resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/dropzone/dropzone.js', 'resources/assets/vendor/libs/notyf/notyf.js','resources/assets/vendor/libs/jquery-repeater/jquery-repeater.js','resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/forms-file-upload.js','resources/assets/js/repeater.js', 'resources/assets/js/common_property.js','resources/assets/js/edit_construction.js', 'resources/assets/js/ui-toasts.js','resources/assets/js/forms-selects.js' ])
@endsection


@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      @if(!isset($isOwner))
        <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">
          @if(isset($society) && $user_type)
            Society Management
          @else
            Property Management
          @endif</p>
      @endif
      <h4 class="mb-1">Property Details</h4>
      @php
        $block = $property->block;
      @endphp
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}" class="text-dark opacity-75 text-decoration-none">Home</a>
          </li>
          @if(!isset($isOwner))
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
          @else
            <li class="breadcrumb-item">
              <a href="{{ route('owner.properties.show')  }}"
                 class="text-dark opacity-75 text-decoration-none">Properties</a>
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
    <div class="rounded-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
         style="width:48px; height:48px;">
      <i class="ti tabler-building-estate text-primary fs-4"></i>
    </div>
    <div class="flex-grow-1 min-w-0">
      <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
        <span class="fw-semibold">{{ ucfirst($property->type) }} #{{ $property->property_no }}</span>
        <span class="badge rounded-pill bg-secondary bg-opacity-25 text-secondary border border-secondary small">
          {{ ucfirst($property->category) }}
        </span>
        <span
          class="badge rounded-pill bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 small">
          {{ ucwords(str_replace('_', ' ', $property->construction_status)) }}
        </span>
      </div>
      @if($property->name)
        <h6 class="mb-0 fw-semibold">{{ ucwords($property->name)  }}</h6>
      @else
        <span>{!! $address !!}</span>
      @endif
    </div>
  </div>

  <div class="col-12">
    <ul class="nav nav-pills flex-column flex-sm-row gap-1 border rounded-3 p-1 bg-light">
      <li class="nav-item">
        <button class="nav-link active w-100 text-start" data-bs-toggle="pill" data-bs-target="#info">
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
      <li class="nav-item">
        <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#payments_planning">
          <i class="ti tabler-calendar-dollar me-2 d-none d-sm-inline-block"></i> Payments Planning
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link w-100 text-start" data-bs-toggle="pill" data-bs-target="#payments">
          <i class="ti tabler-credit-card me-2 d-none d-sm-inline-block"></i> Payments
        </button>
      </li>
    </ul>
  </div>

  <div class="tab-content mt-3 p-0 mt-5">

    {{-- TAB 1: PROPERTY DETAILS --}}
    <div class="tab-pane fade show active" id="info" role="tabpanel">
      <div class="row g-4">
        <div class="col-xl-4 col-lg-4 col-md-5">
          <div class="card mb-4 shadow-sm rounded-3">
            <div class="card-body text-center p-4">
              <div class="position-relative d-inline-block mb-3">
                <img id="societyAvatarPreview" class="rounded border img-fluid"
                     src="{{ $property->attachment ? asset('storage/' . $property->attachment->link) : asset('assets/img/my_images/dummy_property_image.png') }}"
                     width="150" height="150" style="object-fit:cover;">
                @if (auth()->user()->can('edit_property'))
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
              <h5 class="mb-0 fw-semibold">{{ ucwords($property->name) }}</h5>
              <span class="badge {{ $property->construction_status == 'active' ? 'bg-primary' : 'bg-secondary' }} mt-2">
                {{ ucfirst($property->owner_id ? "Sold" : 'Un-sold') }}
              </span>


              @can('listing_property')
                <div class="mt-3 pt-3 border-top text-start">
                  <h6 class="fw-bold mb-1 d-flex align-items-center gap-2">Listing Status</h6>
                  <div
                    class="rounded-3 px-3 py-2 mb-3 border {{ $property->listing ? 'border-success border-opacity-50 bg-success bg-opacity-10' : 'border-dashed' }}">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <div class="d-flex align-items-center gap-2">
                      <span
                        class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 listing-status-icon {{ $property->listing ? 'bg-success bg-opacity-25' : '' }}">
                        <i
                          class="ti {{ $property->listing ? 'tabler-tag text-success' : 'tabler-tag-off listing-status-icon-i' }}"></i>
                      </span>
                        <span class="fw-semibold small {{ $property->listing ? 'text-success' : 'text-primary' }}">
                        {{ $property->listing ? 'Listed' : 'Not Listed' }}
                      </span>
                      </div>
                      @if($property->listing && Auth()->user()->can('unlist_property'))
                        <a href="{{ route('listing.destroy', $property->listing->uuid) }}"
                           onclick="confirmDelete(event)"
                           class="d-inline-flex align-items-center gap-1 text-decoration-none badge bg-danger">
                          <i class="ti tabler-tag-off"></i> Unlist
                        </a>
                      @endif
                    </div>

                    @if(auth()->user()->can('see_inquiries') && $property->listing?->inquiries->count())
                      @if(isset($society) && $user_type)
                        <a class="btn btn-xs bg-secondary text-white rounded my-1"
                           href="{{ route('society.property.inquiries.show', [$user_type, $society->uuid, $property->listing->uuid]) }}">
                          See Inquiries
                        </a>
                      @else
                        <a class="btn btn-xs bg-secondary text-white rounded mb-1"
                           href="{{ route('property.inquiries.show', ['property', $property->uuid, $property->listing->uuid]) }}">
                          See Inquiries
                        </a>
                      @endif
                    @endif
                    <p class="mb-0 text-muted listing_description">
                      {{ $property->listing ? 'This property has listed for ' . ucfirst($property->listing->listing_type) : 'This property has no active listing' }}
                    </p>
                  </div>

                  <button class="btn btn-sm w-100 d-flex align-items-center gap-2 fw-semibold sell_rent_btn"
                          data-bs-toggle="modal" data-bs-target="#sell_rent_modal"
                          data-type="property" data-id="{{ $property->id }}"
                          data-listing='@json($property->listing)'>
                    <i class="ti tabler-tag sell_rent_icon"></i>
                    {{ $property->listing ? 'Update Your Listing' : 'List for Sell / Rent' }}
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
                  <h6 class="mb-0">{{ $property->created_at->format('M d, Y') }}</h6>
                </div>
              @endcan

            </div>
          </div>

          {{-- Documents Card --}}
          <div class="card border-0 shadow-sm rounded-3 p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
              <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <span class="d-inline-block bg-primary rounded hr_bar"></span>
                Documents
              </h5>
              @can('edit_property')
                <div class="d-flex align-items-center gap-2">
                  <label class="btn btn-sm btn-outline-info d-flex align-items-center gap-1 mb-0">
                    <i class="fa-solid fa-plus"></i> Add File
                    <input type="file" class="file_trigger" multiple hidden>
                  </label>
                  <form id="add_files_form" method="POST" action="{{ route('property.store') }}"
                        enctype="multipart/form-data" class="d-flex align-items-center gap-2 m-0">
                    @csrf
                    <input type="file" name="documents[]" class="documents_input" multiple hidden>
                    <input type="hidden" name="block_id" value="{{ $property->block->id }}">
                    <input type="hidden" name="section" value="documents">
                    <input type="hidden" name="request_type" value="document">
                    <input type="hidden" name="property_id" value="{{ $property->id ?? '' }}">
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
                <div class="d-flex align-items-center gap-2 @if ($property->attachments->count() == 0) d-none @endif">
                  <div class="form-check d-flex align-items-center m-0">
                    <input class="form-check-input checkbox me-2" type="checkbox" id="select_all">
                    <label class="form-check-label fw-semibold mb-0 cursor-pointer" for="select_all">Select All</label>
                  </div>
                </div>
                <span data-url="{{ route('attachments.bulk_delete') }}" id="bulk_btn"
                      class="btn btn-xs btn-outline-danger waves-effect d-none bulk_delete_btn d-flex align-items-center gap-1 bulk_btn">
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
                          <div class="d-flex flex-column align-items-center gap-1">
                            @php $ext = pathinfo($attachment->link, PATHINFO_EXTENSION); @endphp
                            @if (!in_array($ext, ['xls', 'xlsx', 'csv']))
                              <a href="{{ asset('storage/' . $attachment->link) }}" target="_blank"
                                 class="btn btn-xs btn-icon btn-outline-primary">
                                <i class="fa-regular fa-eye fa-sm"></i>
                              </a>
                            @endif
                            <a href="{{ asset('storage/' . $attachment->link) }}" download title="Download"
                               class="btn btn-xs btn-icon btn-outline-success">
                              <i class="fa-solid fa-download fa-sm"></i>
                            </a>
                            @can('edit_property')
                              <a href="{{ route('attachment.delete', [$attachment->id]) }}" title="Delete"
                                 onclick="confirmDelete(event)" class="btn btn-xs btn-icon btn-outline-danger">
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

          {{-- Basic Information Card --}}
          <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <span class="d-inline-block bg-primary rounded hr_bar"></span> Basic Information
              </h5>
              @can('edit_property')
                <span class="text-primary cursor-pointer" data-bs-toggle="modal" data-bs-target="#edit_basic_property"
                      title="Edit"><i class="fa-solid fa-pen-to-square"></i></span>
              @endcan
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Property no</p>
                  <p class="fw-bold text-dark mb-0">#{{ $property->property_no }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Category</p>
                  <p class="fw-semibold text-dark mb-0">{{ ucfirst($property->category) }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Type</p>
                  <p class="fw-semibold text-dark mb-0">{{ ucfirst($property->type) }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Status</p>
                  <p
                    class="fw-semibold mb-0 text-primary">{{ ucwords(str_replace('_', ' ', $property->construction_status)) }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Block</p>
                  <p class="fw-semibold mb-0 text-primary">{{ $property->block->name }}</p>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="bg-light rounded-3 p-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-1 card_label">Society</p>
                  <p class="fw-semibold mb-0 text-primary">{{ $property->block->society->name }}</p>
                </div>
              </div>
            </div>
            <hr>
            {{-- Add in property_details.blade.php after Basic Information card --}}

            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                  <span class="d-inline-block bg-primary rounded hr_bar"></span> Features
                </h5>
              </div>
              <div class="row g-2">
                @if($property->is_corner)
                  <div class="col-md-4">
                    <div class="d-flex align-items-center gap-2 p-3 bg-light rounded">
                      <i class="ti tabler-corner-down-right-double text-primary fs-5"></i>
                      <div>
                        <small class="text-muted d-block">Corner</small>
                        <span class="fw-semibold">Yes</span>
                      </div>
                    </div>
                  </div>
                @endif
                @if($property->is_main_boulevard_facing)
                  <div class="col-md-4">
                    <div class="d-flex align-items-center gap-2 p-3 bg-light rounded">
                      <i class="ti tabler-road text-success fs-5"></i>
                      <div>
                        <small class="text-muted d-block">Main Boulevard</small>
                        <span class="fw-semibold">Facing</span>
                      </div>
                    </div>
                  </div>
                @endif
                @if($property->is_park_facing)
                  <div class="col-md-4">
                    <div class="d-flex align-items-center gap-2 p-3 bg-light rounded">
                      <i class="ti tabler-trees text-success fs-5"></i>
                      <div>
                        <small class="text-muted d-block">Park</small>
                        <span class="fw-semibold">Facing</span>
                      </div>
                    </div>
                  </div>
                @endif
              </div>
              @if(!$property->is_corner && !$property->is_main_boulevard_facing && !$property->is_park_facing)
                <p class="text-muted small mb-0 fst-italic">No special features selected</p>
              @endif
            </div>

            <hr>
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
              <span class="d-inline-block bg-primary rounded hr_bar"></span> Address
            </h5>
            <p class="text-muted small mb-0 ps-1">
              <i class="ti tabler-map-pin me-1"></i>
              {!! $address !!}
            </p>
          </div>

          {{-- Dimensions Card --}}
          <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <span class="d-inline-block bg-primary rounded hr_bar"></span> Property Dimensions
              </h5>
              <div class="d-flex">
                @can('edit_property')
                  <span class="text-primary cursor-pointer" data-bs-toggle="modal"
                        data-bs-target="#edit_dimensions_modal" title="Edit">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </span>
                @endcan
              </div>
            </div>
            @foreach($property->dimensions as $dimension)
              @php $converted_size = app()->make(\App\Http\Controllers\PropertiesController::class)->convertForUser($dimension->size, $dimension->unit); @endphp
              <div class="border border-secondary rounded mb-2 p-2">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fw-semibold">{{ ucfirst($dimension->name) }}</span>
                  <div class="d-flex align-items-center gap-1">
                    <span class="fw-semibold">{{ round($converted_size, 2) }}</span>
                    <span class="badge bg-label-secondary">{{ ucfirst($dimension->unit) }}</span>
                  </div>
                </div>
              </div>
            @endforeach
            @php
              $area_sqft = GetArea::calculate($property->dimensions);
              $total_area = "";
              $area_unit = "";
              if ($area_sqft >= $default_marla_size) {
                $in_marla = $area_sqft / $default_marla_size;
                if ($in_marla >= 20) {
                  $total_area = $in_marla / 20;
                  $area_unit = $total_area > 1 ? "Kanals" : "Kanal";
                } else {
                  $total_area = $in_marla;
                  $area_unit = $in_marla > 1 ? "Marlas" : "Marla";
                }
              } else {
                $total_area = $area_sqft;
                $area_unit = "Square Feet";
              }
            @endphp
            <div class="border border-secondary rounded mb-2 p-2">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Total Area</span>
                <div class="d-flex align-items-center gap-1">
                  <span class="fw-semibold">{{ round($total_area, 2) }}</span>
                  <span class="badge bg-label-secondary">{{ $area_unit }}</span>
                </div>
              </div>
            </div>
          </div>


          @can('delete_property')
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
              <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <span class="d-inline-block bg-primary rounded hr_bar"></span> Delete Property
              </h5>

              <p class="mb-4">
                Deleting this property will
                <strong class="text-danger fst-italic">permanently</strong>
                remove the society and all associated data.
                This action cannot be undone.
              </p>
              @php
                $property_destroy_url = route('property.delete', isset($user_type) ? [$property->uuid, $user_type]: [$property->uuid]);
              @endphp

              <a href="{{ $property_destroy_url }}" class="btn btn-danger"
                 onclick="confirmDelete(event, null)">
                <i class="fa-solid fa-trash me-3"></i>
                Delete Property
              </a>
            </div>

          @endcan


        </div>
      </div>
    </div>

    {{-- TAB 2: GALLERY --}}
    <div class="tab-pane fade" id="gallery" role="tabpanel">
      @include('components.gallery')
    </div>

    {{-- TAB 3: CONSTRUCTION DETAILS --}}
    <div class="tab-pane fade" id="construction_details" role="tabpanel">

      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
          <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
            <span class="d-inline-block bg-primary rounded hr_bar"></span>
            Construction Details
          </h5>
          <span
            class="badge bg-primary bg-opacity-10 text-primary fw-normal d-none d-sm-inline-flex">{{ $property?->property_no }}</span>
        </div>
        @php
          $search_content = request('search_content');
          $selected_unit_type = request('unit_type');
        @endphp
        <div class="d-flex align-items-center gap-2">
          @if($property->floors->count() > 1)
            <div class="btn-group">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="selectedFloor">
                All Floors
              </button>
              <button type="button"
                      class="btn btn-outline-secondary btn-sm dropdown-toggle dropdown-toggle-split"
                      data-bs-toggle="dropdown" aria-expanded="false">
                <span class="visually-hidden">Toggle Dropdown</span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end small">
                <li>
                  <a class="dropdown-item floor-option" href="javascript:void(0);" data-floor="all">All Floors</a>
                </li>
                @foreach($property->floors as $floor)
                  <li>
                    <a class="dropdown-item floor-option" href="javascript:void(0);" data-floor="{{ $floor->id }}">
                      {{ ucfirst($floor_types->firstWhere('id', $floor->floor_type)?->title) }}
                    </a>
                  </li>
                @endforeach
              </ul>
            </div>
          @endif

          @can('edit_property')
            <span class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 cursor-pointer"
                  data-bs-toggle="modal" data-bs-target="#add_floor_modal">
              <i class="ti tabler-plus fa-xs"></i> Add Floor
            </span>
          @endcan
        </div>
      </div>

      <div class="d-sm-none mb-4 mt-n3">
        <span class="badge bg-primary bg-opacity-10 text-primary fw-normal">{{ $property?->property_no }}</span>
      </div>

      @if($property->construction_status !== "pending" && $property->floors->count())

        @foreach($property->floors as $floor)
          <div class="card border-0 shadow-sm rounded-3  mb-3" data-floor-id="{{ $floor->id }}">

            {{-- Floor header --}}
            <div class="floor-header-border bg-label-primary d-flex justify-content-between flex-wrap gap-2 px-3 py-2">
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
                @can('edit_property')
                  @if($floor->units->count())
                    <button
                      class="floor_action_btn btn btn-sm btn-danger d-inline-flex align-items-center gap-1 floor_bulk_delete_btn d-none"

                      data-floor-id="{{ $floor->id }}"><i class="ti tabler-trash "></i> Bulk Delete (<span
                        class="sel_count">0</span>)
                    </button>
                    <button
                      class="floor_action_btn btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                      data-bs-toggle="modal" data-bs-target="#unit_modal"
                      data-floor-id="{{ $floor->id }}" data-floor-prefix="floors[0]">
                      <i class="ti tabler-plus fa-xs"></i> Add Unit
                    </button>
                  @elseif($floor->rooms->count())
                    <button
                      class="floor_action_btn btn btn-sm btn-danger d-inline-flex align-items-center gap-1 floor_bulk_delete_btn d-none"
                      data-floor-id="{{ $floor->id }}">
                      <i class="ti tabler-trash "></i>Bulk Delete (<span class="sel_count">0</span>)
                    </button>
                    <button
                      class="floor_action_btn btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                      data-bs-toggle="modal" data-bs-target="#room_modal"
                      data-floor-id="{{ $floor->id }}">
                      <i class="ti tabler-plus fa-xs"></i> Add Room
                    </button>
                  @else
                    <button
                      class="floor_action_btn btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                      data-bs-toggle="modal" data-bs-target="#unit_modal"
                      data-floor-id="{{ $floor->id }}">
                      <i class="ti tabler-plus fa-xs"></i> Add Unit
                    </button>
                    <button
                      class="floor_action_btn btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                      data-bs-toggle="modal" data-bs-target="#room_modal"
                      data-floor-id="{{ $floor->id }}">
                      <i class="ti tabler-plus fa-xs"></i> Add Room
                    </button>
                  @endif

                  <a href="{{ route('property.destroy', ['section'=>'floor', $floor->uuid]) }}"
                     onclick="confirmDelete(event, null)"
                     class="floor_action_btn text-decoration-none d-inline-flex align-items-center gap-1 btn btn-outline-danger text-danger">
                    <i class="ti tabler-trash"></i> Delete
                  </a>
                @endcan
                <button class="btn btn-sm btn-link text-primary p-0" onclick="cdToggle(this, '.card-body')">
                  <span class="chevron"></span>
                </button>
              </div>
            </div>

            <div class="card-body px-3 py-3 overflow-visible">

              {{-- filter --}}
              @if($floor->units->count() || $floor->rooms->count())
                <div class="row g-4 my-3">
                  @if($floor->units->count())
                    <div class="col-12 col-md-4 mt-0">
                      <label class="form-label text-uppercase fw-semibold">Search</label>
                      <div class="input-group">
                        <input type="text"
                               class="form-control unit-search-input"
                               data-floor="{{ $floor->id }}"
                               placeholder="Search by unit name...">
                        <span class="input-group-text cursor-pointer unit-search-btn" data-floor="{{ $floor->id }}"
                              title="Search">
                          <i class="ti tabler-search"></i>
                        </span>
                      </div>
                    </div>
                    <div class="col-12 col-md-4 mt-0">
                      <label class="form-label text-uppercase fw-semibold">Unit Type</label>
                      <select class="form-select unit-type-filter" data-floor="{{ $floor->id }}">
                        <option value="">All</option>
                        @foreach($unit_types as $unit_type)
                          <option value="{{ $unit_type->id }}">{{ ucwords($unit_type->title) }}</option>
                        @endforeach
                      </select>
                    </div>
                  @elseif($floor->rooms->count())
                    <div class="col-12 col-md-4 mt-0">
                      <label class="form-label text-uppercase fw-semibold">Room Type</label>
                      <select class="form-select room-type-filter" data-floor="{{ $floor->id }}">
                        <option value="">All</option>
                        @foreach($room_types as $room_type)
                          <option value="{{ $room_type->id }}">{{ ucwords($room_type->title) }}</option>
                        @endforeach
                      </select>
                    </div>
                  @endif
                </div>
              @endif

              {{-- Units --}}
              @if($floor->units->count())
                <div class=" mb-3">
                  <p class="section-sublabel mb-0">Units</p>
                </div>

                <div class="row g-3 unit-list" data-floor-units="{{ $floor->id }}">
                  @php
                    $units = $floor->units()->with(['rooms.dimensions','dimensions','listing','owner'])->when($search_content, function ($q) use ($search_content) {$q->where('unit_name', 'LIKE', "%{$search_content}%");})
                     ->when($selected_unit_type, function ($q) use ($selected_unit_type) {
                      $q->where('unit_type', $selected_unit_type);
                     })
                    ->latest()->take(4)->get();
                  @endphp

                  @foreach($units as $unit)
                    <div class="col-sm-6 col-lg-4 col-xl-3 unit-col">
                      <div class="card h-100 border-0 shadow-sm unit-card" data-id="{{ $unit->id }}">
                        <div class="select-overlay"></div>

                        <div class="card-accent-wrap">
                          <div class="card-accent"></div>
                        </div>
                        <div class="card-body p-3 ">

                          <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                              <span
                                class="unit-icon-wrap d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                                <i class="ti tabler-home"></i>
                              </span>
                              <span
                                class="unit-name fw-semibold text-truncate">{{ $unit->unit_name ? ucfirst($unit->unit_name) : 'Unit' }}</span>
                            </div>

                            <div>
                              @can('edit_property')
                                <button class="clone btn btn-xs px-1 rounded-pill bg-label-primary">
                                  <i class="ti tabler-copy"></i>
                                </button>
                              @endcan
                              @if($unit->unit_type)
                                <span class="badge rounded-pill unit-type-pill flex-shrink-0">
                             {{ ucfirst($unit_types->first(fn($t) => $t->id == $unit->unit_type)?->title ?? '') }}
                             </span>
                              @endif
                            </div>

                          </div>

                          <div class="d-flex align-items-center gap-3 text-muted small mb-2">
                            @if($unit->rooms->count())
                              <span class="d-flex align-items-center gap-1">
              <i
                class="ti tabler-door"></i> {{ $unit->rooms->count() }} {{ Str::plural('room', $unit->rooms->count()) }}
            </span>
                            @else
                              <span class="d-flex align-items-center gap-1">
              <i class="ti tabler-ruler-2"></i> {{ round(GetArea::calculate($unit->dimensions)) }} sq ft
            </span>
                              @php
                                $unit_amenities_count = count(is_array($unit->amenities) ? $unit->amenities : json_decode($unit->amenities ?? '[]', true));
                              @endphp
                              @if($unit_amenities_count)
                                <span class="d-flex align-items-center gap-1">
                <i
                  class="ti tabler-check"></i> {{ $unit_amenities_count }} {{ Str::plural('amenity', $unit_amenities_count) }}
              </span>
                              @endif
                            @endif
                          </div>

                          <div class="mb-3">
                       <span
                         class="badge rounded-pill {{ $unit->listing ? 'bg-success bg-opacity-10 text-success' : 'bg-label-secondary' }} d-inline-flex align-items-center gap-1">
            <i class="ti {{ $unit->listing ? 'tabler-tag' : 'tabler-tag-off' }}"></i>
            {{ $unit->listing ? 'Listed for '.ucfirst($unit->listing->listing_type) : 'Not listed' }}
                       </span>
                          </div>

                          <div class="d-flex align-items-center gap-2">
                            @if(isset($society) && $user_type)
                              <a href="{{ route('society.unit.details', [$user_type, $society->uuid, $unit->uuid]) }}"
                                 class="btn btn-sm btn-outline-primary flex-grow-1 d-flex align-items-center justify-content-center gap-1">
                                <i class="ti tabler-eye"></i> View
                              </a>
                            @else
                              <a href="{{ route('unit.details', $unit->uuid) }}"
                                 class="btn btn-sm btn-outline-primary flex-grow-1 d-flex align-items-center justify-content-center gap-1">
                                <i class="ti tabler-eye"></i> View
                              </a>
                            @endif
                            @can('edit_property')
                              <div class="dropdown unit-actions-dropdown">
                                <button type="button" class="btn btn-sm btn-icon unit-kebab-btn"
                                        data-bs-toggle="dropdown" data-bs-boundary="viewport"
                                        aria-expanded="false" title="More actions">
                                  <i class="ti tabler-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                  @can('edit_property')
                                    <li>
                                      <button type="button"
                                              class="dropdown-item d-flex align-items-center gap-2 edit-unit-btn"
                                              data-bs-toggle="modal" data-bs-target="#unit_modal"
                                              data-unit-id="{{ $unit->id }}" data-floor-id="{{ $floor->id }}">
                                        <i class="ti tabler-pencil"></i> Edit Unit
                                      </button>
                                    </li>
                                  @endcan

                                  @can('listing_property')
                                    <li>
                                      <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                                              data-bs-toggle="modal" data-bs-target="#sell_rent_modal"
                                              data-type="unit" data-id="{{ $unit->id }}"
                                              data-listing='@json($unit->listing)'>
                                        <i class="ti {{ $unit->listing ? 'tabler-tag' : 'tabler-tag-off' }}"></i>
                                        {{ $unit->listing ? 'Update Listing' : 'List Unit' }}
                                      </button>
                                    </li>
                                  @endcan
                                  @if(auth()->user()->can('property_inquiries') && $unit->listing?->inquiries?->count())
                                    @if(isset($society) && $user_type)
                                      <li>
                                        <a type="button" class="dropdown-item d-flex align-items-center gap-2"
                                           href="{{ route('society.property.inquiries.show', [$user_type, $society->uuid, $unit->listing->uuid]) }}">
                                          <i class="ti {{'tabler-tag' }}"></i> See Inquiries
                                        </a>
                                      </li>
                                    @else
                                      <li>
                                        <a type="button" class="dropdown-item d-flex align-items-center gap-2"
                                           href="{{ route('property.inquiries.show', ['unit', $unit->uuid, $unit->listing->uuid]) }}">
                                          <i class="ti {{'tabler-tag' }}"></i> See Inquiries
                                        </a>
                                      </li>
                                    @endif
                                  @endif

                                  @can('delete_property')
                                    <li>
                                      <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                      <a href="{{ route('property.destroy', ['section'=>'unit', $unit->uuid]) }}"
                                         onclick="confirmDelete(event, null)"
                                         class="dropdown-item d-flex align-items-center gap-2 text-danger">
                                        <i class="ti tabler-trash"></i> Delete Unit
                                      </a>
                                    </li>

                                  @endcan
                                </ul>
                              </div>
                            @endcan
                          </div>

                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>

                {{-- Per-floor Load More button --}}
                @if($floor->units->count() > 4)
                  <div class="text-center mt-4 floor-load-more-wrap " data-floor-id="{{ $floor->id }}">
                    <button class="btn btn-xs btn-outline-primary floor-load-more-btn"
                            type="button"
                            data-floor-id="{{ $floor->id }}"
                            data-skip="4"
                            data-total="{{ $floor->units->count() }}">
                      <span class="spinner-border spinner-border-sm me-1 d-none floor-spinner"></span>
                      <span class="floor-load-more-text">Load More</span>
                    </button>
                  </div>
                @endif
              @endif

              {{-- Direct floor rooms --}}
              @if($floor->rooms->count())
                <div class="mb-3">
                  <p class="section-sublabel mb-0">Rooms</p>
                </div>
                <div class="row g-3 room-list" data-floor-rooms="{{ $floor->id }}">
                  @foreach($floor->rooms->take(4) as $room)
                    <div class="col-sm-6 col-lg-4 col-xl-3 room-col">
                      <div class="card h-100 border-0 shadow-sm room-card" data-id="{{ $room->id }}">
                        <div class="select-overlay"></div>
                        {{--                        <div class="select-check  bg-label-primary"><i class="ti tabler-check"></i></div>--}}
                        <div class="card-accent-wrap">
                          <div class="card-accent"></div>
                        </div>
                        <div class="card-body p-3">
                          <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                        <span
                          class="unit-icon-wrap d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                            <i class="ti tabler-door"></i>
                        </span>
                              <span
                                class="unit-name fw-semibold text-truncate">{{ ucfirst($room_types->first(fn($t) => $t->id == $room->room_type)?->title ?? '') }}</span>
                            </div>
                            <div class="d-flex align-items-center flex-shrink-0 room-card-actions">
                              @can('edit_property')
                                <button type="button" class="btn btn-icon room-icon-btn edit-room-btn"
                                        data-bs-toggle="modal" data-bs-target="#room_modal"
                                        data-room-id="{{ $room->id }}" data-floor-id="{{ $floor->id }}"
                                        title="Edit Room">
                                  <i class="ti tabler-pencil"></i>
                                </button>
                              @endcan
                              @can('delete_property')
                                <a href="{{ route('property.destroy', ['section'=>'room', $room->uuid]) }}"
                                   onclick="confirmDelete(event, null)"
                                   class="btn btn-icon room-icon-btn room-icon-btn-danger text-danger"
                                   title="Delete Room">
                                  <i class="ti tabler-trash"></i>
                                </a>
                              @endcan
                            </div>
                          </div>

                          @php
                            $roomArea = \App\Helpers\GetArea::calculate($room->dimensions);
                            $roomAmenities = is_array($room->amenities) ? $room->amenities : json_decode($room->amenities ?? '[]', true);
                          @endphp

                          <div class="d-flex align-items-center gap-3 text-muted small mb-2">
                           <span class="d-flex align-items-center gap-1">
                             <i class="ti tabler-ruler-2"></i> {{ round($roomArea) }} sq ft
                           </span>
                          </div>

                          @if($room->dimensions->count())
                            <div class="d-flex flex-wrap gap-1 mb-2">
                              @foreach($room->dimensions as $rd)
                                <span class="dim-side-chip rounded px-2 py-1 d-inline-flex align-items-center gap-1">
                                <span class="dim-side-name">{{ ucfirst($rd->name) }}</span>
                                <span class="dim-side-unit">{{ $rd->size }} {{ $rd->unit }}</span>
                                </span>
                              @endforeach
                            </div>
                          @endif

                          @if(count($roomAmenities))
                            <div class="d-flex flex-wrap gap-1">
                              @foreach($roomAmenities as $amenityId)
                                @php $amenityTitle = $amenities->firstWhere('id', $amenityId)?->title ?? null; @endphp
                                @if($amenityTitle)
                                  <span
                                    class="amenity-chip rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1">
                                   <i class="ti tabler-check fa-xs"></i> {{ ucwords($amenityTitle) }}
                                  </span>
                                @endif
                              @endforeach
                            </div>
                          @endif
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
                @if($floor->rooms->count() > 4)
                  <div class="text-center mt-4 floor-load-more-wrap" data-floor-id="{{ $floor->id }}" data-mode="rooms">
                    <button class="btn btn-xs btn-outline-primary floor-load-more-btn"
                            type="button"
                            data-floor-id="{{ $floor->id }}"
                            data-mode="rooms"
                            data-skip="4"
                            data-total="{{ $floor->rooms->count() }}">
                      <span class="spinner-border spinner-border-sm me-1 d-none floor-spinner"></span>
                      <span class="floor-load-more-text">Load More</span>
                    </button>
                  </div>
                @endif
              @elseif(!$floor->units->count())
                <p class="text-muted small fst-italic mb-0">No rooms or units added to this floor.</p>
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


    {{-- TAB 3: Payments planning DETAILS --}}
    <div class="tab-pane fade" id="payments_planning" role="tabpanel">
      @include('components.property.payments-planning' , ['property' => $property])
    </div>
    {{-- TAB 3: Payments --}}
    <div class="tab-pane fade" id="payments" role="tabpanel">
      @include('components.property.payments')
    </div>
  </div>
  @include('_partials._modals.edit_property')
  @include('_partials._modals.listing_property')

@endsection

@push('styles')
  <style>
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

    .floor_action_btn {
      font-size: .68rem;
      padding: 4px 10px;
      border-radius: 6px;
      white-space: nowrap;
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
      font-size: .72rem;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: .06em;
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

    .sell_rent_btn {
      background: linear-gradient(135deg, #6c63ff, #534ab7);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 10px;
      font-size: .82rem;
      letter-spacing: .02em;
    }

    .sell_rent_btn:hover {
      background: linear-gradient(135deg, #534ab7, #3c3489);
      color: #fff;
    }

    .sell_rent_icon {
      font-size: 1rem;
    }

    .listing-status-card {
      background: #f8f7ff;
      border: 1px dashed #b5aeff;
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

    .listing_status {
      font-size: .78rem;
      color: #534ab7;
    }

    .listing_description {
      font-size: .65rem;
      color: #6c757d;
    }

    .border-dashed {
      border-style: dashed !important;
    }

    @media (max-width: 450px) {
      .const_labels {
        padding: 0.25rem 0.5rem !important;
      }

      .const-inner {
        flex-direction: column;
      }

      .const-inner .const-icon {
        font-size: 1rem !important;
      }

      .const-inner span {
        font-size: 0.72rem !important;
        text-align: center;
      }
    }

    .room-card,
    .unit-card {
      position: relative;
      cursor: pointer;
      border-radius: 10px !important;
      overflow: hidden;
      transition: box-shadow .2s ease;
    }

    .room-card:hover,
    .unit-card:hover {
      box-shadow: 0 .5rem 1.25rem rgba(0, 0, 0, .08) !important;
    }

    .card-accent-wrap {
      overflow: hidden;
      border-radius: 10px 10px 0 0;
    }

    .card-accent {
      height: 3px;
      background: #7367f0;
    }

    .unit-name {
      font-size: .8rem;
    }

    .room-icon-btn {
      width: 17px;
      height: 26px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: none;
      background: transparent;
      color: #e3a71bd4;
      border-radius: 6px;
      font-size: .8rem;
      transition: background .15s ease, color .15s ease;
    }

    .room-icon-btn:hover {
      background: #f1f3f5;
      color: #343a40;
    }

    .room-icon-btn-danger:hover {
      background: #FCEBEB;
      color: #d33232;
    }

    /* Unit kebab dropdown */
    .unit-kebab-btn {
      width: 32px;
      height: 32px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #e2e2e7;
      background: #fff;
      color: #6c757d;
      border-radius: 8px;
      flex-shrink: 0;
    }

    .unit-kebab-btn:hover,
    .unit-kebab-btn[aria-expanded="true"] {
      background: #f1f3f5;
      color: #343a40;
      border-color: #d3d3d9;
    }

    .unit-actions-dropdown .dropdown-menu {
      min-width: 170px;
      padding: .4rem;
      border-radius: 10px;
      border: 1px solid #eee;
    }

    .unit-actions-dropdown .dropdown-item {
      font-size: .8rem;
      border-radius: 6px;
      padding: .45rem .6rem;
    }

    .unit-actions-dropdown .dropdown-item:hover {
      background: #f1f3f5;
    }

    .unit-actions-dropdown .dropdown-item.text-danger:hover {
      background: #FCEBEB;
    }

    @media (max-width: 350px) {
      .const-inner {
        font-size: 0.7rem !important;
      }
    }

    #dimension_modal_body > .card-body {
      padding: 0 !important;
    }

    @media (max-width: 575.98px) {
      #edit_construction_modal .modal-title {
        font-size: 0.95rem;
      }

      #edit_construction_modal .modal-header {
        padding: 0.75rem 1rem;
      }
    }


    /* Soft overlay with Bootstrap secondary tone */
    .select-overlay {
      position: absolute;
      inset: 0;
      background: rgba(108, 117, 125, 0.12);
      border: 3px solid #7367f0;
      border-radius: 10px;
      opacity: 0;
      transition: all 0.2s ease;
      pointer-events: none;
      z-index: 2;
    }

    /* show overlay only in select mode */
    .floor-select-mode .select-overlay {
      opacity: 1;
    }

    /* hover effect */
    .floor-select-mode .unit-card:hover .select-overlay,
    .floor-select-mode .room-card:hover .select-overlay {
      background: rgba(108, 117, 125, 0.18);
    }

    /* selected card */
    .card-selected {
      transform: scale(1.01);
    }

    /* border highlight */
    .card-selected::after {
      content: "";
      position: absolute;
      inset: 0;
      border: 3px solid #7367f0;
      border-radius: 10px;
      z-index: 3;
    }

    .select-check {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0.7);
      width: 42px;
      background: #7367f0;
      height: 42px;
      color: #fff;
      font-size: 18px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      z-index: 4;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      transition: all 0.2s ease;
      pointer-events: none;
    }

    .card-selected .select-check {
      opacity: 1;
      transform: translate(-50%, -50%) scale(1);
    }

    .card-selected .select-check {
      animation: popTick 0.18s ease-in-out;
    }

    @keyframes popTick {
      0% {
        transform: translate(-50%, -50%) scale(0.5);
      }
      100% {
        transform: translate(-50%, -50%) scale(1);
      }
    }

    /* ===== Bulk action bar ===== */
    #bulk_action_bar {
      bottom: 1.5rem;
    }

    #bulk_action_bar .card {
      background: #fff;
      border: 1px solid #e2e2e7;
    }
  </style>
@endpush

@push('scripts')
  <script>

    // Floor filter dropdown
    document.querySelectorAll('.floor-option').forEach(item => {
      item.addEventListener('click', function() {
        const val = this.dataset.floor;
        document.querySelectorAll('#construction_details .card[data-floor-id]').forEach(card => {
          card.style.display = (val === 'all' || card.dataset.floorId === val) ? '' : 'none';
        });
        document.getElementById('selectedFloor').innerText = this.innerText;
      });
    });

    function cdToggle(btn, target) {
      const body = btn.closest('.card').querySelector(target);
      const icon = btn.querySelector('.chevron');
      body.classList.toggle('collapse');
      icon.classList.toggle('up');
    }

    const edit_type = document.getElementById('type');
    const edit_cat = document.getElementById('category');
    edit_cat.addEventListener('change', () => fillTypes(edit_type, edit_cat.value, ''));
    document.getElementById('edit_basic_property').addEventListener('show.bs.modal', () =>
      fillTypes(edit_type, edit_cat.value, edit_type.dataset.old)
    );

    // listing

    const sellRentModal = document.getElementById('sell_rent_modal');

    sellRentModal.addEventListener('show.bs.modal', function(e) {
      const btn = e.relatedTarget;
      if (!btn) return;

      const itemType = btn.dataset.type || 'property';
      const itemId = btn.dataset.id || '';
      const listing = btn.dataset.listing ? JSON.parse(btn.dataset.listing) : null;
      const form = document.getElementById('listing_form');

      // Set form for SINGLE mode
      form.querySelector('[name="type"]').value = itemType;
      form.querySelector('[name="id"]').value = itemId;
      form.querySelector('[name="ids"]').value = '';
      form.action = "{{ route('listing.store') }}";

      // Show single UI
      document.getElementById('modal_title').textContent = listing ? 'Update Listing' : 'List Property';
      document.getElementById('bulk_notice').classList.add('d-none');
      document.getElementById('single_price').classList.remove('d-none');
      document.getElementById('bulk_rate').classList.add('d-none');
      document.getElementById('btn_text').textContent = 'Submit Listing';

      // Prefill if listing exists
      if (listing) {
        document.getElementById('type_' + listing.listing_type).checked = true;
        document.getElementById('price').value = listing.price || '';
        document.getElementById('unit').value = listing.unit || 'PKR';
        document.getElementById('show_price').checked = listing.show_price ? true : false;
        document.getElementById('contact').value = listing.contact_no || '';
        document.getElementById('show_contact').checked = listing.show_contact_no ? true : false;
        document.getElementById('email').value = listing.email || '';
        document.getElementById('description').value = listing.description || '';
      } else {
        // Reset to defaults
        document.getElementById('type_sell').checked = true;
        document.getElementById('price').value = '';
        document.getElementById('unit').value = 'PKR';
        document.getElementById('show_price').checked = true;
        document.getElementById('contact').value = '';
        document.getElementById('show_contact').checked = true;
        document.getElementById('email').value = '';
        document.getElementById('description').value = '';
      }
    });


    function initKebabDropdowns(container = document) {
      container.querySelectorAll('.unit-kebab-btn').forEach(btn => {
        bootstrap.Dropdown.getOrCreateInstance(btn, {
          popperConfig: { strategy: 'fixed' }
        });
      });
    }

    document.addEventListener('DOMContentLoaded', () => initKebabDropdowns());

    // ── Bulk selection state (shared across fetch and click handlers)
    const floorSelections = new Map();

    function syncBtn(floorId) {
      const btn = document.querySelector(`.floor_bulk_delete_btn[data-floor-id="${floorId}"]`);
      if (!btn) return;
      const count = floorSelections.get(floorId)?.ids.size ?? 0;
      btn.classList.toggle('d-none', count === 0);
      btn.querySelector('.sel_count').textContent = count;
    }

    // ── Unit AJAX
    function fetchUnits(floorId, skip, append) {
      const grid = document.querySelector(`.unit-list[data-floor-units="${floorId}"]`);
      const search = document.querySelector(`.unit-search-input[data-floor="${floorId}"]`)?.value.trim() ?? '';
      const utype = document.querySelector(`.unit-type-filter[data-floor="${floorId}"]`)?.value ?? '';
      const wrap = document.querySelector(`.floor-load-more-wrap[data-floor-id="${floorId}"]:not([data-mode])`);

      const params = new URLSearchParams({ skip, take: 4 });
      if (search) params.set('search', search);
      if (utype) params.set('unit_type', utype);

      return fetch(`/floor/${floorId}/units?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
          if (!append) {
            grid.innerHTML = '';
            if (floorSelections.has(floorId)) {
              floorSelections.delete(floorId);
              syncBtn(floorId);
            }
          }

          if (!data.units.length && !append) {
            grid.innerHTML = `
                        <div class="col-12 d-flex flex-column align-items-center justify-content-center py-4 text-muted">
                            <i class="ti tabler-alert-triangle-filled text-warning fs-2 mb-1"></i>
                            <small class="fst-italic">No units found.</small>
                        </div>`;
            if (wrap) wrap.remove();
            return;
          }

          const societyUuid = @json($society->uuid ?? null);
          const userType = @json($user_type ?? null);
          const canEdit = @json(auth()->user()->can('edit_property'));
          const canListing = @json(auth()->user()->can('listing_property'));
          const canDelete = @json(auth()->user()->can('delete_property'));
          const unitTypes = @json($unit_types->values());

          data.units.forEach(unit => {
            const listed = unit.listing;
            const viewUrl = societyUuid && userType
              ? `/societies/${userType}/society/${societyUuid}/unit/${unit.uuid}`
              : `/unit/details/${unit.uuid}`;

            let metaHtml = '';
            if (unit.rooms && unit.rooms.length) {
              const r = unit.rooms.length;
              metaHtml = `<i class="ti tabler-door"></i> ${r} ${r === 1 ? 'room' : 'rooms'}`;
            } else {
              let sqft = 0;
              if (unit.dimensions && unit.dimensions.length >= 2)
                sqft = unit.dimensions.reduce((acc, d) => acc * parseFloat(d.size), 1);
              metaHtml = `<i class="ti tabler-ruler-2"></i> ${Math.round(sqft)} sq ft`;
            }

            const listedBadge = listed
              ? `<span class="badge rounded-pill bg-success bg-opacity-10 text-success d-inline-flex align-items-center gap-1"><i class="ti tabler-tag"></i> Listed for ${listed.listing_type}</span>`
              : `<span class="badge rounded-pill bg-label-secondary d-inline-flex align-items-center gap-1"><i class="ti tabler-tag-off"></i> Not listed</span>`;

            const listingData = listed ? JSON.stringify(listed).replace(/'/g, '&#39;') : 'null';
            const typeTitle = unit.unit_type ? unitTypes.find(t => t.id == unit.unit_type)?.title ?? null : null;
            const typePill = typeTitle ? `<span class="badge rounded-pill unit-type-pill flex-shrink-0">${typeTitle.charAt(0).toUpperCase() + typeTitle.slice(1)}</span>` : '';
            const unitLabel = unit.unit_name ? unit.unit_name.charAt(0).toUpperCase() + unit.unit_name.slice(1) : 'Unit';
            const viewBtn = `<a href="${viewUrl}" class="btn btn-sm btn-outline-primary flex-grow-1 d-flex align-items-center justify-content-center gap-1"><i class="ti tabler-eye"></i> View</a>`;
            const listingLabel = listed ? 'Update Listing' : 'List Unit';
            const listingIcon = listed ? 'tabler-tag' : 'tabler-tag-off';
            const cloneBtn = canEdit ? `<button class="clone btn btn-xs px-1 rounded-pill bg-label-primary">
                            <i class="ti tabler-copy"></i>
                        </button>` : '';

            const editUnitItem = canEdit ? `
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 edit-unit-btn"
                                            data-bs-toggle="modal" data-bs-target="#unit_modal"
                                            data-unit-id="${unit.id}" data-floor-id="${floorId}">
                                        <i class="ti tabler-pencil"></i> Edit Unit
                                    </button>
                                </li>` : '';

            const listingItem = canListing ? `
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                                            data-bs-toggle="modal" data-bs-target="#sell_rent_modal"
                                            data-type="unit" data-id="${unit.id}" data-listing='${listingData}'>
                                        <i class="ti ${listingIcon}"></i> ${listingLabel}
                                    </button>
                                </li>` : '';

            const deleteUnitItem = canDelete ? `
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a href="/property/destroy/unit/${unit.uuid}" onclick="confirmDelete(event,null)"
                                       class="dropdown-item d-flex align-items-center gap-2 text-danger">
                                        <i class="ti tabler-trash"></i> Delete Unit
                                    </a>
                                </li>` : '';

            const actionsHtml = canEdit ? `
                        <div class="dropdown unit-actions-dropdown">
                            <button type="button" class="btn btn-sm btn-icon unit-kebab-btn"
                                    data-bs-toggle="dropdown" data-bs-boundary="viewport"
                                    aria-expanded="false" title="More actions">
                                <i class="ti tabler-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                ${editUnitItem}
                                ${listingItem}
                                ${deleteUnitItem}
                            </ul>
                        </div>` : `
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#sell_rent_modal"
                            data-type="unit" data-id="${unit.id}" data-listing='${listingData}'
                            title="${listed ? 'Update listing' : 'List unit'}">
                            <i class="ti tabler-tag"></i>
                        </button>`;

            const col = document.createElement('div');
            col.className = 'col-sm-6 col-lg-4 col-xl-3 unit-col';
            col.innerHTML = `
                        <div class="card h-100 border-0 shadow-sm unit-card" data-id="${unit.id}">
                            <div class="select-overlay"></div>
                            <div class="card-accent-wrap"><div class="card-accent"></div></div>
                            <div class="card-body p-3">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <span class="unit-icon-wrap d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                                            <i class="ti tabler-home"></i>
                                        </span>
                                        <span class="unit-name fw-semibold text-truncate">${unitLabel}</span>
                                    </div>
                                    <div>
                                        ${cloneBtn}
                                        ${typePill}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3 text-muted small mb-2">
                                    <span class="d-flex align-items-center gap-1">${metaHtml}</span>
                                </div>
                                <div class="mb-3">${listedBadge}</div>
                                <div class="d-flex align-items-center gap-2">
                                    ${viewBtn}
                                    ${actionsHtml}
                                </div>
                            </div>
                        </div>`;
            grid.appendChild(col);
          });

          initKebabDropdowns(grid);

          const btn = document.querySelector(`.floor-load-more-btn[data-floor-id="${floorId}"]:not([data-mode])`);
          if (data.has_more) {
            if (!wrap) {
              const newWrap = document.createElement('div');
              newWrap.className = 'text-center mt-4 floor-load-more-wrap';
              newWrap.dataset.floorId = floorId;
              newWrap.innerHTML = `<button class="btn btn-sm btn-outline-primary floor-load-more-btn" type="button"
                            data-floor-id="${floorId}" data-skip="${data.next_skip}" data-total="${data.total}">
                            <span class="spinner-border spinner-border-sm me-1 d-none floor-spinner"></span>
                            <span class="floor-load-more-text">Load More</span></button>`;
              grid.closest('.card-body').appendChild(newWrap);
            } else if (btn) {
              btn.dataset.skip = data.next_skip;
            }
          } else {
            if (wrap) wrap.remove();
          }
        });
    }

    // Search button click
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.unit-search-btn');
      if (!btn) return;
      fetchUnits(btn.dataset.floor, 0, false);
    });

    // Search on Enter key
    document.addEventListener('keydown', function(e) {
      if (e.key !== 'Enter') return;
      const input = e.target.closest('.unit-search-input');
      if (!input) return;
      fetchUnits(input.dataset.floor, 0, false);
    });

    // Unit type filter change
    document.addEventListener('change', function(e) {
      const sel = e.target.closest('.unit-type-filter');
      if (!sel) return;
      fetchUnits(sel.dataset.floor, 0, false);
    });

    // ── Room AJAX
    const roomTypes = @json($room_types->values());

    function fetchRooms(floorId, skip, append) {
      const canEditRooms = window.canEditRooms ?? false;
      const canDeleteRooms = window.canDeleteProperty ?? false;
      const grid = document.querySelector(`.room-list[data-floor-rooms="${floorId}"]`);
      const rtype = document.querySelector(`.room-type-filter[data-floor="${floorId}"]`)?.value ?? '';
      const wrap = document.querySelector(`.floor-load-more-wrap[data-floor-id="${floorId}"][data-mode="rooms"]`);

      const params = new URLSearchParams({ skip, take: 4 });
      if (rtype) params.set('room_type', rtype);

      return fetch(`/floor/${floorId}/rooms?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
          if (!append) {
            grid.innerHTML = '';
            if (floorSelections.has(floorId)) {
              floorSelections.delete(floorId);
              syncBtn(floorId);
            }
          }

          if (!data.rooms.length && !append) {
            grid.innerHTML = `
                        <div class="col-12 d-flex flex-column align-items-center justify-content-center py-4 text-muted">
                            <i class="ti tabler-alert-triangle-filled text-warning fs-2 mb-1"></i>
                            <small class="fst-italic">No rooms found.</small>
                        </div>`;
            if (wrap) wrap.remove();
            return;
          }

          data.rooms.forEach(room => {
            const typeTitle = roomTypes.find(t => t.id == room.room_type)?.title ?? 'Room';
            const label = typeTitle.charAt(0).toUpperCase() + typeTitle.slice(1);
            const amenityIds = Array.isArray(room.amenities) ? room.amenities : JSON.parse(room.amenities ?? '[]');
            const sqft = room.dimensions?.length >= 2
              ? Math.round(room.dimensions.reduce((a, d) => a * parseFloat(d.size), 1))
              : 0;

            const dimsHtml = (room.dimensions ?? []).map(rd => `
                        <span class="dim-side-chip rounded px-2 py-1 d-inline-flex align-items-center gap-1">
                            <span class="dim-side-name">${rd.name.charAt(0).toUpperCase() + rd.name.slice(1)}</span>
                            <span class="dim-side-unit">${rd.size} ${rd.unit}</span>
                        </span>`).join('');

            const allAmenities = @json($amenities->values());
            const amenitiesHtml = amenityIds.map(id => {
              const a = allAmenities.find(x => x.id == id);
              if (!a) return '';
              const title = a.title.replace(/\b\w/g, c => c.toUpperCase());
              return `<span class="amenity-chip rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1">
                                    <i class="ti tabler-check fa-xs"></i> ${title}
                                </span>`;
            }).join('');

            const editRoomBtn = canEditRooms ? `
                            <button type="button" class="btn btn-icon room-icon-btn edit-room-btn"
                                    data-bs-toggle="modal" data-bs-target="#room_modal"
                                    data-room-id="${room.id}" data-floor-id="${floorId}" title="Edit Room">
                                <i class="ti tabler-pencil"></i>
                            </button>` : '';

            const deleteRoomBtn = canDeleteRooms ? `
                            <a href="/property/destroy/room/${room.uuid}" onclick="confirmDelete(event,null)"
                               class="btn btn-icon room-icon-btn room-icon-btn-danger text-danger" title="Delete Room">
                                <i class="ti tabler-trash"></i>
                            </a>` : '';

            const actionsHtml = (editRoomBtn || deleteRoomBtn) ? `
                        <div class="d-flex align-items-center flex-shrink-0 room-card-actions">
                            ${editRoomBtn}
                            ${deleteRoomBtn}
                        </div>` : '';

            const col = document.createElement('div');
            col.className = 'col-sm-6 col-lg-4 col-xl-3 room-col';
            col.innerHTML = `
                        <div class="card h-100 border-0 shadow-sm room-card" data-id="${room.id}">
                            <div class="select-overlay"></div>
                            <div class="card-accent-wrap"><div class="card-accent"></div></div>
                            <div class="card-body p-3">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <span class="unit-icon-wrap d-flex align-items-center justify-content-center rounded-circle flex-shrink-0">
                                            <i class="ti tabler-door"></i>
                                        </span>
                                        <span class="unit-name fw-semibold text-truncate">${label}</span>
                                    </div>
                                    ${actionsHtml}
                                </div>
                                <div class="d-flex align-items-center gap-3 text-muted small mb-2">
                                    <span class="d-flex align-items-center gap-1">
                                        <i class="ti tabler-ruler-2"></i> ${sqft} sq ft
                                    </span>
                                </div>
                                ${dimsHtml ? `<div class="d-flex flex-wrap gap-1 mb-2">${dimsHtml}</div>` : ''}
                                ${amenitiesHtml ? `<div class="d-flex flex-wrap gap-1">${amenitiesHtml}</div>` : ''}
                            </div>
                        </div>`;
            grid.appendChild(col);
          });

          const btn = document.querySelector(`.floor-load-more-btn[data-floor-id="${floorId}"][data-mode="rooms"]`);
          if (data.has_more) {
            if (btn) btn.dataset.skip = data.next_skip;
          } else {
            if (wrap) wrap.remove();
          }
        });
    }

    // Room type filter change
    document.addEventListener('change', function(e) {
      const sel = e.target.closest('.room-type-filter');
      if (!sel) return;
      fetchRooms(sel.dataset.floor, 0, false);
    });

    // Load more
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.floor-load-more-btn');
      if (!btn) return;

      const floorId = btn.dataset.floorId;
      const skip = parseInt(btn.dataset.skip, 10);
      const spinner = btn.querySelector('.floor-spinner');
      const label = btn.querySelector('.floor-load-more-text');
      const isRooms = btn.dataset.mode === 'rooms';

      btn.disabled = true;
      spinner.classList.remove('d-none');
      label.textContent = 'Loading…';

      (isRooms ? fetchRooms(floorId, skip, true) : fetchUnits(floorId, skip, true))
        .finally(() => {
          btn.disabled = false;
          spinner.classList.add('d-none');
          label.textContent = 'Load More';
        });
    });

    window.floorTypesJson = @json($floor_types->values());
    window.unitTypesJson = @json($unit_types->values());
    window.roomTypesJson = @json($room_types->values());
    window.amenitiesJson = @json($amenities->values());
    window.canEditRooms = @json(auth()->user()->can('edit_property'));
    window.canDeleteProperty = @json(auth()->user()->can('delete_property'));
    window.editIsResidential = {{ $property->category === 'residential' ? 'true' : 'false' }};
    window.unitUpdateRoute = "{{ route('unit.update') }}";
    window.floorRoomRoute = "{{ route('floor.room.store') }}";

    // ── Card selection & bulk delete
    document.addEventListener('click', function(e) {
      if (e.target.closest('a, button, .dropdown')) return;
      const card = e.target.closest('.unit-card, .room-card');
      if (!card) return;

      const unitGrid = card.closest('[data-floor-units]');
      const roomGrid = card.closest('[data-floor-rooms]');
      const floorId = unitGrid?.dataset.floorUnits || roomGrid?.dataset.floorRooms;
      if (!floorId) return;

      if (!floorSelections.has(floorId)) {
        floorSelections.set(floorId, { type: unitGrid ? 'units' : 'rooms', ids: new Set() });
      }
      const sel = floorSelections.get(floorId);
      const id = card.dataset.id;

      card.classList.toggle('card-selected') ? sel.ids.add(id) : sel.ids.delete(id);
      syncBtn(floorId);
    });

    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.floor_bulk_delete_btn');
      if (!btn) return;

      const floorId = btn.dataset.floorId;
      const sel = floorSelections.get(floorId);
      if (!sel?.ids.size) return;

      Swal.fire({
        title: 'Are you sure?',
        text: 'You won\'t be able to revert this!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
      }).then(result => {
        if (!result.isConfirmed) return;
        btn.disabled = true;
        fetch('{{ route("construction.bulk_delete") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ [sel.type]: Array.from(sel.ids) })
        })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              sel.ids.forEach(id => {
                document.querySelector(`.unit-card[data-id="${id}"], .room-card[data-id="${id}"]`)
                  ?.closest('.unit-col, .room-col')?.remove();
              });
              floorSelections.delete(floorId);
              syncBtn(floorId);
              notify('Deleted successfully.', 'success');
            } else {
              notify('Delete failed.', 'error');
            }
          })
          .catch(() => notify('Something went wrong.', 'error'))
          .finally(() => {
            btn.disabled = false;
          });
      });
    });

    // ── Clone unit modal
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.clone');
      if (!btn) return;

      const card = btn.closest('.unit-card');
      const unitId = card?.dataset.id;
      if (!unitId) return;

      document.getElementById('clone_unit_id').value = unitId;
      document.getElementById('clone_prefix').value = '';
      document.getElementById('clone_start').value = '';
      document.getElementById('clone_end').value = '';
      document.getElementById('clone_preview').classList.add('d-none');
      document.getElementById('clone_error').classList.add('d-none');

      new bootstrap.Modal(document.getElementById('clone_unit_modal')).show();
    });

    ['clone_prefix', 'clone_start', 'clone_end'].forEach(id => {
      document.getElementById(id)?.addEventListener('input', updateClonePreview);
    });

    function updateClonePreview() {
      const prefix = document.getElementById('clone_prefix').value.trim();
      const start = parseInt(document.getElementById('clone_start').value);
      const end = parseInt(document.getElementById('clone_end').value);
      const preview = document.getElementById('clone_preview');
      const errorBox = document.getElementById('clone_error');

      errorBox.classList.add('d-none');

      if (!prefix || isNaN(start) || isNaN(end) || start < 1 || end < start) {
        preview.classList.add('d-none');
        return;
      }

      const count = end - start + 1;

      if (count > 20) {
        preview.classList.add('d-none');
        errorBox.textContent = 'Maximum 20 units allowed per clone operation.';
        errorBox.classList.remove('d-none');
        return;
      }

      const names = [];
      for (let i = start; i <= end; i++) names.push(`${prefix}-${i}`);

      document.getElementById('clone_count').textContent = count;
      document.getElementById('clone_preview_names').innerHTML = names
        .map(n => `<span class="badge bg-label-primary">${n}</span>`)
        .join('');
      preview.classList.remove('d-none');
    }

    document.getElementById('clone_submit_btn')?.addEventListener('click', function() {
      const unitId = document.getElementById('clone_unit_id').value;
      const prefix = document.getElementById('clone_prefix').value.trim();
      const start = parseInt(document.getElementById('clone_start').value);
      const end = parseInt(document.getElementById('clone_end').value);
      const errorBox = document.getElementById('clone_error');
      const spinner = document.getElementById('clone_spinner');
      const btn = this;

      errorBox.classList.add('d-none');

      if (!prefix || isNaN(start) || isNaN(end) || start < 1 || end < start) {
        errorBox.textContent = 'Please fill in all fields correctly.';
        errorBox.classList.remove('d-none');
        return;
      }

      if ((end - start + 1) > 20) {
        errorBox.textContent = 'Maximum 20 units allowed per clone operation.';
        errorBox.classList.remove('d-none');
        return;
      }

      btn.disabled = true;
      spinner.classList.remove('d-none');

      fetch('{{ route("unit.clone") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ unit_id: unitId, prefix, start, end })
      })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('clone_unit_modal')).hide();
            notify(data.message, 'success');
            const card = document.querySelector(`.unit-card[data-id="${unitId}"]`);
            const floorGrid = card?.closest('[data-floor-units]');
            if (floorGrid) fetchUnits(floorGrid.dataset.floorUnits, 0, false);
          } else {
            errorBox.innerHTML = data.message +
              (data.conflicts?.length
                ? '<br><strong>Conflicting:</strong> ' + data.conflicts.join(', ')
                : '');
            errorBox.classList.remove('d-none');
          }
        })
        .catch(() => {
          errorBox.textContent = 'Something went wrong. Please try again.';
          errorBox.classList.remove('d-none');
        })
        .finally(() => {
          btn.disabled = false;
          spinner.classList.add('d-none');
        });
    });

    document.querySelectorAll('.feature-chip-input').forEach(function(input) {
      input.addEventListener('change', function() {
        this.closest('.feature-chip').classList.toggle('active', this.checked);
      });
    });
  </script>
@endpush
