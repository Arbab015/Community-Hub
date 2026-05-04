// common_property.js — shared across create and edit pages

//  Property type options
const PROPERTY_TYPES = {
  residential: ['Plot', 'House', 'Other'],
  commercial:  ['Plot', 'Building', 'Plaza', 'Other'],
  other:       ['Plot', 'Mosque', 'Temple', 'Hospital', 'Park', 'School', 'Govt-Office', 'Other']
};

// Fill type <select> based on category
window.fillTypes = function (selectEl, category, oldVal = '') {
  selectEl.innerHTML = '<option value="" disabled selected>Select Type</option>';
  (PROPERTY_TYPES[category] ?? []).forEach(t => {
    const o = document.createElement('option');
    o.value = t.toLowerCase();
    o.text  = t;
    if (o.value === oldVal.toLowerCase()) o.selected = true;
    selectEl.appendChild(o);
  });
};

//  Construction status radio styling
function styleConstructionRadios() {
  const constructed = document.getElementById('cons_constructed')?.checked;
  const in_progress = document.getElementById('cons_in_progress')?.checked;
  const lc = document.getElementById('label_constructed');
  const lp = document.getElementById('label_progress');
  const ln = document.getElementById('label_pending');
  if (!lc || !lp || !ln) return;
  lc.className = lp.className = ln.className = 'const_labels';
  if (constructed)      lc.classList.add('border-success', 'bg-success', 'bg-opacity-10');
  else if (in_progress) lp.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
  else                  ln.classList.add('border-warning',  'bg-warning',  'bg-opacity-10');
}
styleConstructionRadios();

document.querySelectorAll('input[name="const_status"]').forEach(r => {
  r.addEventListener('change', () => {
    styleConstructionRadios();
    // show/hide Construction tab on create page only
    const constTab    = document.getElementById('const_tab');
    const constructed = document.getElementById('cons_constructed')?.checked;
    const in_progress = document.getElementById('cons_in_progress')?.checked;
    if (constTab) constTab.classList.toggle('d-none', !constructed && !in_progress);
  });
});

// Hide construction wrapper when type = plot
document.getElementById('type')?.addEventListener('change', function () {
  document.getElementById('constructed_wrapper')?.classList.toggle('d-none', this.value === 'plot');
});

//  Template cloning
window.cloneTpl = function (id, replacements) {
  const tpl  = document.getElementById(id);
  const node = tpl.content.cloneNode(true);
  const div  = document.createElement('div');
  div.appendChild(node);
  let html = div.innerHTML;
  Object.entries(replacements).forEach(([k, v]) => { html = html.replaceAll(k, v); });
  div.innerHTML = html;
  return div.firstElementChild;
};

//  Add a dimension row
// prefix = field prefix up to room/floor, e.g. "floors[0][rooms][1]"
window.addDimRow = function ($dimBlock, prefix) {
  const idx  = $dimBlock.find('.dim-rows .dimension-row').length;
  const node = cloneTpl('tpl-dim-row', { '__PREFIX__': prefix, '__DIM__': idx });
  $dimBlock.find('.dim-rows').append(node);
};

//  Add a room
window.addRoom = function ($container, prefix, roomIdx) {
  const node  = cloneTpl('tpl-room', { '__PREFIX__': prefix, '__ROOM__': roomIdx });
  const $room = $(node);
  addDimRow($room.find('.dim-block'), prefix + '[rooms][' + roomIdx + ']');
  $container.append($room);
};

//  Add a unit
window.addUnit = function ($container, floorPrefix, unitIdx) {
  const node = cloneTpl('tpl-unit', { '__PREFIX__': floorPrefix, '__UNIT__': unitIdx });
  $container.append(node);
};

// Dimension add/remove — call from any delegated listener
window.handleDimClicks = function (e) {
  if (e.target.closest('.btn-add-dim')) {
    e.stopPropagation();
    const btn      = e.target.closest('.btn-add-dim');
    const block    = btn.closest('.dim-block');
    const existing = block.querySelector('[name*="[dimensions]"]');
    if (!existing) return;
    // name is like: floors[0][rooms][0][dimensions][0][name]
    // everything before "[dimensions]" is the prefix we need
    const prefix = existing.name.split('[dimensions]')[0];
    addDimRow($(block), prefix);
    return;
  }
  if (e.target.closest('.btn-remove-dim')) {
    e.stopPropagation();
    const rows = e.target.closest('.dim-rows');
    if (rows.querySelectorAll('.dimension-row').length > 1) {
      e.target.closest('.dimension-row').remove();
    }
  }
};

// Dimension uniqueness validation

