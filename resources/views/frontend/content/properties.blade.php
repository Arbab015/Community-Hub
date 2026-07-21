@extends('layouts/layoutMaster')

@section('title', 'Find Properties')


@section('vendor-script')
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
  @vite(['resources/assets/vendor/libs/notyf/notyf.scss', 'resources/assets/vendor/libs/notyf/notyf.js'])
@endsection
@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/front-page-help-center.scss'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/ui-toasts.js', 'resources/assets/js/property_search.js'])
@endsection

@php

  $cards = $properties->map(function ($property) use ($favouritedKeys, $inquiriesByListing, $currency, $currencyRates, $areaFactor) {
      $listing = $property->listing;
      $society = $property->block?->society;
      $favType = $property->matched_unit_uuid ? 'unit' : 'property';
      $favModelClass = $property->matched_unit_uuid ? \App\Models\PropertyUnit::class : \App\Models\Property::class;
      $favId = $property->matched_unit_uuid ? $property->matched_unit_id : $property->id;
      $existingInquiry = ($inquiriesByListing ?? collect())->get($listing?->id);

      $detailsUrl = ($property->matched_unit_uuid
          ? route('property.unit.show', $property->matched_unit_uuid)
          : route('property.show', $property->uuid)) . '?currency=' . urlencode($currency);

      return (object) [
          'property' => $property,
          'listing' => $listing,
          'society' => $society,
          'isRent' => $listing?->listing_type === 'rent',
          'isFavourited' => ($favouritedKeys ?? collect())->contains($favModelClass . ':' . $favId),
          'favType' => $favType,
          'favUuid' => $property->matched_unit_uuid ?: $property->uuid,
          'area' => $property->matched_unit_uuid ? ($property->unit_area_sqft ?? 0) : \App\Helpers\GetArea::calculate($property->dimensions),
          'cardTitle' => $property->matched_unit_uuid
              ? (ucwords($property->matched_unit_name) ?: (ucwords($property->matched_unit_type_title) ?: ucwords($property->name ?: $property->type)))
              : ucwords($property->name ?: $property->type),
          'address' => ucwords(collect([$property->street, $property->block?->name, $society?->address, $society?->city])->filter()->implode(', ')),
          'existingInquiry' => $existingInquiry,
          'hasInquired' => (bool) $existingInquiry,
          'detailsUrl' => $detailsUrl,
          'imageUrl' => $property->attachment?->link
              ? asset('storage/' . $property->attachment->link)
              : asset('assets/img/my_images/dummy_property_image.png'),
          'convertedPrice' => $listing?->price
              ? \App\Helpers\CurrencyConverter::convert((float) $listing->price, $listing->unit ?? 'PKR', $currency, $currencyRates)
              : null,
      ];
  });
