let editFloorCount = 0;

// init floor count when modal opens
document.getElementById('edit_construction_modal')?.addEventListener('show.bs.modal', function() {
  editFloorCount = this.querySelectorAll('#edit-floors-container > .floor-item').length;
});


//  Add floor (edit modal)
function editAddFloor() {
  const fIdx = editFloorCount++;
  const fPrefix = 'floors[' + fIdx + ']';
  const $floor = $(cloneTpl('tpl-floor', { '__PREFIX__': fPrefix }));
  $floor.attr('data-floor-prefix', fPrefix);
  if (!editIsResidential) $floor.find('.units-section-wrapper').removeClass('d-none');
  $floor.find('.btn-add-floor-room').attr('data-floor-prefix', fPrefix).attr('data-room-count', 0);
  $floor.find('.btn-add-unit').attr('data-floor-prefix', fPrefix).attr('data-unit-count', 0);
  $('#edit-floors-container').append($floor);
}

document.getElementById('edit-btn-add-floor')?.addEventListener('click', editAddFloor);

//Delegated clicks (scoped to edit modal only)
document.addEventListener('click', function(e) {
  if (!e.target.closest('#edit_construction_modal')) return;
  // dim add/remove — handled by common
  handleDimClicks(e);
  if (e.target.closest('.btn-remove-floor')) {
    e.stopPropagation();
    e.target.closest('.floor-item').remove();
    return;
  }

  if (e.target.closest('.btn-add-floor-room')) {
    e.stopPropagation();
    const btn = e.target.closest('.btn-add-floor-room');
    const prefix = btn.dataset.floorPrefix;
    const roomIdx = parseInt(btn.getAttribute('data-room-count'));
    const container = btn.closest('.floor-rooms-section').querySelector('.floor-rooms-container');
    addRoom($(container), prefix, roomIdx);
    btn.setAttribute('data-room-count', roomIdx + 1);
    return;
  }

  if (e.target.closest('.btn-add-unit-room')) {
    e.stopPropagation();
    const btn = e.target.closest('.btn-add-unit-room');
    const uPrefix = btn.closest('.unit-item').querySelector('[name$="[unit_name]"]').name.replace('[unit_name]', '');
    const roomIdx = parseInt(btn.getAttribute('data-room-count'));
    const container = btn.closest('.unit-item').querySelector('.unit-rooms-container');
    addRoom($(container), uPrefix, roomIdx);
    btn.setAttribute('data-room-count', roomIdx + 1);
    return;
  }

  if (e.target.closest('.btn-remove-room')) {
    e.stopPropagation();
    e.target.closest('.room-item').remove();
    return;
  }

  if (e.target.closest('.btn-add-unit')) {
    e.stopPropagation();
    const btn = e.target.closest('.btn-add-unit');
    const fPrefix = btn.dataset.floorPrefix;
    const unitIdx = parseInt(btn.getAttribute('data-unit-count'));
    addUnit($(btn.previousElementSibling), fPrefix, unitIdx);
    btn.setAttribute('data-unit-count', unitIdx + 1);
    return;
  }

  if (e.target.closest('.btn-remove-unit')) {
    e.stopPropagation();
    e.target.closest('.unit-item').remove();
    return;
  }


});

//  Construction form submit (edit modal)
document.getElementById('construction_form')?.addEventListener('submit', function(e) {
  validateConstructionForm(this, e);
});
