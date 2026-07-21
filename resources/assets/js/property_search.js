let currentListingType = 'buy';

document.addEventListener('DOMContentLoaded', function() {

  const locationInput = document.getElementById('location');
  const societyInput = document.getElementById('society');
  const propNameInput = document.getElementById('propNameInput');
  const filtersPanel = document.getElementById('filters');
  const catSelect = document.getElementById('cat');
  const typeSelect = document.getElementById('prop_type');

  function openFilters() {
    if (filtersPanel) {
      filtersPanel.classList.add('prop-open');
      const link = document.getElementById('propToggleFilters');
      if (link) link.innerHTML = '<i class="ti tabler-chevron-up me-1"></i>See Less';
    }
  }

  if (locationInput) {
    locationInput.addEventListener('click', openFilters);
    locationInput.addEventListener('focus', openFilters);

  }
  if (societyInput) {
    societyInput.addEventListener('click', openFilters);
    societyInput.addEventListener('focus', openFilters);
  }
  if (propNameInput) {
    propNameInput.addEventListener('click', openFilters);
    propNameInput.addEventListener('focus', openFilters);
  }

  if (catSelect) catSelect.addEventListener('change', function() {
    propSwitchCat(this.value);
  });
  if (typeSelect) typeSelect.addEventListener('change', function() {
    propSwitchType(this.value);
  });

  const defaults = window.propSearchDefaults || {};

  // Restore intent tab (buy/rent)
  if (defaults.listing_type === 'rent') {
    currentListingType = 'rent';
    document.querySelectorAll('.prop-itab').forEach(b => {
      b.classList.toggle('active', b.getAttribute('data-type') === 'rent');
    });
  } else {
    currentListingType = 'buy';
  }

  // Restore category then type (type dropdown is populated by propSwitchCat)
  const savedCat = defaults.category || (catSelect ? catSelect.value : '');
  const savedType = defaults.type || '';

  // FIX (bug 3): actually set the category select's value, not just feed it into propSwitchCat
  if (catSelect && savedCat) {
    catSelect.value = savedCat;
  }

  propSwitchCat(savedCat || 'all');

  if (savedType) {
    setTimeout(() => {
      if (typeSelect) {
        typeSelect.value = savedType;
        propSwitchType(savedType);
      }
    }, 50);
  }

// Restore unit type (only relevant when category is commercial)
  const unitTypeSelect = document.getElementById('unit_type');
  if (unitTypeSelect && defaults.unit_type) {
    unitTypeSelect.value = defaults.unit_type;
    document.querySelector('.prop-floors')?.classList.add('d-none');
  }
  if (unitTypeSelect) {
    unitTypeSelect.addEventListener('change', function() {
      const propFloors = document.querySelector('.prop-floors');
      const floorsSelect = document.querySelector('select[name="floors"]');
      if (this.value) {
        if (floorsSelect) floorsSelect.value = 'All';
        if (propFloors) propFloors.classList.add('d-none');
      } else if (propFloors) {
        propFloors.classList.remove('d-none');
      }
    });
  }

  // Restore rooms / floors
  const roomsSelect = document.querySelector('select[name="rooms"]');
  const floorsSelect = document.querySelector('select[name="floors"]');
  if (roomsSelect && defaults.rooms) roomsSelect.value = defaults.rooms;
  if (floorsSelect && defaults.floors) floorsSelect.value = defaults.floors;

  const filterKeys = ['society', 'property_name', 'location', 'category', 'type', 'unit_type', 'city', 'country', 'min_price', 'max_price', 'min_area', 'max_area', 'rooms', 'floors'];
  const hasFilter = filterKeys.some(k => defaults[k] && defaults[k] !== '');

  if (hasFilter && filtersPanel) {
    openFilters();
  }

  // Restore currency & area labels
  const savedCurrency = document.getElementById('propCurrencyInput')?.value || 'PKR';
  const savedAreaUnit = document.getElementById('propAreaUnitInput')?.value || 'sqft';

  const priceLabel = document.querySelector('.prop-field-price .search_label');
  if (priceLabel) priceLabel.textContent = `PRICE (${savedCurrency})`;

  const areaFieldLabel = document.querySelector('.prop-field-area .search_label');
  if (areaFieldLabel) {
    const fieldLabelMap = { sqft: 'AREA (SQUARE FEET\'S)', marla: 'AREA (MARLA)', kanal: 'AREA (KANAL)' };
    areaFieldLabel.textContent = fieldLabelMap[savedAreaUnit] || 'AREA (SQUARE FEET\'S)';
  }
});

