@php
  // Works on both landing page (no $searchInputs) and properties page (passed from controller)
  $s = $searchInputs ?? [];
  $listingType = $s['listing_type'] ?? 'sell';
@endphp

<div class="prop-search mt-8">
  <div class="d-flex justify-content-center">
    <div class="prop-intent-tabs">
      <button class="prop-itab {{ $listingType !== 'rent' ? 'active' : '' }}" data-type="buy"
              onclick="propIntent(this)">BUY
      </button>
      <button class="prop-itab {{ $listingType === 'rent' ? 'active' : '' }}" data-type="rent"
              onclick="propIntent(this)">RENT
      </button>
    </div>
  </div>

  <form method="POST" action="{{ route('property.search') }}" id="propSearchForm">
    @csrf
    <input type="hidden" name="listing_type" id="propListingTypeInput" value="{{ $listingType }}">

    <div class="prop-card">
      <div class="prop-row">
        <div class="prop-field prop-field-society">
          <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Society</span>
          <input type="text" name="society" id="propSocietyInput" class="w-100" placeholder="DHA, Bahria Town…"
                 value="{{ $s['society'] ?? '' }}">
        </div>
        <div class="prop-field prop-field-society">
          <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Property Name</span>
          <input type="text" name="property_name" id="propNameInput" class="w-100" placeholder="Property Name …"
                 value="{{ $s['property_name'] ?? '' }}">
        </div>
        <div class="prop-field flex-grow-1">
          <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Location</span>
          <input type="text" id="propLocationInput" class="w-100" placeholder="Area, street or landmark…"
                 name="location" autocomplete="off" value="{{ $s['location'] ?? '' }}">
        </div>
        <button type="submit"
                class="btn btn-primary btn-sm px-4 fw-bold d-flex align-items-center gap-1 rounded-0 fs-6">
          <i class="ti tabler-search"></i> FIND
        </button>
      </div>

      <div id="propFilters" class="prop-filters-collapse">
        <div class="prop-row prop-row-filters">
          <div class="prop-field">
            <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Category</span>
            <select class="form-select" id="propCat" name="category" onchange="propSwitchCat(this.value)">
              <option value="">All</option>
              <option value="residential" {{ ($s['category'] ?? '') === 'residential' ? 'selected' : '' }}>Residential
              </option>
              <option value="commercial" {{ ($s['category'] ?? '') === 'commercial'  ? 'selected' : '' }}>Commercial
              </option>
            </select>
          </div>
          <div class="prop-field">
            <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Property Type</span>
            <select class="form-select" id="propType" name="type" onchange="propSwitchType(this.value)"></select>
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
                <option value="{{ $val }}" {{ ($s['country'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="prop-row prop-row-filters">
          <div class="prop-field prop-field-price">
            <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Price (PKR)</span>
            <div class="prop-range">
              <input type="number" placeholder="Min" min="0" name="min_price" value="{{ $s['min_price'] ?? '' }}">
              <span class="range-sep">to</span>
              <input type="number" placeholder="Max" min="0" name="max_price" value="{{ $s['max_price'] ?? '' }}">
            </div>
          </div>
          <div class="prop-field prop-field-area">
            <span class="d-block text-uppercase fw-bold text-secondary mb-1 search_label">Area (Square Feet's)</span>
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
              @foreach(['Any', '1', '2', '3', '4', '5+'] as $opt)
                <option {{ ($s['floors'] ?? '') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <div class="prop-links">
        <a href="#" id="propToggleFilters" onclick="propToggleFilters(event)">
          <i class="ti tabler-chevron-down me-1" id="propToggleIcon"></i>See More
        </a>
        <span>|</span>
        <div class="dropdown d-inline-block">
          <a href="#" class="dropdown-toggle text-decoration-none" data-bs-toggle="dropdown">Change Currency</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" onclick="propSetCurrency('PKR',event)">Pakistani Rupee (PKR)</a></li>
            <li><a class="dropdown-item" href="#" onclick="propSetCurrency('USD',event)">US Dollar (USD)</a></li>
            <li><a class="dropdown-item" href="#" onclick="propSetCurrency('AED',event)">UAE Dirham (AED)</a></li>
            <li><a class="dropdown-item" href="#" onclick="propSetCurrency('SAR',event)">Saudi Riyal (SAR)</a></li>
            <li><a class="dropdown-item" href="#" onclick="propSetCurrency('GBP',event)">British Pound (GBP)</a></li>
          </ul>
        </div>
        <span>|</span>
        <div class="dropdown d-inline-block">
          <a href="#" class="dropdown-toggle text-decoration-none" data-bs-toggle="dropdown">Change Area Unit</a>
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
    </div>
  </form>
</div>
