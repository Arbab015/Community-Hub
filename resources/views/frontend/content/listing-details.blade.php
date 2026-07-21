@extends('layouts/layoutMaster')

@section('title', ucwords($type === 'unit' ? ($entity->unit_name ?: 'Unit') : ($entity->name ?: 'Property')))

@php
  $isUnit = $type === 'unit';
  $parentProperty = $isUnit ? $entity->floor->property : $entity;
  $block = $isUnit ? $entity->floor->property->block : $entity->block;
  $addressParts = collect([
      $parentProperty->street,
      $parentProperty->landmark,
      $block?->name,
      $society?->address,
      $society?->name,
      $society?->city,
  ])->filter();
  $address = ucwords($addressParts->implode(', '));
  $isRent = $listing?->listing_type === 'rent';
  $title = $isUnit
      ? ($entity->unit_name
          ? ucwords($entity->unit_name)
          : 'Unit')
      : ($entity->name ?:
      ucwords($entity->type));
  $existingInquiry = ($inquiriesByListing ?? collect())->get($listing?->id);
  $hasInquired = (bool) $existingInquiry;
  $favType = $isUnit ? \App\Models\PropertyUnit::class : \App\Models\Property::class;
  $isFavourited = ($favouritedKeys ?? collect())->contains($favType . ':' . $entity->id);
  $currency = $currency ?? 'PKR';
$currencyRates = $currencyRates ?? ['PKR' => 1];
$convertedPrice = $listing?->price
    ? \App\Helpers\CurrencyConverter::convert((float) $listing->price, $listing->unit ?? 'PKR', $currency, $currencyRates)
    : null;
$videoExtensions = ['mp4', 'mov', 'avi', 'webm', 'mkv'];
$isVideo = fn($img) => in_array(strtolower($img->extension ?? ''), $videoExtensions);
@endphp

@section('vendor-style')
  <script>
    window.config = window.config || {
      colors: {
        primary: '#696cff',
        info: '#03c3ec',
        warning: '#ffab00',
        success: '#71dd37',
        danger: '#ff3e1d'
      }
    };
  </script>
  @vite(['resources/assets/vendor/libs/notyf/notyf.scss', 'resources/assets/vendor/libs/notyf/notyf.js', 'resources/assets/vendor/libs/swiper/swiper.scss', 'resources/assets/vendor/libs/leaflet/leaflet.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/swiper/swiper.js', 'resources/assets/vendor/libs/leaflet/leaflet.js'])
@endsection

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/ui-carousel.scss' , 'resources/assets/vendor/scss/pages/front-page-help-center.scss'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/ui-toasts.js', 'resources/assets/js/ui-carousel.js', 'resources/assets/js/listing-map.js'])
@endsection

