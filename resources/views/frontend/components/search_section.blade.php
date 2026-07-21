@php
  $s = $searchInputs ?? [];
  $listingType = $s['listing_type'] ?? 'sell';
@endphp

<div class="prop-search mt-8">
  <div class="d-flex justify-content-center">
    <div class="prop-intent-tabs">
      <button class="prop-itab {{ $listingType !== 'rent' ? 'active' : '' }}" data-type="buy"
              onclick="propIntent(this); openPropSearchPanel();">BUY
      </button>
      <button class="prop-itab {{ $listingType === 'rent' ? 'active' : '' }}" data-type="rent"
              onclick="propIntent(this); openPropSearchPanel();">RENT
      </button>
    </div>
  </div>

  <div id="propSearchPanelAnchor"></div>

  <div class="prop-search-backdrop" id="propSearchBackdrop" onclick="closePropSearchPanel()"></div>

  <div class="prop-search-panel" id="propSearchPanel">
    <div class="prop-search-panel-header d-md-none">
      <h5 class="mb-0 fw-bold">Filters</h5>
      <button type="button" class="prop-search-close" aria-label="Close" onclick="closePropSearchPanel()">
        <i class="ti tabler-x"></i>
      </button>
    </div>

    <div class="prop-search-panel-body">
      <div class="d-md-none d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
        <span class="fw-semibold">I want to</span>
        <div class="prop-intent-tabs">
          <button class="prop-itab {{ $listingType !== 'rent' ? 'active' : '' }}" data-type="buy"
                  onclick="propIntent(this)">Buy
          </button>
          <button class="prop-itab {{ $listingType === 'rent' ? 'active' : '' }}" data-type="rent"
                  onclick="propIntent(this)">Rent
          </button>
        </div>
      </div>

      <form method="POST" action="{{ route('property.search') }}" id="propSearchForm">
        @csrf
        <input type="hidden" name="listing_type" id="propListingTypeInput" value="{{ $listingType }}">

        <div class="prop-card">
          <div class="prop-row d-flex flex-column flex-md-row gap-3">
            <div class="prop-field prop-field-society">
              <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Society</span>
              <input type="text" name="society" id="society" class="w-100" placeholder="DHA, Bahria Town…"
                     value="{{ $s['society'] ?? '' }}">
            </div>
            <div class="prop-field prop-field-society">
              <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Property Name</span>
              <input type="text" name="property_name" id="propNameInput" class="w-100" placeholder="Property Name …"
                     value="{{ $s['property_name'] ?? '' }}">
            </div>
            <div class="prop-field flex-grow-1">
              <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Location</span>
              <input type="text" id="location" class="w-100" placeholder="Area, street or landmark…"
                     name="location" autocomplete="off" value="{{ $s['location'] ?? '' }}">
            </div>
            <button type="submit"
                    class="btn btn-primary btn-sm px-4 fw-bold d-none d-md-inline-flex align-items-center gap-1 rounded-0 fs-6">
              <i class="ti tabler-search"></i> FIND
            </button>
          </div>

          <div id="filters" class="prop-filters-collapse">
            <div class="prop-row prop-row-filters d-flex flex-column flex-md-row gap-3">
              <div class="prop-field">
                <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Category</span>
                <select class="form-select" id="cat" name="category" onchange="propSwitchCat(this.value)">
                  <option value="">All</option>
                  <option value="residential" {{ ($s['category'] ?? '') === 'residential' ? 'selected' : '' }}>
                    Residential
                  </option>
                  <option value="commercial" {{ ($s['category'] ?? '') === 'commercial'  ? 'selected' : '' }}>Commercial
                  </option>
                </select>
              </div>
              <div class="prop-field">
                <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Property Type</span>
                <select class="form-select" id="prop_type" name="type" onchange="propSwitchType(this.value)"></select>
              </div>

              <div class="prop-field prop-unit-type d-none">
                <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Unit Type</span>
                <select class="form-select" id="unit_type" name="unit_type">
                  <option value="">Any</option>
                  @foreach($unitTypes ?? [] as $unitType)
                    <option
                      value="{{ $unitType->id }}" {{ ($s['unit_type'] ?? '') == $unitType->id ? 'selected' : '' }}>
                      {{ ucwords($unitType->title)  }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="prop-field">
                <label class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">City</label>
                <input type="text" name="city" id="city" class="w-100" placeholder="Islamabad, Lahore..."
                       value="{{ $s['city'] ?? '' }}">
              </div>
              <div class="prop-field">
                <label class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Country</label>
                <select id="select2Basic" class="select2 form-select" name="country" data-allow-clear="true">
                  <option value="">All</option>
                  @foreach(['pakistan' => 'Pakistan', 'uae' => 'UAE', 'saudi arabia' => 'Saudi Arabia', 'uk' => 'UK', 'usa' => 'USA'] as $val => $label)
                    <option
                      value="{{ $val }}" {{ ($s['country'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="prop-row prop-row-filters d-flex flex-column flex-md-row gap-3">
              <div class="prop-field prop-field-price">
                <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Price (PKR)</span>
                <div class="prop-range">
                  <input type="number" placeholder="Min" min="0" name="min_price" value="{{ $s['min_price'] ?? '' }}">
                  <span class="range-sep">to</span>
                  <input type="number" placeholder="Max" min="0" name="max_price" value="{{ $s['max_price'] ?? '' }}">
                </div>
              </div>
              <div class="prop-field prop-field-area">
                <span
                  class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Area (Square Feet's)</span>
                <div class="prop-range">
                  <input type="number" placeholder="Min" min="0" name="min_area" value="{{ $s['min_area'] ?? '' }}">
                  <span class="range-sep">to</span>
                  <input type="number" placeholder="Max" min="0" name="max_area" value="{{ $s['max_area'] ?? '' }}">
                </div>
              </div>
              <div class="prop-field prop-rooms d-none">
                <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Rooms</span>
                <select class="form-select" name="rooms">
                  @foreach(['All', '1', '2', '3', '4', '5+'] as $opt)
                    <option {{ ($s['rooms'] ?? '') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                  @endforeach
                </select>
              </div>
              <div class="prop-field prop-floors d-none">
                <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Floors</span>
                <select class="form-select" name="floors">
                  @foreach(['All', '1', '2', '3', '4', '5+'] as $opt)
                    <option {{ ($s['floors'] ?? '') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>

          <div class="prop-links">
            <span class="d-none d-md-inline-flex align-items-center gap-2">
              <a href="#" id="propToggleFilters" onclick="propToggleFilters(event)">
                <i class="ti tabler-chevron-down me-1" id="propToggleIcon"></i>See More
              </a>
              <span>|</span>
            </span>
            <div class="dropdown d-inline-block">
              <a href="#" class="dropdown-toggle text-decoration-none" data-bs-toggle="dropdown"
                 data-bs-popper-config='{"strategy":"fixed"}'>Change Currency</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="propSetCurrency('PKR',event)">Pakistani Rupee (PKR)</a>
                </li>
                <li><a class="dropdown-item" href="#" onclick="propSetCurrency('USD',event)">US Dollar (USD)</a></li>
                <li><a class="dropdown-item" href="#" onclick="propSetCurrency('AED',event)">UAE Dirham (AED)</a></li>
                <li><a class="dropdown-item" href="#" onclick="propSetCurrency('SAR',event)">Saudi Riyal (SAR)</a></li>
                </li>
              </ul>
            </div>
            <span>|</span>
            <div class="dropdown d-inline-block">
              <a href="#" class="dropdown-toggle text-decoration-none" data-bs-toggle="dropdown"
                 data-bs-popper-config='{"strategy":"fixed"}'>Change Area Unit</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="propSetArea('sqft','Sqft',event)">Square Feet</a></li>
                <li><a class="dropdown-item" href="#" onclick="propSetArea('marla','Marla',event)">Marla</a></li>
                <li><a class="dropdown-item" href="#" onclick="propSetArea('kanal','Kanal',event)">Kanal</a></li>
              </ul>
            </div>
            <span>|</span>
            <a href="{{ route('landing-page') }}" class="text-danger">Reset Search</a>
          </div>

          <input type="hidden" name="currency" id="propCurrencyInput" value="{{ $s['currency']  ?? 'PKR' }}">
          <input type="hidden" name="area_unit" id="propAreaUnitInput" value="{{ $s['area_unit'] ?? 'sqft' }}">

          <button type="submit"
                  class="btn btn-primary w-100 fw-bold d-flex d-md-none align-items-center justify-content-center gap-1 rounded-0 fs-6 mt-3">
            <i class="ti tabler-search"></i> FIND
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('styles')
  <style>

    .prop-search-backdrop {
      display: none;
    }

    @media (max-width: 767.98px) {
      .prop-search-panel {
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100%;
        background: #fff;
        z-index: 99999;
        transform: translateX(100%);
        transition: transform .3s ease;
        overflow-y: auto;
      }

      .prop-search-panel.show {
        transform: translateX(0);
      }

      .prop-search-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #eee;
        position: sticky;
        top: 0;
        background: #fff;
      }

      .prop-search-close {
        border: none;
        background: #f1f2f7;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        color: #4b4f5c;
      }

      .prop-search-panel-body {
        padding: 1.25rem;
      }

      .prop-search-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .5);
        z-index: 99998;
      }

      .prop-search-backdrop.show {
        display: block;
      }

      body.prop-search-open {
        overflow: hidden;
      }

      #filters.prop-filters-collapse {
        max-height: none !important;
        height: auto !important;
        opacity: 1 !important;
        overflow: visible !important;
        visibility: visible !important;
        display: block !important;
      }


      .prop-search-panel .prop-field {
        width: 100%;
      }

      .prop-search-panel input,
      .prop-search-panel select {
        min-height: 44px;
      }

      .prop-search-panel button[type="submit"].w-100 {
        position: sticky;
        bottom: 0;
      }
    }
  </style>
@endpush

@push('scripts')
  <script>

    document.addEventListener('DOMContentLoaded', function() {
      const panel = document.getElementById('propSearchPanel');
      const backdrop = document.getElementById('propSearchBackdrop');
      const anchor = document.getElementById('propSearchPanelAnchor');
      if (!panel || !backdrop || !anchor) return;

      function relocate() {
        const isMobile = window.innerWidth < 768;
        if (isMobile) {
          if (panel.parentNode !== document.body) document.body.appendChild(panel);
          if (backdrop.parentNode !== document.body) document.body.appendChild(backdrop);
        } else {
          if (panel.parentNode === document.body) {
            anchor.parentNode.insertBefore(panel, anchor.nextSibling);
          }
          if (backdrop.parentNode === document.body) {
            backdrop.remove();
            anchor.parentNode.insertBefore(backdrop, anchor);
          }
        }
      }

      relocate();

      let resizeTimer;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(relocate, 150);
      });
    });

    function openPropSearchPanel() {
      if (window.innerWidth >= 768) return; // desktop fields are already inline, nothing to open
      document.getElementById('propSearchPanel')?.classList.add('show');
      document.getElementById('propSearchBackdrop')?.classList.add('show');
      document.body.classList.add('prop-search-open');
    }

    function closePropSearchPanel() {
      document.getElementById('propSearchPanel')?.classList.remove('show');
      document.getElementById('propSearchBackdrop')?.classList.remove('show');
      document.body.classList.remove('prop-search-open');
    }

    window.openPropSearchPanel = openPropSearchPanel;
    window.closePropSearchPanel = closePropSearchPanel;
  </script>
@endpush
