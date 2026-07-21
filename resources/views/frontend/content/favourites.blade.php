@extends('layouts/layoutMaster')

@section('title', 'My Favourites')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/notyf/notyf.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/notyf/notyf.js'])
@endsection

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/front-page-help-center.scss'])
@endsection

@section('page-script')
  <script>
    // Fallback so shared ui-toasts.js can init Notyf even without the admin config.js
    window.config = window.config || {
      colors: { info: '#03c3ec', warning: '#ffab00', success: '#71dd37', danger: '#ff3e1d' }
    };
  </script>
  @vite(['resources/assets/js/ui-toasts.js'])
@endsection

@section('content')
  <section class="section-py bg-body ">

    <div class="container position-relative">
      <div class="text-center mb-6">
        <span class="fav-hero-icon d-inline-flex align-items-center justify-content-center mb-3">
          <i class="ti tabler-heart-filled"></i>
        </span>
        <h4 class="text-primary mb-1 fav-hero-title">My Favourites</h4>
        <p class="text-muted mb-0 small">Properties you've saved</p>
      </div>

      <div id="favHeader"
           class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3 @if($properties->total() == 0) d-none @endif">
        <h5 class="mb-0 fw-semibold d-flex align-items-center gap-2">
          @if($properties->total() > 0)
            <span
              id="favCountLabel">{{ \Illuminate\Support\Str::plural('Saved Property', $properties->total()) }}</span>
            <span id="favCountBadge" class="fav-count-badge">{{ number_format($properties->total()) }}</span>
          @endif
        </h5>
      </div>

      <div id="viewGrid" class="row g-4">
        @forelse($properties as $property)
          @php
            $favType = $property->matched_unit_uuid ? \App\Models\PropertyUnit::class : \App\Models\Property::class;
            $favId   = $property->matched_unit_uuid ? $property->matched_unit_id : $property->id;
            $isFavourited = ($favouritedKeys ?? collect())->contains($favType.':'.$favId);
            $listing = $property->listing;
            $society = $property->block?->society;
            $isRent  = $listing?->listing_type === 'rent';
            $area    = $property->matched_unit_uuid
                ? ($property->unit_area_sqft ?? 0)
                : \App\Helpers\GetArea::calculate($property->dimensions);
            $cardTitle = $property->matched_unit_uuid
                ? (ucwords($property->matched_unit_name) ?: ucwords($property->matched_unit_type_title) ?? ucwords($property->type))
                : ($property->type ? ucwords($property->type) : 'Property');
            $address = ucwords(collect([$property->street, $property->block?->name, $society?->address, $society?->city])->filter()->implode(', '));
            $existingInquiry = ($inquiriesByListing ?? collect())->get($listing?->id);
            $hasInquired = (bool) $existingInquiry;
          @endphp

          <div class="col-12 col-sm-6 col-lg-4 col-xl-3"
               id="fav-card-{{ $favType === \App\Models\PropertyUnit::class ? 'unit' : 'property' }}-{{ $favId }}">
            <div class="card prop-card-result h-100">

              <div class="prop-thumb position-relative">
                @php
                  $url = $property->attachment?->link
                    ? asset('storage/' . $property->attachment->link)
                    : asset('assets/img/my_images/dummy_property_image.png')
                @endphp
                <img src="{{ $url }}" alt="{{ $property->type }}" class="w-100 h-100 object-fit-cover">

                <span
                  class="prop-status-badge prop-status-{{ $isRent ? 'rent' : 'sell' }} position-absolute top-0 start-0 m-2 opacity-75">
                {{ $isRent ? 'For Rent' : 'For Sale' }}
              </span>

                <button
                  class="prop-fav {{ $isFavourited ? 'saved' : '' }} position-absolute top-0 end-0 m-2 rounded-circle d-flex align-items-center justify-content-center border-0"
                  onclick="event.preventDefault(); toggleFavourite(this)"
                  data-type="{{ $property->matched_unit_uuid ? 'unit' : 'property' }}"
                  data-uuid="{{ $property->matched_unit_uuid ?: $property->uuid }}"
                  data-card-id="fav-card-{{ $favType === \App\Models\PropertyUnit::class ? 'unit' : 'property' }}-{{ $favId }}"
                  title="Remove from favourites">
                  <i class="ti {{ $isFavourited ? 'tabler-heart-filled' : 'tabler-heart' }}"></i>
                </button>
              </div>

              <div class="card-body d-flex flex-column p-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                  <h6 class="mb-0 fw-medium prop-title fw-semibold">{{ $cardTitle }}</h6>
                  @if($listing?->show_price && $listing->price)
                    <div class="text-end flex-shrink-0">
                    <span class="fw-medium prop-price-val">
                      {{ number_format(\App\Helpers\CurrencyConverter::convert((float) $listing->price, $listing->unit ?? 'PKR', $currency, $currencyRates), 0) }}
                    </span>
                      <span class="fw-medium prop-price-cur">{{ $currency }}</span>
                    </div>
                  @endif
                </div>

                @if($address)
                  <p class="mb-1 text-muted small prop-address address-clamp" data-bs-toggle="tooltip"
                     data-bs-placement="bottom" data-bs-custom-class="tooltip-secondary"
                     data-bs-original-title="{{ $address }}">
                    <i class="ti tabler-map-pin"></i> {{ $address }}
                  </p>
                @endif

                <div class="d-flex flex-column gap-1 text-muted small mb-1">
                  @if($society?->name)
                    <span class="d-inline-flex align-items-center gap-1">
                      <i class="ti tabler-building-community"></i> {{ $society->name }}
                    </span>
                  @endif
                  @if($area > 0)
                    <span class="d-inline-flex align-items-center gap-1">
                      <i class="ti tabler-ruler-2"></i> {{ number_format($area / $areaFactor, 2) }} {{ $areaLabel }}
                    </span>
                  @else
                    <span class="d-inline-flex align-items-center gap-1">
                      <i class="ti tabler-door"></i> {{ $property->unit_rooms_count }} Rooms
                    </span>
                  @endif
                </div>

                <p class="mb-1 text-muted prop-listed d-inline-flex align-items-center gap-1">
                  <i class="ti tabler-clock"></i> Listed {{ $property->created_at?->diffForHumans() }}
                </p>

                <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top">
                  <div class="contact_bar d-flex gap-2">
                    <button type="button" data-bs-toggle="modal" data-bs-target="#message_to_owner"
                            class="btn btn-sm btn-outline-primary text-primary rounded cursor-pointer"
                            data-listing-id="{{ $listing?->id }}"
                            data-existing="{{ $hasInquired ? '1' : '0' }}"
                            data-name="{{ $existingInquiry->name ?? '' }}"
                            data-email="{{ $existingInquiry->email ?? '' }}"
                            data-contact="{{ $existingInquiry->contact_number ?? '' }}"
                            data-message="{{ $existingInquiry->message ?? '' }}">
                      <i class="ti {{ $hasInquired ? 'tabler-mail-check' : 'tabler-mail-filled' }} icon-xs"></i>
                    </button>
                    @if($listing?->show_contact_no && $listing->contact_no)
                      <a class="btn btn-sm btn-outline-primary text-primary rounded"
                         data-bs-toggle="modal" data-bs-target="#callForProperty"
                         data-contact="{{ $listing->contact_no }}">
                        <i class="ti tabler-phone-filled me-1"></i>Call
                      </a>
                    @endif
                  </div>

                  @php
                    $detailsUrl = $property->matched_unit_uuid
                        ? route('property.unit.show', $property->matched_unit_uuid)
                        : route('property.show', $property->uuid);
                  @endphp
                  <a href="{{ $detailsUrl }}" class="fw-medium small prop-view-link text-decoration-none">
                    View details <i class="ti tabler-arrow-right align-middle"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>

        @empty
          <div class="col-12">
            <div class="fav-empty-state text-center mx-auto">
              <div class="fav-empty-icon-wrap mb-4">
                <i class="ti tabler-heart-off"></i>
              </div>
              <h5 class="fw-semibold mb-2">No favourites yet</h5>
              <p class="text-muted mb-4">Tap the heart icon on any property to save it here for quick access
                later.</p>
              <a href="{{ route('property.search') }}" class="btn btn-primary btn-lg fav-empty-cta">
                <i class="ti tabler-search me-1"></i> Browse Properties
              </a>
            </div>
          </div>
        @endforelse
      </div>

      @if($properties->hasPages())
        <div class="mt-3">
          {!! $properties->withQueryString()->links('pagination::bootstrap-5') !!}
        </div>
      @endif

      @include('_partials._modals.message_to_owner')
      @include('_partials._modals.call_to_owner')

    </div>
  </section>