@endphp

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
            @if($properties->total() > 0)
              {{ \Illuminate\Support\Str::plural('Property', $properties->total()) }}
              ({{ number_format($properties->total()) }})
            @else
              No Properties Found
            @endif
          </h5>
        </div>
        <div class="d-none d-md-flex align-items-center gap-2">
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
        @forelse($cards as $card)
          <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
            <div class="card prop-card-result h-100">

              <div class="prop-thumb position-relative">
                <a href="{{ $card->detailsUrl }}">
                  <img src="{{ $card->imageUrl }}"
                       alt="{{ $card->property->type }}"
                       class="w-100 h-100 object-fit-cover">
                </a>
                <span
                  class="prop-status-badge prop-status-{{ $card->isRent ? 'rent' : 'sell' }} position-absolute top-0 start-0 m-2 opacity-75">
                    {{ $card->isRent ? 'For Rent' : 'For Sale' }}
                 </span>

                <button
                  class="prop-fav {{ $card->isFavourited ? 'saved' : '' }} position-absolute top-0 end-0 m-2 rounded-circle d-flex align-items-center justify-content-center border-0"
                  onclick="event.preventDefault(); toggleFavourite(this)"
                  data-type="{{ $card->favType }}"
                  data-uuid="{{ $card->favUuid }}"
                  title="Save">
                  <i class="ti {{ $card->isFavourited ? 'tabler-heart-filled' : 'tabler-heart' }}"></i>
                </button>
              </div>
              <div class="card-body d-flex flex-column p-3">
                {{--                <a href="{{ $card->detailsUrl }}" class="text-decoration-none">--}}
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                  <h6 class="mb-0 fw-medium prop-title fw-semibold text-truncate flex-grow-1" style="min-width: 0;"
                      data-bs-toggle="tooltip" data-bs-placement="bottom"
                      data-bs-custom-class="tooltip-secondary"
                      data-bs-original-title="{{ $card->cardTitle }}">
                    {{ $card->cardTitle }}
                  </h6>
                  @if($card->listing?->show_price && $card->listing->price)
                    <div class="text-end flex-shrink-0">
                      <span class="fw-medium prop-price-val">{{ number_format($card->convertedPrice, 0) }}</span>
                      <span class="fw-medium prop-price-cur">{{ $currency }}</span>
                    </div>
                  @endif
                </div>

                @if($card->address)
                  <p class="mb-1 text-muted small prop-address address-clamp" data-bs-toggle="tooltip"
                     data-bs-placement="bottom"
                     data-bs-custom-class="tooltip-secondary"
                     data-bs-original-title="{{ $card->address }}">
                    <i class=" ti tabler-map-pin"></i> {{ $card->address }}
                  </p>
                @endif

                <div class="d-flex flex-column gap-1 text-muted small mb-1">
                  @if($card->society?->name)
                    <span class="d-inline-flex align-items-center gap-1">
                   <i class="ti tabler-building-community"></i> {{ ucwords($card->society->name) }}
               </span>
                  @endif
                  @if($card->area > 0)
                    <span class="d-inline-flex align-items-center gap-1">
                <i class="ti tabler-ruler-2"></i> {{ number_format($card->area / $areaFactor, 2) }} {{ $areaLabel }}
                </span>
                  @else
                    <span class="d-inline-flex align-items-center gap-1">
                <i class="ti tabler-door"></i> {{ $card->property->unit_rooms_count }} Rooms
                </span>
                  @endif
                </div>

                <p class="mb-1 text-muted prop-listed d-inline-flex align-items-center gap-1">
                  <i class="ti tabler-clock"></i> Listed {{ $card->property->created_at?->diffForHumans() }}
                </p>
                {{--                </a>--}}
                <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top">
                  <div class="contact_bar d-flex gap-2">
                    <button type="button" data-bs-toggle="modal" data-bs-target="#message_to_owner"
                            class="btn btn-sm btn-outline-primary text-primary rounded cursor-pointer"
                            data-listing-id="{{ $card->listing?->id }}"
                            data-existing="{{ $card->hasInquired ? '1' : '0' }}"
                            data-name="{{ $card->existingInquiry->name ?? '' }}"
                            data-email="{{ $card->existingInquiry->email ?? '' }}"
                            data-contact="{{ $card->existingInquiry->contact_number ?? '' }}"
                            data-message="{{ $card->existingInquiry->message ?? '' }}">
                      <i class="ti {{ $card->hasInquired ? 'tabler-mail-check' : 'tabler-mail-filled' }} icon-xs"></i>
                    </button>
                    @if($card->listing?->show_contact_no && $card->listing->contact_no)
                      <a class="btn btn-sm btn-outline-primary text-primary rounded"
                         data-bs-toggle="modal" data-bs-target="#callForProperty"
                         data-contact="{{ $card->listing->contact_no }}">
                        <i class="ti tabler-phone-filled me-1"></i>Call
                      </a>
                    @endif
                  </div>

                  <a href="{{ $card->detailsUrl }}" class="fw-medium small prop-view-link text-decoration-none">
                    View details <i class="ti tabler-arrow-right align-middle"></i>
                  </a>
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

      <div id="viewList" class="d-none row g-4">
        @foreach($cards as $card)
          <div class="col-12 col-md-6">
            <div class="card prop-list-card mb-3">
              <div class="card-body p-0 d-flex">

                <div class="prop-list-thumb flex-shrink-0 position-relative">
                  <img src="{{ $card->imageUrl }}" alt="{{ $card->property->type }}">

                  <span
                    class="prop-status-badge prop-status-{{ $card->isRent ? 'rent' : 'sell' }} position-absolute top-0 start-0 m-2 opacity-75">
                  {{ $card->isRent ? 'For Rent' : 'For Sale' }}
                </span>

                  <button
                    class="prop-fav {{ $card->isFavourited ? 'saved' : '' }} position-absolute top-0 end-0 m-2 rounded-circle d-flex align-items-center justify-content-center border-0"
                    onclick="event.preventDefault(); toggleFavourite(this)"
                    data-type="{{ $card->favType }}"
                    data-uuid="{{ $card->favUuid }}"
                    title="Save">
                    <i class="ti {{ $card->isFavourited ? 'tabler-heart-filled' : 'tabler-heart' }}"></i>
                  </button>
                </div>

                <div class="flex-grow-1 p-3 d-flex flex-column gap-2">
                  <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div class="flex-grow-1" style="min-width: 0;">
                      <h6 class="mb-1 fw-semibold prop-title text-truncate"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-custom-class="tooltip-secondary"
                          data-bs-original-title="{{ $card->cardTitle }}">
                        {{ $card->cardTitle }}
                      </h6>
                      @if($card->address)
                        <p class="text-muted mb-1 small prop-address list-address-clamp" data-bs-toggle="tooltip"
                           data-bs-placement="bottom"
                           data-bs-custom-class="tooltip-secondary"
                           data-bs-original-title="{{ $card->address }}">
                          <i class="ti tabler-map-pin"></i> {{ $card->address }}
                        </p>
                      @endif
                      <div class="d-flex flex-wrap gap-3 text-muted small">
                        @if($card->area > 0)
                          <span><i
                              class="ti tabler-ruler-2"></i> {{ number_format($card->area / $areaFactor, 2) }} {{ $areaLabel }}</span>
                        @else
                          <span> <i class="ti ti tabler-door"></i> {{ $card->property->unit_rooms_count }} Rooms</span>
                        @endif
                        @if($card->society?->name)
                          <span><i class="ti tabler-building-community"></i> {{ ucwords($card->society->name) }}</span>
                        @endif
                      </div>
                      <p class="mb-0 mt-1 prop-listed">
                        <i class="ti tabler-clock"></i> Listed {{ $card->property->created_at?->diffForHumans() }}
                      </p>
                    </div>

                    @if($card->listing?->show_price && $card->listing->price)
                      <div class="text-end flex-shrink-0">
                        <span class="fw-medium prop-price-val">{{ number_format($card->convertedPrice, 0) }}</span>
                        <span class="fw-medium prop-price-cur">{{ $currency }}</span>
                      </div>
                    @endif
                  </div>

                  <div class="d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
                    <div class="contact_bar d-flex gap-2">
                      <button type="button" data-bs-toggle="modal" data-bs-target="#message_to_owner"
                              class="btn btn-sm btn-outline-primary text-primary rounded cursor-pointer"
                              data-listing-id="{{ $card->listing?->id }}"
                              data-existing="{{ $card->hasInquired ? '1' : '0' }}"
                              data-name="{{ $card->existingInquiry->name ?? '' }}"
                              data-email="{{ $card->existingInquiry->email ?? '' }}"
                              data-contact="{{ $card->existingInquiry->contact_number ?? '' }}"
                              data-message="{{ $card->existingInquiry->message ?? '' }}">
                        <i class="ti {{ $card->hasInquired ? 'tabler-mail-check' : 'tabler-mail-filled' }} icon-xs"></i>
                      </button>

                      @if($card->listing?->show_contact_no && $card->listing->contact_no)
                        <a class="btn btn-sm btn-outline-primary text-primary rounded"
                           data-bs-toggle="modal" data-bs-target="#callForProperty"
                           data-contact="{{ $card->listing->contact_no }}">
                          <i class="ti tabler-phone-filled me-1"></i>Call
                        </a>
                      @endif
                    </div>
                    <a href="{{ $card->detailsUrl }}" class="fw-medium small prop-view-link text-decoration-none">
                      View details <i class="ti tabler-arrow-right align-middle"></i>
                    </a>
                  </div>
                </div>

              </div>
            </div>
          </div>
        @endforeach
      </div>

      @if($properties->hasPages())
        <div class="mt-3">
          {!! $properties->withQueryString()->links('pagination::bootstrap-5') !!}
        </div>
      @endif

      @include('_partials._modals.message_to_owner')
      @include('_partials._modals.call_to_owner')

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
          window.addEventListener('load', function() {
            @if (session('success'))
            notify(@json(session('success')), 'success');
            @endif

            @if (session('error'))
            notify(@json(session('error')), 'error');
            @endif
          });

          document.getElementById('message_to_owner').addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            const listingId = btn?.dataset?.listingId ?? '';
            const isExisting = btn?.dataset?.existing === '1';

            document.getElementById('inquiryListingId').value = listingId;
            document.getElementById('inquiryName').value = btn?.dataset?.name ?? '';
            document.getElementById('inquiryEmail').value = btn?.dataset?.email ?? '';
            document.getElementById('inquiryContact').value = btn?.dataset?.contact ?? '';
            document.getElementById('inquiryMessage').value = btn?.dataset?.message ?? '';

            const modalTitle = document.getElementById('mailForPropertyLabel');
            const submitBtn = document.getElementById('inquirySubmitBtn');
            if (modalTitle) {
              modalTitle.textContent = isExisting ? 'Update Your Inquiry' : 'Contact Owner';
            }
            if (submitBtn) {
              submitBtn.textContent = isExisting ? 'Update Inquiry' : 'Send Inquiry';
            }
          });


          document.getElementById('callForProperty').addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            const contact = btn?.dataset?.contact ?? '';
            const refNo = btn?.dataset?.ref ?? '';

            document.getElementById('callModalNumber').textContent = contact;
            document.getElementById('callModalNumber').href = 'tel:' + contact;
            document.getElementById('callModalBtn').href = 'tel:' + contact;
            document.getElementById('callModalRef').textContent = refNo ? 'Property #' + refNo : '';
            document.getElementById('copyFeedback').classList.add('d-none');
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


          function toggleFavourite(btn) {
            const type = btn.dataset.type;
            const uuid = btn.dataset.uuid;
            const icon = btn.querySelector('i');

            const allBtns = document.querySelectorAll(
              `.prop-fav[data-type="${type}"][data-uuid="${uuid}"]`
            );

            const wasFavourited = icon.classList.contains('tabler-heart-filled');

            fetch("{{ route('property.favourite') }}", {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
              },
              credentials: 'same-origin',
              body: JSON.stringify({ type, uuid })
            })
              .then(res => {
                if (!res.ok) throw new Error('Request failed');
                return res.json();
              })
              .then(data => {
                allBtns.forEach(b => {
                  const i = b.querySelector('i');
                  i.classList.toggle('tabler-heart', !data.favourited);
                  i.classList.toggle('tabler-heart-filled', data.favourited);
                  b.classList.toggle('saved', data.favourited);
                });
              })
              .catch(err => {
                console.error('Favourite toggle failed:', err);
                allBtns.forEach(b => {
                  const i = b.querySelector('i');
                  i.classList.toggle('tabler-heart', wasFavourited ? false : true);
                  i.classList.toggle('tabler-heart-filled', wasFavourited);
                  b.classList.toggle('saved', wasFavourited);
                });
              });
          }

          window.propSearchDefaults = {
            listing_type: "{{ $searchInputs['listing_type'] ?? 'sell' }}",
            category: "{{ $searchInputs['category']     ?? '' }}",
            type: "{{ $searchInputs['type']         ?? '' }}",
            unit_type: "{{ $searchInputs['unit_type']     ?? '' }}",
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