function propIntent(btn) {
  const type = btn.getAttribute('data-type');
  document.querySelectorAll('.prop-itab').forEach(b => {
    b.classList.toggle('active', b.getAttribute('data-type') === type);
  });
  currentListingType = type;
  const hiddenInput = document.getElementById('propListingTypeInput');
  if (hiddenInput) hiddenInput.value = currentListingType === 'rent' ? 'rent' : 'sell';
  // FIX (bug 1): the category select's real id is "cat", not "propCat"
  propSwitchCat(document.getElementById('cat')?.value || 'residential');
}

// Types that exist in commercial and can have units
const UNIT_TYPES_FOR = ['any', 'building', 'plaza', 'office', 'other'];

function propSwitchCat(val) {
  const typeSelect = document.getElementById('prop_type');
  if (!typeSelect) return;

  const types = {
    all: ['Any', 'plot', 'house', 'building', 'plaza', 'office', 'other'],
    residential: ['Any', 'plot', 'house', 'other'],
    commercial: ['Any', 'plot', 'building', 'plaza', 'other']
  };

  const key = types[val] ? val : 'all';
  let list = [...types[key]];
  if (currentListingType === 'rent') list = list.filter(t => t.toLowerCase() !== 'plot');

  typeSelect.innerHTML = list.map(t => {
    const value = t === 'Any' ? '' : t;
    const label = t.charAt(0).toUpperCase() + t.slice(1);
    return `<option value="${value}">${label}</option>`;
  }).join('');

  // Always default to first option (Any)
  typeSelect.value = '';

  propSwitchType(typeSelect.value);
}

function propSwitchType(val) {
  const propFloors = document.querySelector('.prop-floors');
  const propRooms = document.querySelector('.prop-rooms');
  const unitTypeField = document.querySelector('.prop-unit-type');
  const unitTypeSelect = document.getElementById('unit_type');
  const cat = document.getElementById('cat')?.value;

  const v = (val || 'any').toLowerCase();

// Floors/rooms
  if (propFloors && propRooms) {
    const hide = ['plot'].includes(v);
    propFloors.classList.toggle('d-none', hide);
    propRooms.classList.toggle('d-none', hide);

    const roomsSelect = document.querySelector('select[name="rooms"]');
    const floorsSelect = document.querySelector('select[name="floors"]');
    if (hide) {
      if (roomsSelect) roomsSelect.value = 'All';
      if (floorsSelect) floorsSelect.value = 'All';
    }
  }

  // Show unit type if: category is commercial OR selected type can have units
  const showUnitType = cat === 'commercial' || UNIT_TYPES_FOR.includes(v);
  if (unitTypeField) unitTypeField.classList.toggle('d-none', !showUnitType);
  if (unitTypeSelect) {
    unitTypeSelect.disabled = !showUnitType;
    if (!showUnitType) unitTypeSelect.value = '';
  }
}


function propToggleFilters(e) {
  e.preventDefault();
  // FIX (bug 2): the panel's real id is "filters", not "propFilters"
  const filters = document.getElementById('filters');
  const link = document.getElementById('propToggleFilters');
  if (!filters) return;
  const isOpen = filters.classList.contains('prop-open');
  filters.classList.toggle('prop-open');
  if (link) link.innerHTML = isOpen
    ? '<i class="ti tabler-chevron-down me-1"></i>See More'
    : '<i class="ti tabler-chevron-up me-1"></i>See Less';
}

function propSetCurrency(code, e) {
  e.preventDefault();
  document.getElementById('propCurrencyInput').value = code;
  const priceLabel = document.querySelector('.prop-field-price .search_label');
  if (priceLabel) priceLabel.textContent = `PRICE (${code})`;
}

function propSetArea(unit, label, e) {
  e.preventDefault();
  document.getElementById('propAreaUnitInput').value = unit;
  const areaLabel = document.querySelector('.prop-field-area .search_label');
  if (areaLabel) {
    const labelMap = { sqft: 'AREA (SQUARE FEET\'S)', marla: 'AREA (MARLA)', kanal: 'AREA (KANAL)' };
    areaLabel.textContent = labelMap[unit] || 'AREA';
  }
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('.prop-links')) {
    document.getElementById('propCurrencyPicker')?.classList.remove('show');
    document.getElementById('propAreaPicker')?.classList.remove('show');
  }
});

window.propIntent = propIntent;
window.propSwitchCat = propSwitchCat;
window.propSwitchType = propSwitchType;
window.propToggleFilters = propToggleFilters;
window.propSetCurrency = propSetCurrency;
window.propSetArea = propSetArea;