@endsection

@push('styles')
  <style>
    /* ---- Page-only styles: hero header + empty state (not card styles) ---- */
    .fav-hero {
      isolation: isolate;
    }

    .fav-hero-glow {
      position: absolute;
      border-radius: 50%;
      filter: blur(70px);
      opacity: .22;
      z-index: -1;
      pointer-events: none;
    }

    .fav-hero-glow-1 {
      width: 320px;
      height: 320px;
      top: -120px;
      left: -80px;
      background: var(--bs-primary);
    }

    .fav-hero-glow-2 {
      width: 260px;
      height: 260px;
      top: -60px;
      right: -60px;
      background: #e03344;
    }

    .fav-hero-icon {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--bs-primary), #8f92ff);
      color: #fff;
      font-size: 1.5rem;
      box-shadow: 0 8px 24px rgba(105, 108, 255, .35);
    }

    .fav-hero-title {
      font-weight: 700;
      letter-spacing: -.01em;
    }

    .fav-count-badge {
      font-size: .72rem;
      font-weight: 700;
      background: rgba(105, 108, 255, .12);
      color: var(--bs-primary);
      border-radius: 20px;
      padding: 3px 11px;
      line-height: 1.4;
    }

    /* ---- Empty state ---- */
    .fav-empty-state {
      max-width: 420px;
      padding: 56px 24px;
      border: 1px dashed rgba(105, 108, 255, .25);
      border-radius: 18px;
      background: linear-gradient(180deg, rgba(105, 108, 255, .04), rgba(105, 108, 255, 0));
    }

    .fav-empty-icon-wrap {
      width: 84px;
      height: 84px;
      margin: 0 auto;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(224, 51, 68, .08);
      color: #e03344;
      font-size: 2.1rem;
    }

    .fav-empty-cta {
      border-radius: 10px;
      box-shadow: 0 8px 20px rgba(105, 108, 255, .25);
    }
  </style>