@section('content')
  <section class="pb-3 bg-body first-section-pt">
    <div class="container">


      <div class="row g-4">
        <div class="col-lg-8">

          {{-- Gallery: Swiper main + thumbnails (only when 2+ images) --}}
          <div id="swiper-gallery">
            @if ($images->count() > 1)
              <div class="swiper gallery-top">
                <div class="swiper-wrapper">
                  @foreach ($images as $img)
                    @if ($isVideo($img))
                      <div class="swiper-slide rounded ld-video-slide">
                        <video class="ld-gallery-video" controls playsinline preload="metadata" muted>
                          <source src="{{ asset('storage/' . $img->link) }}">
                          Your browser does not support the video tag.
                        </video>
                        <span class="ld-media-badge"><i class="ti tabler-video"></i> Video</span>
                      </div>
                    @else
                      <div class="swiper-slide rounded"
                           style="background-image:url('{{ asset('storage/' . $img->link) }}')"></div>
                    @endif
                  @endforeach
                </div>
                <div class="swiper-button-next swiper-button-white custom-icon"></div>
                <div class="swiper-button-prev swiper-button-white custom-icon"></div>
                <button type="button" class="ld-gallery-save-btn text-primary {{ $isFavourited ? 'saved' : '' }}"
                        aria-label="Save property" onclick="event.preventDefault(); toggleFavourite(this)"
                        data-type="{{ $isUnit ? 'unit' : 'property' }}" data-uuid="{{ $entity->uuid }}">
                  <i class="ti {{ $isFavourited ? 'tabler-heart-filled ' : 'tabler-heart' }} icon-md"></i>
                </button>
              </div>

              <div class="swiper gallery-thumbs mb-3">
                <div class="swiper-wrapper">
                  @foreach ($images as $img)
                    @if ($isVideo($img))
                      <div class="swiper-slide rounded ld-video-thumb">
                        <video class="ld-thumb-video" muted playsinline preload="metadata"
                               disablepictureinpicture disableremoteplayback>
                          <source src="{{ asset('storage/' . $img->link) }}">
                        </video>
                        <span class="ld-thumb-play"><i class="ti tabler-player-play-filled"></i></span>
                        <span class="ld-media-badge ld-media-badge-sm"><i class="ti tabler-video"></i></span>
                      </div>
                    @else
                      <div class="swiper-slide rounded"
                           style="background-image:url('{{ asset('storage/' . $img->link) }}')"></div>
                    @endif
                  @endforeach
                </div>
              </div>
            @elseif ($images->count() === 1)
              <div class="position-relative ld-gallery-single">
                @if ($isVideo($images->first()))
                  <video class="w-100 ld-carousel-img rounded ld-single-video" controls playsinline
                         preload="metadata" muted>
                    <source src="{{ asset('storage/' . $images->first()->link) }}">
                    Your browser does not support the video tag.
                  </video>
                  <span class="ld-media-badge"><i class="ti tabler-video"></i> Video</span>
                @else
                  <img src="{{ asset('storage/' . $images->first()->link) }}" class="w-100 ld-carousel-img rounded"
                       alt="{{ $title }}">
                @endif
                <button type="button" class="ld-gallery-save-btn text-primary {{ $isFavourited ? 'saved' : '' }}"
                        aria-label="Save property" onclick="event.preventDefault(); toggleFavourite(this)"
                        data-type="{{ $isUnit ? 'unit' : 'property' }}" data-uuid="{{ $entity->uuid }}">
                  <i class="ti {{ $isFavourited ? 'tabler-heart-filled ' : 'tabler-heart' }} icon-md"></i>
                </button>
              </div>
            @else
              <div class="position-relative ld-gallery-single">
                <img
                  src="{{ $entity->attachment?->link
        ? asset('storage/' . $entity->attachment->link)
        : asset('assets/img/my_images/dummy_property_image.png') }}"
                  class="w-100 ld-carousel-img rounded"
                  alt="No image available">
                <button type="button" class="ld-gallery-save-btn text-primary {{ $isFavourited ? 'saved' : '' }}"
                        aria-label="Save property" onclick="event.preventDefault(); toggleFavourite(this)"
                        data-type="{{ $isUnit ? 'unit' : 'property' }}" data-uuid="{{ $entity->uuid }}">
                  <i class="ti {{ $isFavourited ? 'tabler-heart-filled ' : 'tabler-heart' }} icon-md"></i>
                </button>
              </div>
            @endif
          </div>

          {{-- ONE consolidated card, sectioned internally --}}
          <div class="card border-0 shadow-sm rounded-4 ld-master-card mb-4">

            {{-- Header: title, address, price --}}
            <div class="p-4 pb-3">
              <div>
                <span
                  class="badge rounded-pill small {{ $isRent ? 'bg-success' : 'bg-primary' }} bg-opacity-10 {{ $isRent ? 'text-success' : 'text-primary' }} mb-2 d-inline-flex align-items-center gap-1">
                  <i class="ti {{ $isRent ? 'tabler-key' : 'tabler-tag' }}"></i>
                  {{ $isRent ? 'For Rent' : 'For Sale' }}
                </span>

                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                  <h5 class="fw-bold mb-1">{{ $title }}</h5>
                  @if ($listing?->show_price && $listing->price)
                    <div class="text-end flex-shrink-0">
                      <div class="fs-5 fw-bold text-primary">{{ number_format($convertedPrice, 0) }}
                        <small>{{ $currency }}</small></div>
                      @if ($isRent)
                        <small class="text-muted">/month</small>
                      @endif
                    </div>
                  @endif
                </div>

                @if ($address)
                  <p class="text-muted mb-0 small d-flex align-items-start gap-1">
                    <i class="ti tabler-map-pin flex-shrink-0"></i> <span>{{ $address }}</span>
                  </p>
                @endif
              </div>
            </div>


            {{-- Quick facts strip --}}
            <div class="ld-quickfacts px-4 pb-3">
              <div class="ld-quickfact">
                <i class="ti tabler-ruler-2"></i>
                <span class="fw-semibold small">{{ round($areaSqft, 0) }}</span>
                <span class="text-muted small">sq ft</span>
              </div>

              @if (!$isUnit)
                @if ($floorsCount ?? 0)
                  <div class="ld-quickfact">
                    <i class="ti tabler-stack-2"></i>
                    <span class="fw-semibold small">{{ $floorsCount }}</span>
                    <span class="text-muted small">{{ Illuminate\Support\Str::plural('Floor', $floorsCount) }}</span>
                  </div>
                @endif
                <div class="ld-quickfact">
                  <i class="ti tabler-building"></i>
                  <span
                    class="fw-semibold small">{{ ucwords(str_replace('_', ' ', $entity->construction_status)) }}</span>
                </div>
              @else
                @if ($floorTitle ?? null)
                  <div class="ld-quickfact">
                    <i class="ti tabler-stack-2"></i>
                    <span class="fw-semibold small">{{ ucwords($floorTitle)  }}</span>

                  </div>
                @endif
              @endif

              @if ($totalRooms)
                <div class="ld-quickfact">
                  <i class="ti tabler-bed"></i>
                  <span class="fw-semibold small">{{ $totalRooms }}</span>
                  <span class="text-muted small">{{ Illuminate\Support\Str::plural('Room', $totalRooms) }}</span>
                </div>
              @endif

            </div>

            {{-- Section: General info --}}
            <div class="ld-section">
              <div class="ld-section-marker ld-bg-blue"><i class="ti tabler-info-circle"></i></div>
              <div class="ld-section-body">
                <p class="ld-section-title">General information</p>
                <div class="ld-fact-row">
                  <span>Type</span><strong
                    class="small">{{ ucfirst($entity->type ?? ($unitTypeTitle ?? 'Unit')) }}</strong>
                </div>
                @if (!$isUnit)
                  <div class="ld-fact-row"><span>Category</span><strong
                      class="small">{{ ucfirst($entity->category) }}</strong></div>
                  <div class="ld-fact-row">
                    <span>Status</span><strong
                      class="small">{{ ucwords(str_replace('_', ' ', $entity->construction_status)) }}</strong>
                  </div>
                @else
                  <div class="ld-fact-row">
                    <span>Belongs to property</span><strong
                      class="small">#{{ $entity->floor->property->property_no }}</strong>
                  </div>
                @endif
                <div class="ld-fact-row"><span>Purpose</span><strong
                    class="small">{{ $isRent ? 'For rent' : 'For sale' }}</strong>
                </div>
                <div class="ld-fact-row"><span>Society</span><strong
                    class="small text-end">{{ $society->name ?: '—' }}</strong>
                </div>
              </div>
            </div>

            {{-- Section: Area & dimensions --}}
            <div class="ld-section">
              <div class="ld-section-marker ld-bg-purple"><i class="ti tabler-ruler-2"></i></div>
              <div class="ld-section-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                  <p class="ld-section-title mb-2">Area and dimensions</p>
                  <select id="areaUnitSelect" class="form-select form-select-sm w-auto">
                    <option value="sqft">Square feet</option>
                    <option value="marla">Marla</option>
                    <option value="kanal">Kanal</option>
                  </select>
                </div>
                <div id="areaStat" data-area-sqft="{{ $areaSqft }}" data-marla-size="{{ $marlaSize }}"
                     class="d-flex align-items-baseline gap-1 mb-2">
                  <span class="fs-5 fw-bold" id="areaValue">{{ round($areaSqft, 2) }}</span>
                  <span class="text-muted small" id="areaUnitLabel">sq ft total</span>
                </div>
                @if ($entity->dimensions->count())
                  <div class="d-flex flex-wrap gap-2">
                    @foreach ($entity->dimensions as $dim)
                      @php $val = app(\App\Http\Controllers\PropertiesController::class)->convertForUser($dim->size, $dim->unit); @endphp
                      <span class="ld-dim-pill">{{ ucfirst($dim->name) }}: {{ round($val, 2) }}
                        {{ ucfirst($dim->unit) }}</span>
                    @endforeach
                  </div>
                @endif
              </div>
            </div>

            {{-- Section: Floors, units & rooms (property only) --}}
            @if (!$isUnit && ($floorDetails ?? collect())->count())
              <div class="ld-section">
                <div class="ld-section-marker ld-bg-amber"><i class="ti tabler-stack-2"></i></div>
                <div class="ld-section-body">
                  <p class="ld-section-title">
                    Floors, units & rooms
                    <span class="text-muted fw-normal">({{ $floorsCount }} floors, {{ $totalUnits }} units, {{ $totalRooms }} rooms)</span>
                  </p>

                  <div class="ld-floor-tabs" role="tablist">
                    @foreach ($floorDetails as $i => $floor)
                      <button type="button" class="ld-floor-tab {{ $i === 0 ? 'active' : '' }}"
                              onclick="switchFloorTab({{ $i }})" id="floorTabBtn{{ $i }}">
                        <i class="ti tabler-building"></i> {{ ucwords($floor['title']) }}
                      </button>
                    @endforeach
                  </div>

                  @foreach ($floorDetails as $i => $floor)
                    <div class="ld-floor-panel {{ $i === 0 ? '' : 'd-none' }}" id="floorPanel{{ $i }}">

                      @if ($floor['units']->count())
                        <p class="fw-semibold small text-muted mb-2">Units on this floor</p>
                        <div class="ld-unit-card-single">
                          @foreach ($floor['units'] as $unitGroup)
                            <div class="ld-unit-row">
                              <div class="ld-unit-row-head">
                                <i class="ti tabler-door ld-unit-row-icon"></i>
                                <span class="ld-unit-row-title">{{ ucwords($unitGroup['unit_type_title']) }}</span>
                                <span class="ld-unit-row-count">{{ $unitGroup['count'] }}x</span>
                              </div>
                              @if ($unitGroup['rooms']->count())
                                @include('frontend.components.room_breakdown_table', ['groups' => $unitGroup['rooms']])
                              @elseif ($unitGroup['dimensions']->count())
                                <table class="ld-room-table">
                                  <tbody>
                                  <tr>
                                    <td class="ld-room-td-count">1x</td>
                                    <td class="ld-room-td-dims">
                                      <div class="ld-dims-row">
                                        @foreach ($unitGroup['dimensions'] as $dim)
                                          <span class="ld-dim-item me-2">
                                              <span
                                                class="ld-dim-label badge bg-label-secondary">{{ $dim['name'] }}</span>
                                              <span class="ld-dim-value">{{ $dim['value'] }} {{ $dim['unit'] }}</span>
                                            </span>
                                        @endforeach
                                      </div>
                                    </td>
                                  </tr>
                                  </tbody>
                                </table>
                              @endif
                            </div>
                          @endforeach
                        </div>
                      @endif

                      @if ($floor['rooms']->count())
                        <p class="fw-semibold small text-muted mb-2 mt-3">Rooms directly on this floor</p>
                        @include('frontend.components.room_breakdown_table', ['groups' => $floor['rooms']])
                      @endif

                      @if (!$floor['units']->count() && !$floor['rooms']->count())
                        <p class="text-muted small mb-0">No rooms recorded for this floor.</p>
                      @endif

                    </div>
                  @endforeach

                </div>
              </div>
            @endif

            @if ($roomBreakdown->count() && $isUnit)
              <div class="ld-section">
                <div class="ld-section-marker ld-bg-green"><i class="ti tabler-door"></i></div>
                <div class="ld-section-body">
                  <p class="ld-section-title">Rooms <span class="text-muted fw-normal">({{ $totalRooms }}
                      total)</span>
                  </p>

                  @foreach ($roomBreakdown as $group)
                    <div class="ld-room-type-title">
                      {{ $group['title'] }} <span class="text-muted fw-normal">({{ $group['total'] }})</span>
                    </div>
                    <table class="ld-room-table">
                      <tbody>
                      @foreach ($group['variants'] as $variant)
                        <tr>
                          <td class="ld-room-td-count">
                            @if ($group['variants']->count() > 1)
                              {{ $variant['count'] }}x
                            @else
                              1x
                            @endif
                          </td>
                          <td class="ld-room-td-dims">
                            <div class="ld-dims-row">
                              @foreach ($variant['dimensions'] as $dim)
                                <span class="ld-dim-item me-2">
                                    <span class="ld-dim-label badge bg-label-secondary">{{ $dim['name'] }}</span>
                                    <span class="ld-dim-value">{{ $dim['value'] }} {{ $dim['unit'] }}</span>
                                  </span>
                              @endforeach
                            </div>
                          </td>
                        </tr>
                      @endforeach
                      </tbody>
                    </table>
                  @endforeach
                </div>
              </div>
            @endif
            {{-- Section: Amenities --}}
            @if ($amenityList->count())
              <div class="ld-section">
                <div class="ld-section-marker ld-bg-pink"><i class="ti tabler-check"></i></div>
                <div class="ld-section-body">
                  <p class="ld-section-title">Amenities</p>
                  <div class="d-flex flex-wrap gap-2">
                    @foreach ($amenityList as $a)
                      <span class="ld-amenity-chip">
                        <i class="ti tabler-check"></i> {{ ucwords($a->title) }}
                      </span>
                    @endforeach
                  </div>
                </div>
              </div>
            @endif

            {{-- Section: Description --}}
            @if ($listing?->description)
              <div class="ld-section">
                <div class="ld-section-marker ld-bg-gray"><i class="ti tabler-file-text"></i></div>
                <div class="ld-section-body">
                  <p class="ld-section-title">Description</p>
                  <p class="text-muted mb-4 ld-desc-clamped" id="listingDescription">
                    {{ ucwords($listing->description)}}</p>
                  <div class="float-end g-2 text-primary">
                    <button type="button" class="ld-desc-toggle d-none" id="descToggleBtn"
                            onclick="toggleDescription()">
                      <span id="descToggleLabel">See more</span>
                      <i class="ti tabler-chevron-down" id="descToggleIcon"></i>
                    </button>
                  </div>

                </div>
              </div>
            @endif

            {{-- Section: Location --}}
            <div class="ld-section">
              <div class="ld-section-marker ld-bg-blue"><i class="ti tabler-map-pin"></i></div>
              <div class="ld-section-body">
                <p class="ld-section-title">Location</p>
                <div class="leaflet-map" id="propertyLocationMap" style="height:320px; border-radius:12px;"
                     data-address="{{ $geocodeQueries[0] ?? $address }}"
                     data-country="{{ $fallbackCountry }}"
                     data-title="{{ $title }}"
                     data-geocode-url="{{ route('property.geocode') }}"
                     data-geocode-queries='{{ json_encode($geocodeQueries) }}'></div>
                <p class="text-muted small mb-0 mt-2 d-none" id="mapUnavailableMsg">
                  Exact location on map isn't available right now.
                </p>
              </div>
            </div>


          </div>
        </div>

        {{-- Sticky sidebar: contact + inquiry --}}
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm rounded-4  ld-contact-card">
            <div class="m-2 p-4" id="card-body">
              @if ($listing?->show_price && $listing->price)
                <div class="fs-5 fw-bold text-primary">{{ number_format($convertedPrice, 0) }} {{ $currency }}</div>
                @if ($isRent)
                  <div class="text-muted small mb-3">per month</div>
                @else
                  <div class="mb-3"></div>
                @endif
              @endif

              @if ($listing)
                @if ($listing->show_contact_no && $listing->contact_no)
                  <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#callForProperty"
                     data-contact="{{ $listing->contact_no }}"
                     class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="ti tabler-phone-filled"></i> Call Owner
                  </a>
                @endif

                <hr class="mb-3">

                @if ($hasInquired)
                  <div class="ld-inquiry-sent mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <i class="ti tabler-mail-check text-success"></i>
                      <span class="fw-semibold text-success small">You already sent an inquiry</span>
                    </div>
                  </div>
                @endif

                <div class="d-flex align-items-center gap-2 mb-3">
                  <div class="ld-section-marker ld-bg-blue">
                    <i class="ti tabler-send"></i>
                  </div>
                  <h6 class="fw-bold mb-0 small">{{ $hasInquired ? 'Update Your Inquiry' : 'Send an Inquiry' }}</h6>
                </div>

                <form method="POST" action="{{ route('inquiry.store') }}">
                  @csrf
                  <input type="hidden" name="redirect_url" value="{{ url()->full() }}">
                  <input type="hidden" name="listing_id" value="{{ $listing->id }}">

                  <div class="mb-2">
                    <label class="form-label small fw-semibold text-muted d-flex align-items-center gap-1">
                      <i class="ti tabler-user"></i> Full Name
                    </label>
                    <input type="text" name="name" class="form-control ld-inquiry-input" required
                           placeholder="Your full name" value="{{ $existingInquiry->name ?? '' }}">
                  </div>

                  <div class="mb-2">
                    <label class="form-label small fw-semibold text-muted mb-1 d-flex align-items-center gap-1">
                      <i class="ti tabler-phone"></i> Contact Number
                    </label>
                    <input type="text" name="contact_number" class="form-control ld-inquiry-input" required
                           placeholder="e.g. 03xx-xxxxxxx" value="{{ $existingInquiry->contact_number ?? '' }}">
                  </div>

                  <div class="mb-2">
                    <label class="form-label small fw-semibold text-muted mb-1 d-flex align-items-center gap-1">
                      <i class="ti tabler-mail"></i> Email Address
                    </label>
                    <input type="email" name="email" class="form-control ld-inquiry-input" required
                           placeholder="you@example.com" value="{{ $existingInquiry->email ?? '' }}">
                  </div>

                  <div class="mb-2">
                    <label class="form-label small fw-semibold text-muted mb-1 d-flex align-items-center gap-1">
                      <i class="ti tabler-message"></i> Message
                    </label>
                    <textarea name="message" rows="3" required class="form-control ld-inquiry-input"
                              placeholder="I'm interested in this property...">{{ $existingInquiry->message ?? "I'm interested in this property. Please contact me at your earliest convenience." }}</textarea>
                  </div>

                  <button type="submit"
                          class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="ti tabler-send"></i> {{ $hasInquired ? 'Update Inquiry' : 'Send Inquiry' }}
                  </button>
                </form>
              @else
                <p class="text-muted small mb-0">No contact details available for this listing.</p>
              @endif
            </div>
          </div>
        </div>
      </div>


    </div>


  </section>

  @include('_partials._modals.call_to_owner')

