let editIsResidential = window.editIsResidential ?? false;
let editFloorCount = 0;

// ─── Utility
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

function cloneUnitCard() {
  const tpl = document.getElementById('tpl-unit');
  if (!tpl) return null;
  const node = tpl.content.cloneNode(true);
  const div = document.createElement('div');
  div.appendChild(node);

  let html = div.innerHTML;
  html = html.replaceAll('__PREFIX__[units][__UNIT__]', 'MODAL__');

  html = html.replace(/MODAL__\[([^\]]+)\]/g, '$1');

  div.innerHTML = html;
  return div.firstElementChild;
}


function cloneRoomCard(namePrefix) {
  const tpl = document.getElementById('tpl-room');
  if (!tpl) return null;
  const node = tpl.content.cloneNode(true);
  const div = document.createElement('div');
  div.appendChild(node);
  div.innerHTML = div.innerHTML.replaceAll('__PREFIX__[rooms][__ROOM__]', namePrefix);
  return div.firstElementChild;
}

// ─── Dimension validation for modals (min 2, max 8, unique names)

function validateModalDimensions(form) {
  form.querySelectorAll('.dim-name-error').forEach(el => el.remove());
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  let valid = true;

  form.querySelectorAll('.dim-block').forEach(block => {
    if (block.closest('.d-none')) return;
    const rows = block.querySelectorAll('.dimension-row');

    if (rows.length < 2) {
      const err = document.createElement('div');
      err.className = 'text-danger small mt-1 dim-name-error';
      err.innerHTML = '<i class="ti tabler-alert-circle me-1"></i>At least 2 dimensions are required.';
      block.querySelector('.btn-add-dim')?.insertAdjacentElement('beforebegin', err);
      valid = false;
    }
    if (rows.length > 8) {
      const err = document.createElement('div');
      err.className = 'text-danger small mt-1 dim-name-error';
      err.innerHTML = '<i class="ti tabler-alert-circle me-1"></i>Maximum 8 dimensions allowed.';
      block.querySelector('.btn-add-dim')?.insertAdjacentElement('beforebegin', err);
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

// ─── Add Floor Modal
// Identical to original — uses cloneTpl/addRoom/addUnit/handleDimClicks from common.

document.getElementById('add_floor_modal')?.addEventListener('show.bs.modal', function() {
  editFloorCount = document.querySelectorAll('#edit-floors-container > .floor-item').length;
  const container = document.getElementById('add-floor-container');
  container.innerHTML = '';
  const fPrefix = 'floors[0]';
  const floorEl = cloneTpl('tpl-floor', { '__PREFIX__': fPrefix });
  if (!floorEl) return;
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
  roomForm.action = unitId
    ? (window.unitUpdateRoute ?? '/unit/update')
    : (window.floorRoomRoute ?? '/floor-room/store');
  roomForm.querySelector('[name="section"]').value = unitId
    ? (isEdit ? 'edit_room' : 'add_room')
    : (isEdit ? 'edit' : 'store');

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

function renderRoomModal(room) {
  const body = document.getElementById('room_modal_body');
  body.innerHTML = '';

  // Clone tpl-room with a throwaway prefix, take inner .p-3 content only,
  const card = cloneRoomCard('FLAT_ROOM');
  if (!card) return;

  const inner = card.querySelector('.p-3') ?? card;
  body.appendChild(inner);

  // Rename room_type → flat "room_type"
  const roomTypeSel = body.querySelector('select[name="FLAT_ROOM[room_type]"]');
  if (roomTypeSel) roomTypeSel.name = 'room_type';

  // Rename amenities → flat "amenities[]"
  body.querySelectorAll('input[name="FLAT_ROOM[amenities][]"]').forEach(cb => {
    cb.name = 'amenities[]';
  });

  // dim-block: set data-prefix to '' so handleDimClicks / addDimRow

  const dimBlock = body.querySelector('.dim-block');
  if (dimBlock) dimBlock.dataset.prefix = 'FLAT_ROOM';

  // Pre-fill or seed dim rows
  const $dimBlock = $(dimBlock);
  const dims = room?.dimensions ?? [];

  if (room) {
    if (roomTypeSel && room.room_type) roomTypeSel.value = room.room_type;

    const selAm = parseAmenities(room.amenities);
    body.querySelectorAll('input[name="amenities[]"]').forEach(cb => {
      cb.checked = selAm.includes(Number(cb.value));
    });

    dims.forEach((d, i) => {
      const row = buildFlatDimRow(i, d);
      dimBlock.querySelector('.dim-rows').appendChild(row);
    });
  } else {
    dimBlock.querySelector('.dim-rows').appendChild(buildFlatDimRow(0));
    dimBlock.querySelector('.dim-rows').appendChild(buildFlatDimRow(1));
  }
}

// Build a flat dimension row: dimensions[i][name/size/unit]
function buildFlatDimRow(i, d) {
  const node = cloneTpl('tpl-dim-row', { '__PREFIX__': 'FLAT_DIM', '__DIM__': i });
  if (!node) return document.createElement('div');
  node.querySelector('[name*="[name]"]').name = `dimensions[${i}][name]`;
  node.querySelector('[name*="[size]"]').name = `dimensions[${i}][size]`;
  node.querySelector('[name*="[unit]"]').name = `dimensions[${i}][unit]`;
  if (d) {
    node.querySelector(`[name="dimensions[${i}][name]"]`).value = d.name ?? '';
    node.querySelector(`[name="dimensions[${i}][size]"]`).value = d.size ?? '';
    const unitSel = node.querySelector(`[name="dimensions[${i}][unit]"]`);
    [...unitSel.options].forEach(o => {
      o.selected = o.value === (d.unit ?? '');
    });
    if (d.id) {
      const h = Object.assign(document.createElement('input'), {
        type: 'hidden', name: `dimensions[${i}][id]`, value: d.id
      });
      node.prepend(h);
    }
  }
  return node;
}

// Delegated dim add/remove inside room_modal — uses buildFlatDimRow
document.addEventListener('click', function(e) {
  if (!e.target.closest('#room_modal')) return;

  if (e.target.closest('.btn-add-dim')) {
    e.stopPropagation();
    const dimRows = document.querySelector('#room_modal_body .dim-rows');
    if (!dimRows) return;
    const i = dimRows.querySelectorAll('.dimension-row').length;
    dimRows.appendChild(buildFlatDimRow(i));
    return;
  }
  if (e.target.closest('.btn-remove-dim')) {
    e.stopPropagation();
    const dimRows = e.target.closest('.dim-rows');
    if (dimRows && dimRows.querySelectorAll('.dimension-row').length > 1) {
      e.target.closest('.dimension-row').remove();
      // Re-index
      dimRows.querySelectorAll('.dimension-row').forEach((row, idx) => {
        row.querySelectorAll('[name*="dimensions"]').forEach(el => {
          el.name = el.name.replace(/dimensions\[\d+\]/, `dimensions[${idx}]`);
        });
      });
    }
  }
});

document.getElementById('room_form')?.addEventListener('submit', function(e) {
  if (!validateModalDimensions(this)) {
    notify('Please fix the errors before submitting.', 'error');
    e.preventDefault();
  }
});

// ─── Unit Modal (Add / Edit)

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

function renderUnitModal(unit, floorId, isEdit, unitForm) {
  const isSingle = unit
    ? (!unit.rooms || unit.rooms.length === 0) && (unit.dimensions?.length > 0)
    : false;
  const rooms = unit?.rooms ?? [];

  unitForm.querySelector('[name="section"]').value = isEdit
    ? (isSingle ? 'specifications' : 'unit_full')
    : 'unit';

  // ── Clone tpl-unit with flat field names
  const card = cloneUnitCard();
  if (!card) return;

  // tpl-unit wraps everything in .unit-item with a header (.unit_header + remove btn)
  // and .p-3 body. For the modal we only need the .p-3 inner content.
  const inner = card.querySelector('.p-3') ?? card;
  const body = document.getElementById('unit_modal_body');
  body.innerHTML = '';
  body.appendChild(inner);

  // ── Pre-fill unit_name and unit_type
  const unitNameInput = body.querySelector('[name="unit_name"]');
  if (unitNameInput && unit?.unit_name) unitNameInput.value = unit.unit_name;

  const unitTypeSel = body.querySelector('[name="unit_type"]');
  if (unitTypeSel && unit?.unit_type) unitTypeSel.value = unit.unit_type;

  // ── no_rooms checkbox state
  const noRoomsCheck = body.querySelector('.no_rooms_check');
  if (noRoomsCheck) noRoomsCheck.checked = isSingle;

  // ── Show/hide sections based on isSingle
  body.querySelector('.unit-amenities-section')?.classList.toggle('d-none', !isSingle);
  body.querySelector('.unit-dimension-section')?.classList.toggle('d-none', !isSingle);
  body.querySelector('.unit-rooms-section')?.classList.toggle('d-none', isSingle);

  // ── Pre-fill amenities (unit-level, single-space mode)
  if (isSingle) {
    const selAm = parseAmenities(unit?.amenities);
    body.querySelectorAll('input[name="amenities[]"]').forEach(cb => {
      cb.checked = selAm.includes(Number(cb.value));
    });
  }

  // ── dim-block: data-prefix is '' after cloneUnitCard regex cleanup,
  const dimBlock = body.querySelector('.unit-dimension-section .dim-block');
  if (dimBlock) {
    const dimRows = dimBlock.querySelector('.dim-rows');
    dimRows.innerHTML = '';
    if (isSingle) {
      const dims = unit?.dimensions ?? [];
      if (dims.length) {
        dims.forEach((d, i) => dimRows.appendChild(buildFlatDimRow(i, d)));
      } else {
        dimRows.appendChild(buildFlatDimRow(0));
        dimRows.appendChild(buildFlatDimRow(1));
      }
    }

    dimBlock.dataset.prefix = 'UNIT_MODAL_DIM';
  }

  // ── Rename rooms container + add-room button for unit modal
  const roomsContainer = body.querySelector('.unit-rooms-container');
  if (roomsContainer) roomsContainer.id = 'um_rooms_container';

  const addRoomBtn = body.querySelector('.btn-add-unit-room');
  if (addRoomBtn) {
    addRoomBtn.dataset.roomCount = rooms.length;
    addRoomBtn.classList.add('btn-add-um-room');
    addRoomBtn.classList.remove('btn-add-unit-room');
  }

  // ── Seed inline room cards (add mode: use tpl-room via cloneRoomCard)
  const container = body.querySelector('#um_rooms_container');
  if (container) {
    rooms.forEach((room, idx) => appendUnitRoomCard(container, idx, room));
  }
}

// Append one inline room card using tpl-room.
// namePrefix='rooms[idx]' → rooms[idx][room_type], rooms[idx][dimensions][j][name] ✓
function appendUnitRoomCard(container, roomIdx, room) {
  const namePrefix = `rooms[${roomIdx}]`;
  const card = cloneRoomCard(namePrefix);
  if (!card) return;

  card.dataset.roomIdx = roomIdx;

  // Set dim-block data-prefix so handleDimClicks / addDimRow work correctly
  const dimBlock = card.querySelector('.dim-block');
  if (dimBlock) dimBlock.dataset.prefix = namePrefix;

  if (room) {
    const sel = card.querySelector(`select[name="${namePrefix}[room_type]"]`);
    if (sel && room.room_type) sel.value = room.room_type;

    const selAm = parseAmenities(room.amenities);
    card.querySelectorAll(`input[name="${namePrefix}[amenities][]"]`).forEach(cb => {
      cb.checked = selAm.includes(Number(cb.value));
    });

    if (room.id) {
      const h = Object.assign(document.createElement('input'), {
        type: 'hidden', name: `${namePrefix}[id]`, value: room.id
      });
      card.prepend(h);
    }

    const $dimBlock = $(dimBlock);
    (room.dimensions ?? []).forEach((d, j) => {
      addDimRow($dimBlock, namePrefix);
      const rows = dimBlock.querySelectorAll('.dimension-row');
      const row = rows[rows.length - 1];
      row.querySelector('[name*="[name]"]').value = d.name ?? '';
      row.querySelector('[name*="[size]"]').value = d.size ?? '';
      const unitSel = row.querySelector('[name*="[unit]"]');
      [...unitSel.options].forEach(o => {
        o.selected = o.value === (d.unit ?? '');
      });
      if (d.id) {
        const h = Object.assign(document.createElement('input'), {
          type: 'hidden', name: `${namePrefix}[dimensions][${j}][id]`, value: d.id
        });
        row.prepend(h);
      }
    });
  } else {
    const $dimBlock = $(dimBlock);
    addDimRow($dimBlock, namePrefix);
    addDimRow($dimBlock, namePrefix);
  }

  // Swap remove button: .btn-remove-room → .btn-remove-um-room
  const removeBtn = card.querySelector('.btn-remove-room');
  if (removeBtn) {
    removeBtn.classList.remove('btn-remove-room');
    removeBtn.classList.add('btn-remove-um-room');
  }

  container.appendChild(card);
}

// ─── Delegated clicks inside unit_modal ───────────────────────────────────────

document.addEventListener('click', function(e) {
  if (!e.target.closest('#unit_modal')) return;

  // Room-card dim add/remove — handleDimClicks works because data-prefix=namePrefix is set
  // Unit-level dim-block has data-prefix='UNIT_MODAL_DIM' so handleDimClicks won't
  // accidentally use it with a wrong prefix — we intercept it below instead.
  if (!e.target.closest('.unit-dimension-section')) {
    handleDimClicks(e);
  }

  // Unit-level dim add — produces dimensions[i][name/size/unit]
  if (e.target.closest('.unit-dimension-section .btn-add-dim')) {
    e.stopPropagation();
    const dimRows = document.querySelector('#unit_modal_body .unit-dimension-section .dim-rows');
    if (!dimRows) return;
    const i = dimRows.querySelectorAll('.dimension-row').length;
    dimRows.appendChild(buildFlatDimRow(i));
    return;
  }

  // Unit-level dim remove
  if (e.target.closest('.unit-dimension-section .btn-remove-dim')) {
    e.stopPropagation();
    const dimRows = e.target.closest('.dim-rows');
    if (dimRows && dimRows.querySelectorAll('.dimension-row').length > 1) {
      e.target.closest('.dimension-row').remove();
      dimRows.querySelectorAll('.dimension-row').forEach((row, idx) => {
        row.querySelectorAll('[name*="dimensions"]').forEach(el => {
          el.name = el.name.replace(/dimensions\[\d+\]/, `dimensions[${idx}]`);
        });
      });
    }
    return;
  }

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
      const dimRows = modal.querySelector('.unit-dimension-section .dim-rows');
      if (dimRows && !dimRows.querySelectorAll('.dimension-row').length) {
        dimRows.appendChild(buildFlatDimRow(0));
        dimRows.appendChild(buildFlatDimRow(1));
      }
    } else {
      const dimRows = modal.querySelector('.unit-dimension-section .dim-rows');
      if (dimRows) dimRows.innerHTML = '';
    }
    return;
  }

  // Add inline room card
  if (e.target.closest('.btn-add-um-room')) {
    const btn = e.target.closest('.btn-add-um-room');
    const roomIdx = parseInt(btn.dataset.roomCount ?? 0);
    const container = document.getElementById('um_rooms_container');
    appendUnitRoomCard(container, roomIdx, null);
    btn.dataset.roomCount = roomIdx + 1;
    return;
  }

  // Remove inline room card — re-index remaining
  if (e.target.closest('.btn-remove-um-room')) {
    e.target.closest('.room-item').remove();
    const container = document.getElementById('um_rooms_container');
    const addBtn = document.querySelector('#unit_modal .btn-add-um-room');

    container.querySelectorAll('.room-item').forEach((card, idx) => {
      const oldPrefix = `rooms[${card.dataset.roomIdx}]`;
      const newPrefix = `rooms[${idx}]`;
      card.dataset.roomIdx = idx;
      card.querySelectorAll('[name]').forEach(el => {
        el.name = el.name.replace(oldPrefix, newPrefix);
      });
      card.querySelectorAll('.dim-block').forEach(block => {
        if (block.dataset.prefix === oldPrefix) block.dataset.prefix = newPrefix;
      });
    });

    if (addBtn) addBtn.dataset.roomCount = container.querySelectorAll('.room-item').length;
    return;
  }
});

// ─── Unit form submit ──────────────────────────────────────────────────────────

document.getElementById('unit_form')?.addEventListener('submit', function(e) {
  const isSingleSpace = document.getElementById('um_no_rooms')?.checked
    ?? document.querySelector('#unit_modal_body .no_rooms_check')?.checked;

  if (!validateModalDimensions(this)) {
    notify('Please fix the errors before submitting.', 'error');
    e.preventDefault();
    return;
  }

  if (!isSingleSpace) {
    const roomCards = document.querySelectorAll('#um_rooms_container .room-item');
    if (!roomCards.length) {
      notify('Please add at least one room.', 'error');
      e.preventDefault();
    }
  }
});