@endpush

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      @if (session('success'))
      notify(@json(session('success')), 'success');
      @endif
      @if (session('error'))
      notify(@json(session('error')), 'error');
      @endif
    });

    document.getElementById('message_to_owner')?.addEventListener('show.bs.modal', function(e) {
      const btn = e.relatedTarget;
      document.getElementById('inquiryListingId').value = btn?.dataset?.listingId ?? '';
      document.getElementById('inquiryName').value = btn?.dataset?.name ?? '';
      document.getElementById('inquiryEmail').value = btn?.dataset?.email ?? '';
      document.getElementById('inquiryContact').value = btn?.dataset?.contact ?? '';
      document.getElementById('inquiryMessage').value = btn?.dataset?.message ?? '';

      const isExisting = btn?.dataset?.existing === '1';
      const modalTitle = document.getElementById('mailForPropertyLabel');
      const submitBtn = document.getElementById('inquirySubmitBtn');
      if (modalTitle) modalTitle.textContent = isExisting ? 'Update Your Inquiry' : 'Contact Owner';
      if (submitBtn) submitBtn.textContent = isExisting ? 'Update Inquiry' : 'Send Inquiry';
    });

    document.getElementById('callForProperty')?.addEventListener('show.bs.modal', function(e) {
      const btn = e.relatedTarget;
      const contact = btn?.dataset?.contact ?? '';
      document.getElementById('callModalNumber').textContent = contact;
      document.getElementById('callModalNumber').href = 'tel:' + contact;
      document.getElementById('callModalBtn').href = 'tel:' + contact;
      document.getElementById('copyFeedback').classList.add('d-none');
      document.getElementById('copyIcon').className = 'ti tabler-copy text-success';
    });

    function copyContact(numberId, feedbackId, iconId) {
      const num = document.getElementById(numberId).textContent;
      navigator.clipboard.writeText(num).then(() => {
        document.getElementById(feedbackId).classList.remove('d-none');
        document.getElementById(iconId).className = 'ti tabler-check text-success';
        setTimeout(() => {
          document.getElementById(feedbackId).classList.add('d-none');
          document.getElementById(iconId).className = 'ti tabler-copy text-success';
        }, 2500);
      });
    }


    function toggleFavourite(btn) {
      const type = btn.dataset.type;
      const uuid = btn.dataset.uuid;
      const cardId = btn.dataset.cardId;
      const card = document.getElementById(cardId);

      if (card) {
        card.style.transition = 'opacity .2s ease';
        card.style.opacity = '0.3';
      }
      btn.disabled = true;
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
            if (!data.favourited) {
              card?.remove();
              decrementFavCount();
              notify('Removed from favourites', 'success');
            } else if (card) {
              card.style.opacity = '1';
            }
          }
        )
        .catch(err => {
          console.error('Favourite toggle failed:', err);
          if (card) card.style.opacity = '1';
        })
        .finally(() => {
          btn.disabled = false;
        });
    }

    function decrementFavCount() {
      const badge = document.getElementById('favCountBadge');
      const label = document.getElementById('favCountLabel');
      const header = document.getElementById('favHeader');

      let count = badge ? (parseInt(badge.textContent.replace(/,/g, ''), 10) || 0) : 0;
      count = Math.max(0, count - 1);

      if (count === 0) {
        if (header) {
          header.classList.remove('d-flex');
          header.style.display = 'none';
        }
        showFavEmptyState();
      } else {
        if (badge) badge.textContent = count.toLocaleString();
        if (label) label.textContent = count === 1 ? 'Saved Property' : 'Saved Properties';
      }
    }

    function showFavEmptyState() {
      const grid = document.getElementById('viewGrid');
      if (!grid) return;
      grid.innerHTML = `
        <div class="col-12">
          <div class="fav-empty-state text-center mx-auto">
            <div class="fav-empty-icon-wrap mb-4">
              <i class="ti tabler-heart-off"></i>
            </div>
            <h5 class="fw-semibold mb-2">No favourites yet</h5>
            <p class="text-muted mb-4">Tap the heart icon on any property to save it here for quick access later.</p>
            <a href="{{ route('property.search') }}" class="btn btn-primary btn-lg fav-empty-cta">
              <i class="ti tabler-search me-1"></i> Browse Properties
            </a>
          </div>
        </div>
      `;
    }
  </script>
@endpush