function validateDimensions(form) {
  form.querySelectorAll('.dim-name-error').forEach(el => el.remove());
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  let valid = true;
  const seen = {};
  form.querySelectorAll('input[name*="[name]"], input[name="name"]').forEach(input => {
    const val = input.value.trim().toLowerCase();
    if (!val) return;
    if (seen[val]) {
      [seen[val], input].forEach(el => {
        el.classList.add('is-invalid');
        const err = document.createElement('div');
        err.className = 'text-danger small mt-1 dim-name-error';
        err.innerHTML = '<i class="ti tabler-alert-circle me-1"></i>Dimension name must be unique.';
        (el.closest('.input-group') ?? el).insertAdjacentElement('afterend', err);
      });
      valid = false;
    } else {
      seen[val] = input;
    }
  });
  return valid;
}

// Property basic form submit (create page)
document.getElementById('property_form')?.addEventListener('submit', function (e) {
  const dimensions = document.querySelectorAll('.dimensions');
  if (dimensions.length === 1) {
    notify('Dimensions must be more than 1 side.', 'error');
    e.preventDefault();
    return;
  }
  if (!validateDimensions(this)) {
    notify('Please fix the errors before submitting.', 'error');
    e.preventDefault();
  }
});

//  Edit dimensions form submit (edit modal)
document.getElementById('edit_dimensions_form')?.addEventListener('submit', function (e) {
  if (!validateDimensions(this)) {
    notify('Please fix the errors before submitting.', 'error');
    e.preventDefault();
  }
});

// ─── Construction form validation (used on both create & edit pages) ──────────
window.validateConstructionForm = function (form, e) {
  form.querySelectorAll('.dim-name-error, .floor-type-error').forEach(el => el.remove());
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

  let valid = true;

  const markError = (el, msg, cls = 'dim-name-error') => {
    el.classList.add('is-invalid');
    const err = document.createElement('div');
    err.className = `text-danger small mt-2 ${cls}`;
    err.innerHTML = `<i class="ti tabler-alert-circle me-1"></i>${msg}`;
    (el.closest('.input-group') ?? el).insertAdjacentElement('afterend', err);
    valid = false;
  };

  const checkUnique = (selector, getValue, msg, cls) => {
    const seen = {};
    form.querySelectorAll(selector).forEach(el => {
      const val = getValue(el).trim().toLowerCase();
      if (!val) return;
      seen[val] ? (markError(seen[val], msg, cls), markError(el, msg, cls)) : seen[val] = el;
    });
  };

  // required selects
  form.querySelectorAll('select[name*="floor_type"], select[name*="room_type"]').forEach(sel => {
    if (!sel.value) markError(sel, 'This field is required.', 'floor-type-error');
  });

  // unique floor types
  checkUnique('.floor-item select[name*="[floor_type]"]', el => el.value,
    'Floor type must be unique within the same property.', 'floor-type-error');

  // unique dimension names per dim-block
  form.querySelectorAll('.dim-block').forEach(block => {
    const seen = {};
    block.querySelectorAll('.dim-rows input[name*="[name]"]').forEach(input => {
      const val = input.value.trim().toLowerCase();
      if (!val) return;
      seen[val]
        ? (markError(seen[val], 'Dimension name must be unique.'), markError(input, 'Dimension name must be unique.'))
        : seen[val] = input;
    });
  });

  // unique unit names
  checkUnique('.unit-item input[name*="[unit_name]"]', el => el.value,
    'Unit name must be unique within the same property.');

  // each room must have more than 1 dimension
  form.querySelectorAll('.room-item').forEach(room => {
    if (room.querySelectorAll('.dim-block .dim-rows .dimension-row').length < 2) {
      const err = document.createElement('div');
      err.className = 'text-danger small mt-2 dim-name-error';
      err.innerHTML = '<i class="ti tabler-alert-circle me-1"></i>Room dimensions must have more than 1 side.';
      room.querySelector('.btn-add-dim').insertAdjacentElement('beforebegin', err);
      valid = false;
    }
  });

  // floor must have at least one room or unit
  form.querySelectorAll('.floor-item').forEach(floor => {
    const hasUnits = floor.querySelectorAll('.unit-item').length > 0;
    const hasRooms = floor.querySelectorAll('.floor-rooms-container .room-item').length > 0;
    if (!hasUnits && !hasRooms) {
      const err = document.createElement('div');
      err.className = 'text-danger small mt-2 dim-name-error';
      err.innerHTML = '<i class="ti tabler-alert-circle me-1"></i>Floor must have at least one unit or room.';
      floor.querySelector('.btn-add-floor-room').insertAdjacentElement('beforebegin', err);
      valid = false;
    }
  });

  // unit must have at least one room
  form.querySelectorAll('.unit-item').forEach(unit => {
    if (unit.querySelectorAll('.unit-rooms-container .room-item').length === 0) {
      const err = document.createElement('div');
      err.className = 'text-danger small mt-2 dim-name-error';
      err.innerHTML = '<i class="ti tabler-alert-circle me-1"></i>Each unit must have at least one room.';
      unit.querySelector('.btn-add-unit-room').insertAdjacentElement('beforebegin', err);
      valid = false;
    }
  });

  if (!valid) {
    notify('Please fix the errors before submitting.', 'error');
    e.preventDefault();
  }
};
