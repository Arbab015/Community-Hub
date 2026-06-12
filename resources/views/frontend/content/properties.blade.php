@extends('layouts/layoutMaster')

@section('title', 'Find Properties')

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/front-page-help-center.scss'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/property_search.js'])
@endsection

@section('content')

  <section class="section-py first-section-pt help-center-header position-relative overflow-hidden">
    <img class="banner-bg-img z-n1" src="{{ asset('assets/img/pages/header.png') }}" alt="Property search header" />

    <div class="container position-relative" style="z-index:2">
      <h4 class="text-center text-primary mb-1">Find Your Perfect Property</h4>
      <p class="text-center text-muted mb-4 small">Search from thousands of listings across Pakistan</p>

      @include('frontend.components.search_section')
    </div>
  </section>

  <section class="m-6">
    <div class="container">

      {{-- Toolbar --}}
      <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
          <h5 class="mb-0 fw-semibold">
            @if($properties->count() > 0)
              {{ \Illuminate\Support\Str::plural('Property', $properties->count()) }}
              ({{ number_format($properties->count()) }})
            @else
              No Properties Found
            @endif
          </h5>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="text-muted small">View:</span>
          <button id="btnGrid" onclick="setView('grid')" class="view-btn active" title="Grid view">
            <i class="ti tabler-layout-grid"></i>
          </button>
          <button id="btnList" onclick="setView('list')" class="view-btn" title="List view">
            <i class="ti tabler-layout-list"></i>
          </button>
        </div>
      </div>

      <div id="viewGrid" class="row g-4">
        @forelse($properties as $property)
          @php
            $listing = $property->listing;
            $society = $property->block?->society;
            $isRent  = $listing?->listing_type === 'rent';
            $dim     = is_iterable($property->dimensions) ? collect($property->dimensions)->first() : $property->dimensions;
            $area    = $dim ? (($dim->length ?? 0) * ($dim->width ?? 0)) : 0;
            $address = collect([$property->street, $property->block?->name, $society?->name, $society?->city])->filter()->implode(', ');
          @endphp

          <div class="col-12 col-sm-6 col-xl-4">
            <div class="card prop-card-result h-100">

              <div class="prop-thumb">
                @php
                  $url = $property->attachment?->link
                  ? asset('storage/' . $property->attachment->link)
                  : asset('assets/img/my_images/dummy_property_image.png')
                @endphp
                <img src="{{ $url }}" alt="{{ $property->type }}">

                <span class="prop-badge prop-badge-{{ $isRent ? 'rent' : 'sell' }}">
                    {{ $isRent ? 'For Rent' : 'For Sale' }}
                  </span>

                @if($property->category)
                  <span class="prop-badge prop-badge-cat">{{ ucfirst($property->category) }}</span>
                @endif

                <button class="prop-fav" onclick="event.preventDefault(); this.classList.toggle('saved')"
                        title="Save">
                  <i class="ti tabler-heart"></i>
                </button>
              </div>

              {{-- Body --}}
              <div class="card-body d-flex flex-column gap-2 p-3">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <h6 class="mb-0 fw-semibold text-body" style="font-size:.9rem;line-height:1.3">
                    {{ $property->type ? ucfirst($property->type) : 'Property' }}
                  </h6>
                  @if($listing?->show_price && $listing->price)
                    <div class="text-end flex-shrink-0">
                       <span class="fw-bold text-primary" style="font-size:.95rem">
                          {{ number_format($rate > 0 ? $listing->price / $rate : $listing->price, 0) }}
                       </span>
                      <span class="fw-bold text-primary small"> {{ $currency }}</span>
                      @if($isRent)
                        <div class="text-muted" style="font-size:.68rem">/month</div>
                      @endif
                    </div>
                  @endif
                </div>

                @if($address)
                  <p class="mb-0 text-muted small">
                    <i class="ti tabler-map-pin"></i> {{ $address }}
                  </p>
                @endif

                <div class="d-flex flex-wrap gap-3 text-muted small">
                  @if($area > 0)
                    <span>
                        <i class="ti tabler-ruler-2"></i>
                        {{ number_format($area / $areaFactor, 2) }} {{ $areaLabel }}
                      </span>
                  @endif
                  @if($society?->name)
                    <span><i class="ti tabler-building-community"></i> {{ $society->name }}</span>
                  @endif
                </div>


                <div class="d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
                  @if($listing?->show_contact_no && $listing->contact_no)
                    <div class="contact_bar gap-2">
                      <button type="button" data-bs-toggle="modal" data-bs-target="#mailForProperty"
                              class="btn btn-sm btn-outline-primary text-primary  rounded cursor-pointer">
                        <i class="ti tabler-mail-filled icon-xs"></i></button>
                      <a class="btn btn-sm  btn-outline-primary text-primary rounded"><i
                          class="ti tabler-phone-filled me-1 "></i>Call</a>

                    </div>
                  @else
                    <span></span>
                  @endif
                  <span class="text-primary fw-semibold small">
                      View details <i class="ti tabler-arrow-right"></i>
                    </span>
                </div>
              </div>
            </div>
          </div>

        @empty
          <div class="col-12 py-5 align-items-center text-center">
            <i class="ti tabler-building-off fs-1 text-muted opacity-50 mb-3"></i>

            <h5 class="fw-semibold">No properties match your search</h5>

            <p class="text-muted mb-3">
              Try adjusting your filters or search a different location.
            </p>
          </div>
        @endforelse
      </div>

      <div id="viewList" class="d-none">
        @foreach($properties as $property)
          @php
            $listing = $property->listing;
            $society = $property->block?->society;

            $isRent  = $listing?->listing_type === 'rent';
            $dim     = is_iterable($property->dimensions) ? collect($property->dimensions)->first() : $property->dimensions;
            $area    = $dim ? (($dim->length ?? 0) * ($dim->width ?? 0)) : 0;
            $address = collect([$property->street, $property->block?->name, $society?->name, $society?->city])->filter()->implode(', ');
          @endphp
          <div class="card prop-list-card mb-3">
            <div class="card-body p-0 d-flex">

              <div class="prop-list-thumb flex-shrink-0 position-relative">
                @php
                  $url = $property->attachment?->link
                  ? asset('storage/' . $property->attachment->link)
                  : asset('assets/img/my_images/dummy_property_image.png')
                @endphp
                <img src="{{ $url }}" alt="{{ $property->type }}">

                <span class="prop-badge prop-badge-{{ $isRent ? 'rent' : 'sell' }}">
                    {{ $isRent ? 'For Rent' : 'For Sale' }}
                  </span>
              </div>

              <div class="flex-grow-1 p-3 d-flex flex-column gap-2">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                  <div>
                    <h6 class="mb-1 fw-semibold text-body">
                      {{ $property->type ? ucfirst($property->type) : 'Property' }}
                      @if($property->category)
                        <span class="badge bg-label-secondary ms-1">{{ ucfirst($property->category) }}</span>
                      @endif
                    </h6>
                    @if($address)
                      <p class="text-muted mb-1 small"><i class="ti tabler-map-pin"></i> {{ $address }}</p>
                    @endif
                    <div class="d-flex flex-wrap gap-3 text-muted small">
                      @if($area > 0)
                        <span><i class="ti tabler-ruler-2"></i> {{ number_format($area) }} sq ft</span>
                      @endif
                      @if($society?->name)
                        <span><i class="ti tabler-building-community"></i> {{ $society->name }}</span>
                      @endif
                    </div>
                  </div>
                  <div class="text-end flex-shrink-0">
                    @if($listing?->show_price && $listing->price)
                      <div class="fw-bold text-primary">
                        {{ number_format($rate > 0 ? $listing->price / $rate : $listing->price, 0) }}
                        <span class="text-muted fw-normal small">{{ $currency }}</span>
                      </div>
                      @if($isRent)
                        <div class="text-muted" style="font-size:.68rem">/month</div>
                      @endif
                    @endif
                    @if($listing?->show_contact_no && $listing->contact_no)
                      <p class="text-muted mt-1 mb-0 small"><i class="ti tabler-phone"></i> {{ $listing->contact_no }}
                      </p>
                    @endif
                  </div>
                </div>

                <div class="mt-auto">
                    <span class="text-primary fw-semibold small">
                      View details <i class="ti tabler-arrow-right"></i>
                    </span>
                </div>
              </div>

            </div>
          </div>
        @endforeach
      </div>

      {{-- Pagination --}}
      {{--      @if($properties->hasPages())--}}
      {{--        <div class="d-flex justify-content-center mt-5">--}}
      {{--          {{ $properties->links() }}--}}
      {{--        </div>--}}
      {{--      @endif--}}

    </div>
  </section>


  @include('_partials._modals.mail_for_property');

