// edit_construction.js — property details page: add floor, add/edit room, add/edit unit

let editIsResidential = window.editIsResidential ?? false;
let editFloorCount = 0;

// ─── Helpers

function ucFirst(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

function parseAmenities(raw) {
  if (Array.isArray(raw)) return raw.map(Number);
  try {
    return JSON.parse(raw ?? '[]').map(Number);
  } catch {
    return [];
  }
}

// ─── Dim row builder (flat names: dimensions[i][name/size/unit])


function makeDimRow(i, d) {
  const div = document.createElement('div');
  div.className = 'dimension-row row g-3 mb-2 align-items-end rounded-3 mx-0';
  div.innerHTML = `
    ${d?.id ? `<input type="hidden" name="dimensions[${i}][id]" value="${d.id}">` : ''}
    <div class="col-md-4">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-ruler text-muted"></i></span>
        <input type="text" name="dimensions[${i}][name]" value="${d?.name ?? ''}"
               required class="form-control border-start-0" placeholder="e.g. Length, Width, Front, Right">
      </div>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-number text-muted"></i></span>
        <input type="number" name="dimensions[${i}][size]" value="${d?.size ?? ''}"
               step="0.01" required class="form-control border-start-0" placeholder="e.g 40, 12.21">
      </div>
    </div>
    <div class="col-10 col-md-3">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
      <select name="dimensions[${i}][unit]" class="form-select" required>
        <option value="" disabled ${!d?.unit ? 'selected' : ''}>Select Unit</option>
        <option value="inch"  ${d?.unit === 'inch' ? 'selected' : ''}>Inch</option>
        <option value="feet"  ${d?.unit === 'feet' ? 'selected' : ''}>Feet</option>
        <option value="meter" ${d?.unit === 'meter' ? 'selected' : ''}>Meter</option>
        <option value="yard"  ${d?.unit === 'yard' ? 'selected' : ''}>Yard</option>
      </select>
    </div>
    <div class="col-2 col-md-1 ps-0 pt-4 mt-1">
      <button type="button" class="btn btn-remove-dim ps-3" title="Remove">
        <i class="ti tabler-x icon-lg text-danger"></i>
      </button>
    </div>`;
  return div;
}

function buildDimRows(dimBlock, dims) {
  const dimRowsEl = dimBlock.querySelector('.dim-rows');
  dimRowsEl.innerHTML = '';
  if (dims && dims.length) {
    dims.forEach((d, i) => dimRowsEl.appendChild(makeDimRow(i, d)));
  } else {
    dimRowsEl.appendChild(makeDimRow(0));
    dimRowsEl.appendChild(makeDimRow(1));
  }
}

// ─── Inline room card builder for Add Unit modal

function makeAddUnitRoomCard(roomIdx) {
  const roomTypes = window.roomTypesJson ?? [];
  const amenities = window.amenitiesJson ?? [];

  const card = document.createElement('div');
  card.className = 'um-room-card border rounded-3 p-3 mb-2';
  card.dataset.roomIdx = roomIdx;
  card.innerHTML = `
    <div class="d-flex align-items-center justify-content-between mb-2">
      <span class="small fw-semibold text-muted text-uppercase">Room ${roomIdx + 1}</span>
      <button type="button" class="btn btn-sm py-1 px-2 btn-remove-um-room rounded-pill btn-label-danger waves-effect" title="Remove room">
        <i class="ti tabler-trash me-1"></i> Remove
      </button>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-md-5">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Room Type</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="ti tabler-door text-muted"></i></span>
          <select name="rooms[${roomIdx}][room_type]" class="form-select border-start-0" required>
            <option value="" disabled selected>Select type</option>
            ${roomTypes.map(rt => `<option value="${rt.id}">${ucFirst(rt.title)}</option>`).join('')}
          </select>
        </div>
      </div>
      ${amenities.length ? `
      <div class="col-12 mt-2">
        <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Amenities</label>
        <div class="d-flex flex-wrap gap-2">
          ${amenities.map(a => `
            <label class="d-flex align-items-center gap-2 border rounded-3 px-2 py-1 user-select-none cursor-pointer">
              <input class="form-check-input mt-0" type="checkbox"
                     name="rooms[${roomIdx}][amenities][]" value="${a.id}">
              <span class="small">${ucFirst(a.title)}</span>
            </label>`).join('')}
        </div>
      </div>` : ''}
    </div>

    <label class="form-label fw-semibold small text-uppercase text-muted mb-1">
      Dimensions <span class="fw-normal">(min 2, max 8)</span>
    </label>
    <div class="dim-block" data-room-idx="${roomIdx}">
      <div class="dim-rows"></div>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-room-dim" style="font-size:.72rem;">
        <i class="ti tabler-plus me-1"></i> Add Dimension
      </button>
    </div>`;

  const dimRowsEl = card.querySelector('.dim-rows');
  dimRowsEl.appendChild(makeRoomDimRow(roomIdx, 0));
  dimRowsEl.appendChild(makeRoomDimRow(roomIdx, 1));

  return card;
}

// ─── Dimension validation (min 2, max 8, unique names)

function validateModalDimensions(form) {
  form.querySelectorAll('.dim-name-error').forEach(el => el.remove());
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  let valid = true;

  form.querySelectorAll('.dim-block').forEach(block => {
    // Skip blocks that aren't currently in use (e.g. unit-dimension-section
    // when the unit has rooms instead of being single-space, or vice versa)
    if (block.closest('.d-none')) return;

    const rows = block.querySelectorAll('.dimension-row');

    if (rows.length < 2) {
      const err = document.createElement('div');
      err.className = 'text-danger small mt-1 dim-name-error';
      err.innerHTML = '<i class="ti tabler-alert-circle me-1"></i>At least 2 dimensions are required.';
      block.querySelector('.btn-add-dim, .btn-add-room-dim').insertAdjacentElement('beforebegin', err);
      valid = false;
    }

    if (rows.length > 8) {
      const err = document.createElement('div');
      err.className = 'text-danger small mt-1 dim-name-error';
      err.innerHTML = '<i class="ti tabler-alert-circle me-1"></i>Maximum 8 dimensions allowed.';
      block.querySelector('.btn-add-dim, .btn-add-room-dim').insertAdjacentElement('beforebegin', err);
      valid = false;
    }

    const seen = {};
    rows.forEach(row => {
      const input = row.querySelector('input[type="text"]');
      if (!input) return;
      const val = input.value.trim().toLowerCase();
      if (!val) return;
      if (seen[val]) {
        [seen[val], input].forEach(el => {
          el.classList.add('is-invalid');
          const parent = el.closest('.input-group') ?? el;
          if (!parent.nextElementSibling?.classList.contains('dim-name-error')) {
            const err = document.createElement('div');
            err.className = 'text-danger small mt-1 dim-name-error';
            err.innerHTML = '<i class="ti tabler-alert-circle me-1"></i>Dimension name must be unique.';
            parent.insertAdjacentElement('afterend', err);
          }
        });
        valid = false;
      } else {
        seen[val] = input;
      }
    });
  });

  return valid;
}

// ─── Render room modal body

function renderRoomModal(room) {
  const amenities = window.amenitiesJson ?? [];
  const roomTypes = window.roomTypesJson ?? [];
  const selectedAm = parseAmenities(room?.amenities);

  document.getElementById('room_modal_body').innerHTML = `
    <div class="row g-3 mb-3">
      <div class="col-md-5">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Room Type</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="ti tabler-door text-muted"></i></span>
          <select name="room_type" class="form-select border-start-0" required>
            <option value="" disabled ${!room ? 'selected' : ''}>Select type</option>
            ${roomTypes.map(rt => `<option value="${rt.id}" ${room?.room_type == rt.id ? 'selected' : ''}>${ucFirst(rt.title)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    ${amenities.length ? `
    <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
    <div class="d-flex flex-wrap gap-3 mb-3">
      ${amenities.map(a => `
        <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer">
          <input class="form-check-input mt-0" type="checkbox" name="amenities[]" value="${a.id}"
                 ${selectedAm.includes(Number(a.id)) ? 'checked' : ''}>
          <span class="small">${ucFirst(a.title)}</span>
        </label>`).join('')}
    </div>` : ''}

    <label class="form-label fw-semibold small text-uppercase text-muted mb-2">
      Room Dimensions <span class="fw-normal">(min 2, max 8)</span>
    </label>
    <div class="dim-block">
      <div class="dim-rows"></div>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">
        <i class="ti tabler-plus me-1"></i> Add Dimension
      </button>
    </div>`;

  buildDimRows(
    document.querySelector('#room_modal_body .dim-block'),
    room?.dimensions ?? null
  );
}

// ─── Render unit modal body
// ─── Inline room card (used in both add and edit unit modal)

function makeInlineRoomCard(roomIdx, room) {
  const roomTypes = window.roomTypesJson ?? [];
  const amenities = window.amenitiesJson ?? [];
  const selectedAm = parseAmenities(room?.amenities);
  const p = `rooms[${roomIdx}]`;

  const card = document.createElement('div');
  card.className = 'um-room-card border rounded-3 p-3 mb-3';
  card.dataset.roomIdx = roomIdx;
  card.innerHTML = `
    ${room?.id ? `<input type="hidden" name="${p}[id]" value="${room.id}">` : ''}
    <div class="d-flex align-items-center justify-content-between mb-3">
      <span class="small fw-semibold text-uppercase text-muted">Room ${roomIdx + 1}</span>
      <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 btn-remove-um-room rounded-pill btn-label-danger waves-effect" title="Remove">
        <i class="ti tabler-trash"></i> Remove
      </button>
    </div>

    <div class="row g-2 mb-3">
      <div class="col-md-5">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Room Type</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="ti tabler-door text-muted"></i></span>
          <select name="${p}[room_type]" class="form-select border-start-0" required>
            <option value="" disabled ${!room ? 'selected' : ''}>Select type</option>
            ${roomTypes.map(rt => `<option value="${rt.id}" ${room?.room_type == rt.id ? 'selected' : ''}>${ucFirst(rt.title)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    ${amenities.length ? `
    <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Amenities</label>
    <div class="d-flex flex-wrap gap-2 mb-3">
      ${amenities.map(a => `
        <label class="d-flex align-items-center gap-2 border rounded-3 px-2 py-1 user-select-none cursor-pointer">
          <input class="form-check-input mt-0" type="checkbox" name="${p}[amenities][]" value="${a.id}"
                 ${selectedAm.includes(Number(a.id)) ? 'checked' : ''}>
          <span class="small">${ucFirst(a.title)}</span>
        </label>`).join('')}
    </div>` : ''}

    <label class="form-label fw-semibold small text-uppercase text-muted mb-1">
      Dimensions <span class="fw-normal">(min 2, max 8)</span>
    </label>
    <div class="dim-block" data-room-idx="${roomIdx}">
      <div class="dim-rows"></div>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-room-dim" style="font-size:.72rem;">
        <i class="ti tabler-plus me-1"></i> Add Dimension
      </button>
    </div>`;

  // Build dimension rows for this room using room-scoped names: rooms[i][dimensions][j][name]
  const dimRowsEl = card.querySelector('.dim-rows');
  const dims = room?.dimensions ?? null;
  if (dims && dims.length) {
    dims.forEach((d, j) => dimRowsEl.appendChild(makeRoomDimRow(roomIdx, j, d)));
  } else {
    dimRowsEl.appendChild(makeRoomDimRow(roomIdx, 0));
    dimRowsEl.appendChild(makeRoomDimRow(roomIdx, 1));
  }

  return card;
}

// Dimension row scoped to a room card: rooms[i][dimensions][j][name/size/unit]
function makeRoomDimRow(roomIdx, j, d) {
  const p = `rooms[${roomIdx}][dimensions][${j}]`;
  const div = document.createElement('div');
  div.className = 'dimension-row row g-3 mb-2 align-items-end rounded-3 mx-0';
  div.innerHTML = `
    ${d?.id ? `<input type="hidden" name="${p}[id]" value="${d.id}">` : ''}
    <div class="col-md-4">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-ruler text-muted"></i></span>
        <input type="text" name="${p}[name]" value="${d?.name ?? ''}"
               required class="form-control border-start-0" placeholder="e.g. Length, Width">
      </div>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-number text-muted"></i></span>
        <input type="number" name="${p}[size]" value="${d?.size ?? ''}"
               step="0.01" required class="form-control border-start-0" placeholder="e.g 40">
      </div>
    </div>
    <div class="col-10 col-md-3">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
      <select name="${p}[unit]" class="form-select" required>
        <option value="" disabled ${!d?.unit ? 'selected' : ''}>Select Unit</option>
        <option value="inch"  ${d?.unit === 'inch' ? 'selected' : ''}>Inch</option>
        <option value="feet"  ${d?.unit === 'feet' ? 'selected' : ''}>Feet</option>
        <option value="meter" ${d?.unit === 'meter' ? 'selected' : ''}>Meter</option>
        <option value="yard"  ${d?.unit === 'yard' ? 'selected' : ''}>Yard</option>
      </select>
    </div>
    <div class="col-2 col-md-1 ps-0 pt-4 mt-1">
      <button type="button" class="btn btn-remove-room-dim ps-3" title="Remove">
        <i class="ti tabler-x icon-lg text-danger"></i>
      </button>
    </div>`;
  return div;
}

function renderUnitModal(unit, floorId, isEdit, unitForm) {
  const unitTypes = window.unitTypesJson ?? [];
  const amenities = window.amenitiesJson ?? [];
  const isSingleRoom = unit
    ? (!unit.rooms || unit.rooms.length === 0) && unit.dimensions?.length > 0
    : false;
  const selectedAm = parseAmenities(unit?.amenities);
  const rooms = unit?.rooms ?? [];

  // Set section value
  unitForm.querySelector('[name="section"]').value = isEdit
    ? (isSingleRoom ? 'specifications' : 'unit_full')
    : 'unit';

  document.getElementById('unit_modal_body').innerHTML = `
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Name</label>
        <input type="text" name="unit_name" value="${unit?.unit_name ?? ''}"
               class="form-control" placeholder="e.g. Suite A" required>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Type</label>
        <select name="unit_type" class="form-select" required>
          <option value="" disabled ${!unit ? 'selected' : ''}>Select type</option>
          ${unitTypes.map(ut => `<option value="${ut.id}" ${unit?.unit_type == ut.id ? 'selected' : ''}>${ucFirst(ut.title)}</option>`).join('')}
        </select>
      </div>
    </div>

    <div class="form-check mt-2 mb-3">
      <input class="form-check-input no_rooms_check" type="checkbox"
             name="no_rooms" value="1" id="um_no_rooms" ${isSingleRoom ? 'checked' : ''}>
      <label class="form-check-label fw-semibold" for="um_no_rooms">
        This unit has no further room divisions (single space).
      </label>
    </div>

    ${amenities.length ? `
    <div class="unit-amenities-section ${isSingleRoom ? '' : 'd-none'}">
      <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
      <div class="d-flex flex-wrap gap-3 mb-3">
        ${amenities.map(a => `
          <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none cursor-pointer">
            <input class="form-check-input mt-0" type="checkbox" name="amenities[]" value="${a.id}"
                   ${selectedAm.includes(Number(a.id)) ? 'checked' : ''}>
            <span class="small">${ucFirst(a.title)}</span>
          </label>`).join('')}
      </div>
    </div>` : ''}

    <div class="unit-dimension-section ${isSingleRoom ? '' : 'd-none'}">
      <label class="small form-label fw-semibold text-uppercase text-muted">
        Unit Dimensions <span class="fw-normal">(min 2, max 8)</span>
      </label>
      <div class="mb-3 dim-block">
        <div class="dim-rows"></div>
        <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">
          <i class="ti tabler-plus me-1"></i> Add Dimension
        </button>
      </div>
    </div>

    <div class="unit-rooms-section ${isSingleRoom ? 'd-none' : ''}">
      <p class="small fw-semibold text-uppercase text-muted mb-2">Rooms in this unit</p>
      <div id="um_rooms_container"></div>
      <button type="button"
              class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 btn-add-um-room"
              data-room-count="${rooms.length}">
        <i class="ti tabler-plus me-1"></i> Add Room
      </button>
    </div>`;

  // Fill unit dimensions (single-space mode)
  if (isSingleRoom && unit?.dimensions?.length) {
    buildDimRows(
      document.querySelector('#unit_modal_body .unit-dimension-section .dim-block'),
      unit.dimensions
    );
  }

  // Fill existing rooms inline
  const container = document.getElementById('um_rooms_container');
  rooms.forEach((room, idx) => container.appendChild(makeInlineRoomCard(idx, room)));
}

// ─── Add Floor Modal

document.getElementById('add_floor_modal')?.addEventListener('show.bs.modal', function() {
  editFloorCount = document.querySelectorAll('#edit-floors-container > .floor-item').length;
  const container = document.getElementById('add-floor-container');
  container.innerHTML = '';
  const fPrefix = 'floors[0]';
  const floorEl = cloneTpl('tpl-floor', { '__PREFIX__': fPrefix });
  floorEl.dataset.floorPrefix = fPrefix;
  if (!editIsResidential) floorEl.querySelector('.units-section-wrapper')?.classList.remove('d-none');
  floorEl.querySelector('.btn-add-floor-room')?.setAttribute('data-floor-prefix', fPrefix);
  floorEl.querySelector('.btn-add-floor-room')?.setAttribute('data-room-count', '0');
  floorEl.querySelector('.btn-add-unit')?.setAttribute('data-floor-prefix', fPrefix);
  floorEl.querySelector('.btn-add-unit')?.setAttribute('data-unit-count', '0');
  container.appendChild(floorEl);
});

document.addEventListener('click', function(e) {
  if (!e.target.closest('#add_floor_modal')) return;

  // dims handled by common (floor/room templates use tpl-dim-row with proper prefix — correct here)
  handleDimClicks(e);

  if (e.target.closest('.btn-add-floor-room')) {
    const btn = e.target.closest('.btn-add-floor-room');
    const roomIdx = parseInt(btn.dataset.roomCount ?? 0);
    addRoom($(btn.closest('.floor-rooms-section').querySelector('.floor-rooms-container')), btn.dataset.floorPrefix, roomIdx);
    btn.dataset.roomCount = roomIdx + 1;
    return;
  }
  if (e.target.closest('.btn-add-unit-room')) {
    const btn = e.target.closest('.btn-add-unit-room');
    const uPrefix = btn.closest('.unit-item').querySelector('[name$="[unit_name]"]').name.replace('[unit_name]', '');
    const roomIdx = parseInt(btn.dataset.roomCount ?? 0);
    addRoom($(btn.closest('.unit-item').querySelector('.unit-rooms-container')), uPrefix, roomIdx);
    btn.dataset.roomCount = roomIdx + 1;
    return;
  }
  if (e.target.closest('.btn-remove-room')) {
    e.target.closest('.room-item').remove();
    return;
  }
  if (e.target.closest('.btn-add-unit')) {
    const btn = e.target.closest('.btn-add-unit');
    const unitIdx = parseInt(btn.dataset.unitCount ?? 0);
    addUnit($(btn.previousElementSibling), btn.dataset.floorPrefix, unitIdx);
    btn.dataset.unitCount = unitIdx + 1;
    return;
  }
  if (e.target.closest('.btn-remove-unit')) {
    e.target.closest('.unit-item').remove();
    return;
  }
});

document.getElementById('add_floor_form')?.addEventListener('submit', function(e) {
  validateConstructionForm(this, e);
});

// ─── Room Modal (Add / Edit)

document.getElementById('room_modal')?.addEventListener('show.bs.modal', function(e) {
  const btn = this._openRoomBtn ?? e.relatedTarget;
  this._openRoomBtn = null;
  if (btn) openRoomModal(btn);
}, true);

function openRoomModal(btn) {
  const roomId = btn.dataset.roomId ?? '';
  const floorId = btn.dataset.floorId ?? '';
  const unitId = btn.dataset.unitId ?? '';
  const isEdit = !!roomId;

  document.getElementById('room_modal_title').innerHTML =
    `<span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span> ${isEdit ? 'Edit' : 'Add'} Room`;
  document.getElementById('room_modal_room_id').value = roomId;
  document.getElementById('room_modal_floor_id').value = floorId;
  document.getElementById('room_modal_unit_id').value = unitId;

  const roomForm = document.getElementById('room_form');
  const unitRoute = window.unitUpdateRoute ?? '/unit/update';
  const floorRoute = window.floorRoomRoute ?? '/floor-room/store';

  if (unitId) {
    roomForm.action = unitRoute;
    roomForm.querySelector('[name="section"]').value = isEdit ? 'edit_room' : 'add_room';
  } else {
    roomForm.action = floorRoute;
    roomForm.querySelector('[name="section"]').value = isEdit ? 'edit' : 'store';
  }

  if (!isEdit) {
    renderRoomModal(null);
    return;
  }

  if (unitId) {
    fetch(`/floor/${floorId}/units?skip=0&take=999`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        const room = data.units?.find(u => u.id == unitId)?.rooms?.find(r => r.id == roomId) ?? null;
        renderRoomModal(room);
      });
  } else {
    fetch(`/room/${roomId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(room => renderRoomModal(room));
  }
}

// ─── Dim add/remove inside room_modal and unit_modal // Uses makeDimRow — NOT handleDimClicks — to produce flat dimensions[i] names.

document.addEventListener('click', function(e) {
  if (!e.target.closest('#room_modal') && !e.target.closest('#unit_modal')) return;

  // Unit-level dim add/remove (single-space mode)
  if (e.target.closest('.btn-add-dim')) {
    e.stopPropagation();
    const block = e.target.closest('.btn-add-dim').closest('.dim-block');
    const count = block.querySelectorAll('.dimension-row').length;
    block.querySelector('.dim-rows').appendChild(makeDimRow(count));
    return;
  }
  if (e.target.closest('.btn-remove-dim')) {
    e.stopPropagation();
    const dimRows = e.target.closest('.btn-remove-dim').closest('.dim-rows');
    if (dimRows.querySelectorAll('.dimension-row').length > 1) {
      e.target.closest('.dimension-row').remove();
    }
    return;
  }

  // Room-card dim add/remove (rooms[i][dimensions][j]) — used by both
  // Add Unit room cards and Edit Unit room cards.
  if (e.target.closest('.btn-add-room-dim')) {
    e.stopPropagation();
    const card = e.target.closest('.um-room-card');
    const roomIdx = parseInt(card.dataset.roomIdx);
    const dimRows = card.querySelector('.dim-rows');
    const j = dimRows.querySelectorAll('.dimension-row').length;
    dimRows.appendChild(makeRoomDimRow(roomIdx, j));
    return;
  }
  if (e.target.closest('.btn-remove-room-dim')) {
    e.stopPropagation();
    const dimRows = e.target.closest('.btn-remove-room-dim').closest('.dim-rows');
    if (dimRows.querySelectorAll('.dimension-row').length > 1) {
      e.target.closest('.dimension-row').remove();
    }
    return;
  }
});

document.getElementById('room_form')?.addEventListener('submit', function(e) {
  if (!validateModalDimensions(this)) {
    notify('Please fix the errors before submitting.', 'error');
    e.preventDefault();
  }
});

// ─── Unit Modal (Add / Edit

document.getElementById('unit_modal')?.addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  const unitId = btn?.dataset.unitId ?? '';
  const floorId = btn?.dataset.floorId ?? '';
  const isEdit = !!unitId;

  document.getElementById('unit_modal_title').innerHTML =
    `<span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span> ${isEdit ? 'Edit' : 'Add'} Unit`;
  document.getElementById('unit_modal_unit_id').value = unitId;
  document.getElementById('unit_modal_floor_id').value = floorId;

  const unitForm = document.getElementById('unit_form');
  unitForm.action = window.unitUpdateRoute ?? '/unit/update';
  unitForm.querySelector('[name="property_id"]')?.remove();

  let uf = unitForm.querySelector('[name="unit_id"]');
  if (!uf) {
    uf = Object.assign(document.createElement('input'), { type: 'hidden', name: 'unit_id' });
    unitForm.prepend(uf);
  }
  uf.value = unitId;

  let ff = unitForm.querySelector('[name="floor_id"]');
  if (!ff) {
    ff = Object.assign(document.createElement('input'), { type: 'hidden', name: 'floor_id' });
    unitForm.prepend(ff);
  }
  ff.value = floorId;

  unitForm.querySelector('[name="section"]').value = isEdit ? 'basic' : 'unit';

  if (isEdit) {
    fetch(`/floor/${floorId}/units?skip=0&take=999`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        const unit = data.units?.find(u => u.id == unitId) ?? null;
        renderUnitModal(unit, floorId, true, unitForm);
      });
  } else {
    renderUnitModal(null, floorId, false, unitForm);
  }
});

// ─── Delegated clicks inside unit_modal

document.addEventListener('click', function(e) {
  if (!e.target.closest('#unit_modal')) return;

  // no_rooms toggle
  if (e.target.closest('.no_rooms_check')) {
    const checked = e.target.closest('.no_rooms_check').checked;
    const modal = document.getElementById('unit_modal');
    const unitForm = document.getElementById('unit_form');
    const isEditMode = !!document.getElementById('unit_modal_unit_id')?.value;

    modal.querySelector('.unit-rooms-section')?.classList.toggle('d-none', checked);
    modal.querySelector('.unit-dimension-section')?.classList.toggle('d-none', !checked);
    modal.querySelector('.unit-amenities-section')?.classList.toggle('d-none', !checked);

    if (isEditMode) {
      unitForm.querySelector('[name="section"]').value = checked ? 'specifications' : 'unit_full';
    }
    if (checked) {
      const dimBlock = modal.querySelector('.unit-dimension-section .dim-block');
      if (dimBlock && !dimBlock.querySelectorAll('.dimension-row').length) {
        dimBlock.querySelector('.dim-rows').appendChild(makeDimRow(0));
        dimBlock.querySelector('.dim-rows').appendChild(makeDimRow(1));
      }
    } else {
      modal.querySelector('.unit-dimension-section .dim-block .dim-rows').innerHTML = '';
    }
    return;
  }

  // Add room card (add-unit mode AND edit-unit "switched back to has rooms" mode)
  // — uses makeAddUnitRoomCard so the section stays 'unit' / 'unit_full' compatible
  if (e.target.closest('.btn-add-um-room')) {
    const btn = e.target.closest('.btn-add-um-room');
    const roomIdx = parseInt(btn.dataset.roomCount ?? 0);
    const container = document.getElementById('um_rooms_container');
    container.appendChild(makeAddUnitRoomCard(roomIdx));
    btn.dataset.roomCount = roomIdx + 1;
    return;
  }

  // Remove inline room card
  if (e.target.closest('.btn-remove-um-room')) {
    const container = document.getElementById('um_rooms_container');
    e.target.closest('.um-room-card').remove();
    // Re-number remaining cards so rooms[0], rooms[1]... stay sequential
    container.querySelectorAll('.um-room-card').forEach((card, idx) => {
      card.dataset.roomIdx = idx;
      card.querySelector('.small.fw-semibold.text-muted').textContent = `Room ${idx + 1}`;
      card.querySelectorAll('[name]').forEach(el => {
        el.name = el.name.replace(/rooms\[\d+\]/, `rooms[${idx}]`);
      });
      card.querySelectorAll('.dim-block').forEach(block => {
        block.dataset.roomIdx = idx;
      });
    });
    return;
  }
});


// ─── Form submit handlers

document.getElementById('unit_form')?.addEventListener('submit', function(e) {
  const isSingleSpace = document.getElementById('um_no_rooms')?.checked;

  // Validate ALL dimension blocks present in the form — the unit-level dims
  // (single-space mode) AND every room card's dimensions (Add Unit + Edit Unit).
  if (!validateModalDimensions(this)) {
    notify('Please fix the errors before submitting.', 'error');
    e.preventDefault();
    return;
  }

  // When rooms are shown, ensure at least one room card exists
  if (!isSingleSpace) {
    const roomCards = document.querySelectorAll('#um_rooms_container .um-room-card');
    if (!roomCards.length) {
      notify('Please add at least one room.', 'error');
      e.preventDefault();
    }
  }
});