@endsection

@push('styles')
  <style>
    /* ---- Icon alignment fix (applies across this page) ---- */
    .ti {
      line-height: 1;
      vertical-align: -1px;
      display: inline-flex;
      align-items: center;
    }

    .ld-quickfact i,
    .ld-section-marker i,
    .ld-amenity-chip i,
    .ld-gallery-save-btn i,
    .ld-dim-pill i,
    .badge i {
      font-size: inherit;
      line-height: 1;
    }


    .ld-carousel-img {
      height: 380px;
      width: 100%;
      object-fit: cover;
    }

    @media (max-width: 767px) {
      .ld-carousel-img {
        height: 230px;
      }
    }

    .ld-gallery-save-btn {
      position: absolute;
      bottom: 16px;
      right: 16px;
      border-radius: 50%;
      border: none;
      background-color: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 17px;
      z-index: 3;
      transition: background .15s, transform .15s;
    }

    .ld-gallery-save-btn:hover {
      transform: scale(1.29);
    }


    /* ---- Master card ---- */
    .ld-master-card {
      overflow: hidden;
    }

    /* ---- Quick facts strip ---- */
    .ld-quickfacts {
      display: flex;
      flex-wrap: wrap;
      gap: 22px;
      border-bottom: 1px solid #f0f0f0;
      padding-bottom: 1.1rem !important;
    }

    .ld-quickfact {
      display: flex;
      align-items: center;
      gap: 7px;
      font-size: .9rem;
      color: #3a3e5c;
    }

    .ld-quickfact i {
      font-size: 19px;
      color: var(--bs-primary);
    }

    /* ---- Sections ---- */
    .ld-section {
      display: flex;
      gap: 14px;
      padding: 1.1rem 1.5rem;
      border-top: 0.5px solid #eee;
      align-items: flex-start;
    }

    .ld-section-marker {
      width: 26px;
      height: 26px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 13px;
    }

    .ld-bg-blue {
      background: #E6F1FB;
      color: #185FA5;
    }

    .ld-bg-purple {
      background: #EEEDFE;
      color: #534AB7;
    }

    .ld-bg-amber {
      background: #FAEEDA;
      color: #854F0B;
    }

    .ld-bg-green {
      background: #EAF3DE;
      color: #3B6D11;
    }

    .ld-bg-pink {
      background: #FBEAF0;
      color: #993556;
    }

    .ld-bg-gray {
      background: #F1EFE8;
      color: #5F5E5A;
    }

    .ld-section-body {
      flex: 1;
      min-width: 0;
    }


    .ld-section-title {
      font-size: 0.9rem;
      font-weight: 600;
      margin-top: 0;
      margin-bottom: 8px;
    }

    .ld-fact-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 6px 0;
      border-bottom: 1px dashed #f0f0f0;
      font-size: .85rem;
    }

    .ld-fact-row:last-child {
      border-bottom: none;
    }

    .ld-fact-row span {
      color: #8a8fa3;
    }

    .ld-dim-pill {
      background: #f8f9fc;
      border: 1px solid #eee;
      border-radius: 999px;
      padding: 3px 12px;
      font-size: .75rem;
      color: #534ab7;
    }

    .ld-amenity-chip {
      background: #f8f9fc;
      border: 1px solid #eee;
      border-radius: 999px;
      padding: 4px 12px;
      font-size: .78rem;
      color: #3a3e5c;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .ld-amenity-chip i {
      color: #3B6D11;
      font-size: .8rem;
    }

    /* ---- Description clamp / see more ---- */
    .ld-desc-clamped {
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .ld-desc-toggle {
      background: none;
      border: none;
      padding: 0;
      color: var(--bs-primary);
      font-size: .84rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .ld-desc-toggle:hover {
      text-decoration: underline;
    }

    .ld-desc-toggle i {
      font-size: .95rem;
      transition: transform .2s ease;
    }

    .ld-desc-toggle.expanded i {
      transform: rotate(180deg);
    }


    .ld-inquiry-sent {
      background: #EAF3DE;
      border: 1px solid #cfe3b8;
      border-radius: 10px;
      padding: 10px 12px;
    }

    .ld-room-card {
      background: #f8f9fc;
      border: 1px solid #eee;
      border-radius: 10px;
      padding: 12px;
      height: 100%;
    }

    .ld-room-icon {
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: #EAF3DE;
      color: #3B6D11;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
    }

    .ld-room-type-title {
      font-weight: 600;
      font-size: .85rem;
      color: #3a3e5c;
      margin: 14px 0 6px;
    }

    .ld-room-type-title:first-child {
      margin-top: 0;
    }

    .ld-room-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 4px;
      font-size: .85rem;
    }

    .ld-room-table tr:nth-child(odd) td {
      background: #f8f9fc;
    }

    .ld-room-table td {
      padding: 8px 12px;
      vertical-align: middle;
      border-bottom: 1px solid #f0f0f0;
    }

    .ld-room-td-count {
      width: 60px;
      font-weight: 600;
      color: #534ab7;
      white-space: nowrap;
    }

    .ld-room-td-dims {
      color: #3a3e5c;
      line-height: 1.9;
    }

    .ld-dim-badge {
      display: inline-block;
      background: #f1f2f7;
      color: #3a3e5c;
      border-radius: 6px;
      padding: 3px 10px;
      font-size: .78rem;
      font-weight: 500;
      margin: 2px 6px 2px 0;
    }

    .ld-inquiry-input {
      border-radius: 8px;
      border: 1px solid #e2e2e7;
      font-size: .85rem;
      padding: 9px 12px;
      transition: border-color .15s, box-shadow .15s;
    }

    .ld-inquiry-input:focus {
      border-color: var(--bs-primary);
      box-shadow: 0 0 0 .15rem rgba(105, 108, 255, .15);
    }

    .ld-fact-row strong {
      font-size: .85rem;
    }

    .ld-gallery-single {
      height: auto;
    }

    #swiper-gallery:has(> .ld-gallery-single) {
      height: auto;
    }

    #swiper-gallery {
      margin-bottom: 16px;
    }


    @media (min-width: 1200px) {
      .ld-contact-card {
        position: sticky;
        top: 90px;

      }
    }

    #card-body {
      max-height: calc(100vh - 110px);
      overflow-y: auto;
    }

    #listingDescription {
      font-size: 0.79rem;
      line-height: 1.6;
    }

    /* ---- Video slides in gallery ---- */
    .ld-video-slide {
      position: relative;
      background: #000;
      overflow: hidden;
    }

    .ld-gallery-video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      background: #000;
      display: block;
    }

    .ld-single-video {
      object-fit: cover;
      background: #000;
    }

    /* Type badge shown on video main/single slides */
    .ld-media-badge {
      position: absolute;
      top: 14px;
      left: 14px;
      z-index: 3;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: rgba(0, 0, 0, .6);
      color: #fff;
      font-size: .72rem;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 999px;
      box-shadow: 0 1px 4px rgba(0, 0, 0, .35);
      pointer-events: none;
    }

    .ld-media-badge i {
      font-size: .85rem;
    }

    .ld-media-badge-sm {
      top: 6px;
      left: 6px;
      padding: 4px 7px;
      font-size: 0;
      box-shadow: 0 1px 3px rgba(0, 0, 0, .35);
    }

    .ld-media-badge-sm i {
      font-size: .75rem;
    }

    /* Video thumbnails: real frame + play overlay */

    .ld-video-thumb {
      position: relative;
      background: #000;
      overflow: hidden;
    }

    .ld-thumb-video {
      position: absolute;
      inset: 0;
      z-index: 1;
      width: 100%;
      height: 100%;
      object-fit: cover;
      pointer-events: none;
    }

    .ld-thumb-play {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: rgba(0, 0, 0, .6);
      box-shadow: 0 1px 4px rgba(0, 0, 0, .4);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      z-index: 3;
      pointer-events: none;
    }

    .ld-unit-group-row {
      padding-bottom: 10px;
      margin-bottom: 10px;
      border-bottom: 1px dashed #e9e9ee;
    }

    .ld-unit-group-row:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }

    /* ---- Floor tabs ---- */
    .ld-floor-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 18px;
    }

    .ld-floor-tab {
      font-size: 12px;
      font-weight: 600;
      border: 1px solid #e2e2e7;
      background: #fff;
      color: #3a3e5c;
      border-radius: 6px;
      padding: 6px 12px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background .15s, border-color .15s, color .15s;
    }

    .ld-floor-tab i {
      font-size: 14px;
    }

    .ld-floor-tab:hover {
      border-color: #cfcfe0;
      background: #f8f9fc;
    }

    .ld-floor-tab.active {
      background: var(--bs-primary);
      border-color: var(--bs-primary);
      color: #fff;
    }

    /* ---- Units on this floor: one consolidated card ---- */
    .ld-unit-card-single {
      background: #f8f9fc;
      border: 1px solid #eee;
      border-radius: 10px;
      padding: 4px 14px;
      margin-bottom: 4px;
    }

    .ld-unit-row {
      padding: 12px 0;
      border-bottom: 1px solid #e5e5ee;
    }

    .ld-unit-row:last-child {
      border-bottom: none;
    }

    .ld-unit-row .ld-room-table tr:last-child td {
      border-bottom: none;
    }

    .ld-unit-row-head {
      display: flex;
      align-items: center;
      gap: 7px;
      margin-bottom: 11px;
    }

    .ld-unit-row-icon {
      font-size: 14px;
      color: #534AB7;
      flex-shrink: 0;
    }

    .ld-unit-row-title {
      font-weight: 600;
      font-size: .85rem;
      color: #3a3e5c;
      flex: 1;
    }

    .ld-unit-row-count {
      font-size: .72rem;
      font-weight: 700;
      color: #534ab7;
      background: #EEEDFE;
      border-radius: 999px;
      padding: 2px 10px;
      flex-shrink: 0;
    }

    .ld-unit-row .ld-room-type-title {
      font-size: .78rem;
      margin: 8px 0 4px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .ld-unit-row .ld-room-type-title::before {
      content: '';
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #7f77dd;
      flex-shrink: 0;
    }

    .ld-unit-row .ld-room-table {
      margin-bottom: 0;
    }

    .ld-unit-row .ld-room-table td {
      padding: 6px 10px;
    }

    .ld-unit-row-dims {
      margin-top: 4px;
    }

  </style>
@endpush

@push('scripts')
  <script>
    window.addEventListener('load', function() {
      @if (session('success'))
      notify(@json(session('success')), 'success');
      @endif

      @if (session('error'))
      notify(@json(session('error')), 'error');
      @endif
    });

    document.getElementById('callForProperty')?.addEventListener('show.bs.modal', function(e) {
      const btn = e.relatedTarget;
      const contact = btn?.dataset?.contact ?? '';
      document.getElementById('callModalNumber').textContent = contact;
      document.getElementById('callModalNumber').href = 'tel:' + contact;
      document.getElementById('callModalBtn').href = 'tel:' + contact;
      document.getElementById('copyFeedback')?.classList.add('d-none');
      document.getElementById('copyIcon').className = 'ti tabler-copy text-success';
    });

    function copyContact(numberId, feedbackId, iconId) {
      const num = document.getElementById(numberId).textContent;
      navigator.clipboard.writeText(num).then(() => {
        document.getElementById(feedbackId).classList.remove('d-none');
        document.getElementById(iconId).className = 'ti tabler-check text-primary';
        setTimeout(() => {
          document.getElementById(feedbackId).classList.add('d-none');
          document.getElementById(iconId).className = 'ti tabler-copy text-primary';
        }, 2500);
      });
    }

    (function() {
      const stat = document.getElementById('areaStat');
      if (!stat) return;
      const sqft = parseFloat(stat.dataset.areaSqft);
      const marlaSize = parseFloat(stat.dataset.marlaSize) || 272.25;
      const select = document.getElementById('areaUnitSelect');
      const valueEl = document.getElementById('areaValue');
      const labelEl = document.getElementById('areaUnitLabel');
      const labels = {
        sqft: 'sq ft total',
        marla: 'marla total',
        kanal: 'kanal total'
      };

      select.addEventListener('change', function() {
        let val = sqft;
        if (this.value === 'marla') val = sqft / marlaSize;
        if (this.value === 'kanal') val = sqft / (marlaSize * 20);
        valueEl.textContent = (Math.round(val * 100) / 100);
        labelEl.textContent = labels[this.value];
      });
    })();


    // Description: show "See more" only if the text actually overflows 3 lines
    (function() {
      const desc = document.getElementById('listingDescription');
      const btn = document.getElementById('descToggleBtn');
      if (!desc || !btn) return;

      requestAnimationFrame(function() {
        if (desc.scrollHeight > desc.clientHeight + 1) {
          btn.classList.remove('d-none');
        }
      });
    })();

    function toggleDescription() {
      const desc = document.getElementById('listingDescription');
      const btn = document.getElementById('descToggleBtn');
      const label = document.getElementById('descToggleLabel');
      const isClamped = desc.classList.contains('ld-desc-clamped');

      if (isClamped) {
        desc.classList.remove('ld-desc-clamped');
        label.textContent = 'See less';
        btn.classList.add('expanded');
      } else {
        desc.classList.add('ld-desc-clamped');
        label.textContent = 'See more';
        btn.classList.remove('expanded');
      }
    }

    // Favourite toggle for this listing's gallery heart button
    function toggleFavourite(btn) {
      const type = btn.dataset.type;
      const uuid = btn.dataset.uuid;
      const icon = btn.querySelector('i');

      btn.disabled = true;

      fetch("{{ route('property.favourite') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          type,
          uuid
        })
      })
        .then(res => {
          if (!res.ok) throw new Error('Request failed');
          return res.json();
        })
        .then(data => {
          btn.classList.toggle('saved', data.favourited);
          icon.classList.toggle('tabler-heart', !data.favourited);
          icon.classList.toggle('tabler-heart-filled', data.favourited);
        })
        .catch(err => {
          console.error('Favourite toggle failed:', err);
        })
        .finally(() => {
          btn.disabled = false;
        });
    }

    // Force video elements to paint a real frame instead of a black rectangle
    // (preload="metadata" alone doesn't guarantee a decoded frame in all browsers)
    document.querySelectorAll('.ld-thumb-video, .ld-gallery-video, .ld-single-video').forEach(function(video) {
      video.addEventListener('loadedmetadata', function() {
        try {
          video.currentTime = Math.min(0.1, (video.duration || 1) / 2);
        } catch (e) {
        }
      }, { once: true });
    });

    // Floor tab switching
    function switchFloorTab(index) {
      document.querySelectorAll('.ld-floor-tab').forEach((btn, i) => {
        btn.classList.toggle('active', i === index);
      });
      document.querySelectorAll('.ld-floor-panel').forEach((panel, i) => {
        panel.classList.toggle('d-none', i !== index);
      });
    }

  </script>
@endpush
