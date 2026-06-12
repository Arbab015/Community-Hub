let currentListingType = 'buy';

document.addEventListener('DOMContentLoaded', function() {

  const locationInput = document.getElementById('propLocationInput');
  const societyInput = document.getElementById('propSocietyInput');
  const filtersPanel = document.getElementById('propFilters');
  const catSelect = document.getElementById('propCat');
  const typeSelect = document.getElementById('propType');

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

  propSwitchCat(savedCat || 'all');

  if (savedType) {
    setTimeout(() => {
      if (typeSelect) {
        typeSelect.value = savedType;
        propSwitchType(savedType);
      }
    }, 50);
  }

  // Restore rooms / floors
  const roomsSelect = document.querySelector('select[name="rooms"]');
  const floorsSelect = document.querySelector('select[name="floors"]');
  if (roomsSelect && defaults.rooms) roomsSelect.value = defaults.rooms;
  if (floorsSelect && defaults.floors) floorsSelect.value = defaults.floors;

  // Auto-open filters if any filter value was submitted (use propSearchDefaults, not URL params — POST request)
  const filterKeys = ['category', 'type', 'city', 'country', 'min_price', 'max_price', 'min_area', 'max_area', 'rooms', 'floors'];
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
  document.querySelectorAll('.prop-itab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentListingType = btn.getAttribute('data-type');
  const hiddenInput = document.getElementById('propListingTypeInput');
  if (hiddenInput) hiddenInput.value = currentListingType === 'rent' ? 'rent' : 'sell';
  propSwitchCat(document.getElementById('propCat')?.value || 'residential');
}

function propSwitchCat(val) {
  const typeSelect = document.getElementById('propType');
  if (!typeSelect) return;

  const types = {
    all: ['Any', 'plot', 'house', 'other', 'building', 'plaza', 'office', 'shop', 'apartment', 'studio', 'other'],
    residential: ['Any', 'plot', 'house', 'other'],
    commercial: ['Any', 'plot', 'building', 'plaza', 'office', 'shop', 'apartment', 'studio', 'other']
  };

  let list = [...(types[val] || types.residential)];
  if (currentListingType === 'rent') list = list.filter(t => t.toLowerCase() !== 'plot');

  typeSelect.innerHTML = list.map(t => {
    const label = t.charAt(0).toUpperCase() + t.slice(1).replace(/-/g, ' ');
    return `<option value="${t}">${label}</option>`;
  }).join('');

  propSwitchType(typeSelect.value);
}

function propSwitchType(val) {
  const propFloors = document.querySelector('.prop-floors');
  const propRooms = document.querySelector('.prop-rooms');
  if (!propFloors || !propRooms) return;

  if (['any', 'plot'].includes((val || '').toLowerCase())) {
    propFloors.classList.add('d-none');
    propRooms.classList.add('d-none');
  } else {
    propRooms.classList.remove('d-none');
    propFloors.classList.remove('d-none');
  }
}

function propToggleFilters(e) {
  e.preventDefault();
  const filters = document.getElementById('propFilters');
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