@endsection

@push('styles')
  <style>
    .help-center-header .banner-bg-img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      pointer-events: none;
    }

    .view-btn {
      border: 1px solid #dee2e6;
      background: #fff;
      border-radius: 6px;
      padding: 5px 9px;
      font-size: 1rem;
      color: #8a8fa3;
      cursor: pointer;
      line-height: 1;
      transition: background .15s, color .15s, border-color .15s;
    }

    .view-btn.active,
    .view-btn:hover {
      background: var(--bs-primary);
      color: #fff;
      border-color: var(--bs-primary);
    }

    .prop-card-result {
      border: 1px solid rgba(0, 0, 0, .08) !important;
      border-radius: 10px !important;
      overflow: hidden;
      transition: box-shadow .2s, transform .2s;
    }

    .prop-card-result:hover {
      box-shadow: 0 8px 28px rgba(105, 108, 255, .13) !important;
      transform: translateY(-3px);
    }

    .prop-thumb {
      position: relative;
      height: 190px;
      overflow: hidden;
      background: #f0f1f5;
    }

    .prop-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform .35s;
    }

    .prop-card-result:hover .prop-thumb img {
      transform: scale(1.04);
    }

    .prop-thumb-empty {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .prop-badge {
      position: absolute;
      font-size: .65rem;
      font-weight: 700;
      letter-spacing: .05em;
      padding: 3px 9px;
      border-radius: 4px;
    }

    .prop-badge-sell {
      top: 10px;
      left: 10px;
      background: #e7f0ff;
      color: #3e6ccc;
    }

    .prop-badge-rent {
      top: 10px;
      left: 10px;
      background: #e5f7ef;
      color: #22835a;
    }

    .prop-badge-cat {
      top: 10px;
      left: 85px;
      background: rgba(0, 0, 0, .3);
      color: #fff;
    }

    .prop-fav {
      position: absolute;
      top: 10px;
      right: 10px;
      background: rgba(255, 255, 255, .88);
      border: none;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .95rem;
      color: #aab0c0;
      cursor: pointer;
      transition: color .15s, background .15s;
    }

    .prop-fav:hover, .prop-fav.saved {
      color: #e03344;
      background: #fff;
    }

    .prop-desc-clamp {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .prop-list-card {
      border: 1px solid rgba(0, 0, 0, .08) !important;
      border-radius: 10px !important;
      overflow: hidden;
      transition: box-shadow .2s;
    }

    .prop-list-card:hover {
      box-shadow: 0 6px 20px rgba(105, 108, 255, .1) !important;
    }

    .prop-list-thumb {
      width: 210px;
      min-height: 150px;
      background: #f0f1f5;
      overflow: hidden;
    }

    .prop-list-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    @media (max-width: 767px) {
      .prop-list-thumb {
        width: 120px;
        min-height: 120px;
      }
    }

    .help-center-header {
      position: relative;
      z-index: 0;
      isolation: auto;
    }

    .prop-search,
    .prop-card {
      z-index: 0 !important;
      isolation: auto !important;
    }
  </style>
@endpush

@push('scripts')
  <script>
    function setView(v) {
      const grid = document.getElementById('viewGrid');
      const list = document.getElementById('viewList');
      const btnGrid = document.getElementById('btnGrid');
      const btnList = document.getElementById('btnList');
      if (v === 'grid') {
        grid.classList.remove('d-none');
        list.classList.add('d-none');
        btnGrid.classList.add('active');
        btnList.classList.remove('active');
      } else {
        list.classList.remove('d-none');
        grid.classList.add('d-none');
        btnList.classList.add('active');
        btnGrid.classList.remove('active');
      }
    }

    window.propSearchDefaults = {
      listing_type: "{{ $searchInputs['listing_type'] ?? 'sell' }}",
      category: "{{ $searchInputs['category']     ?? '' }}",
      type: "{{ $searchInputs['type']         ?? '' }}",
      city: "{{ $searchInputs['city']         ?? '' }}",
      country: "{{ $searchInputs['country']      ?? '' }}",
      min_price: "{{ $searchInputs['min_price']    ?? '' }}",
      max_price: "{{ $searchInputs['max_price']    ?? '' }}",
      min_area: "{{ $searchInputs['min_area']     ?? '' }}",
      max_area: "{{ $searchInputs['max_area']     ?? '' }}",
      currency: "{{ $searchInputs['currency']     ?? 'PKR' }}",
      area_unit: "{{ $searchInputs['area_unit']    ?? 'sqft' }}",
      rooms: "{{ $searchInputs['rooms']        ?? '' }}",
      floors: "{{ $searchInputs['floors']       ?? '' }}"
    };
  </script>
@endpush
