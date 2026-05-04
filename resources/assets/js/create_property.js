let isCommercial = window.isCommercial ?? false;
let floorCount   = window.initialFloorCount ?? 0;

// Category cards
const cons_wrapper = document.getElementById('constructed_wrapper');
let isPageLoadSelect = false;
let type_select = document.getElementById('type');
document.querySelectorAll('.category-card').forEach(card => {
  card.addEventListener('click', () => {
    // update card styles
    document.querySelectorAll('.category-card').forEach(c => {
      c.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');
      const dot = c.querySelector('.radio-dot');
      dot.classList.replace('bg-primary', 'border-secondary') || dot.classList.remove('bg-primary', 'border-primary');
      dot.classList.add('border-secondary');
    });
    card.classList.add('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');
    const dot = card.querySelector('.radio-dot');
    dot.classList.add('bg-primary', 'border-primary');
    dot.classList.remove('border-secondary');
    card.querySelector('input[type="radio"]').checked = true;

    // populate type dropdown
    fillTypes(type_select, card.querySelector('input').value, type_select.dataset.old);

    // on real user click: reset construction status to pending
    if (!isPageLoadSelect) {
      cons_wrapper.classList.add('d-none');
      document.getElementById('cons_pending').checked = true;
      document.getElementById('label_constructed').classList.remove('border-success', 'bg-success', 'bg-opacity-10');
      document.getElementById('label_progress').classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
      document.getElementById('label_pending').classList.add('border-warning', 'bg-warning', 'bg-opacity-10');
    }
  });
});

// pre-select saved category on page load without resetting construction status
const preSelected = document.querySelector('input[name="category"]:checked');
if (preSelected) {
  isPageLoadSelect = true;
  preSelected.closest('.category-card').click();
  isPageLoadSelect = false;
}

//  Add floor
function addFloor() {
  const fIdx    = floorCount++;
  const fPrefix = 'floors[' + fIdx + ']';
  const $floor  = $(cloneTpl('tpl-floor', { '__PREFIX__': fPrefix }));
  $floor.attr('data-floor-idx', fIdx).attr('data-floor-prefix', fPrefix);
  if (isCommercial) $floor.find('.units-section-wrapper').removeClass('d-none');
  $floor.find('.btn-add-floor-room').attr('data-floor-prefix', fPrefix).attr('data-room-count', 0);
  $floor.find('.btn-add-unit').attr('data-floor-prefix', fPrefix).attr('data-unit-count', 0);
  $('#floors-container').append($floor);
}

document.getElementById('btn-add-floor')?.addEventListener('click', addFloor);

// auto-add one floor if none exist
if (!window.initialFloorCount) addFloor();

//  Delegated clicks (create page)
document.addEventListener('click', function (e) {

  // dim add/remove — handled by common
  handleDimClicks(e);

  if (e.target.closest('.btn-remove-floor')) {
    e.stopPropagation();
    e.target.closest('.floor-item').remove();
    return;
  }

  if (e.target.closest('.btn-add-floor-room')) {
    e.stopPropagation();
    const btn       = e.target.closest('.btn-add-floor-room');
    const prefix    = btn.dataset.floorPrefix;
    const roomIdx   = parseInt(btn.getAttribute('data-room-count'));
    const container = btn.closest('.floor-rooms-section').querySelector('.floor-rooms-container');
    addRoom($(container), prefix, roomIdx);
    btn.setAttribute('data-room-count', roomIdx + 1);
    return;
  }

  if (e.target.closest('.btn-add-unit-room')) {
    e.stopPropagation();
    const btn       = e.target.closest('.btn-add-unit-room');
    const uPrefix   = btn.closest('.unit-item').querySelector('[name$="[unit_name]"]').name.replace('[unit_name]', '');
    const roomIdx   = parseInt(btn.getAttribute('data-room-count'));
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
    const btn     = e.target.closest('.btn-add-unit');
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

  if (e.target.closest('.has_units_check')) {
    const floor   = e.target.closest('.floor-item');
    const checked = e.target.closest('.has_units_check').checked;
    floor.querySelector('.units-section').classList.toggle('d-none', !checked);
    floor.querySelector('.floor-rooms-section').classList.toggle('d-none', checked);
  }
});

//  Construction form submit
document.getElementById('construction_form')?.addEventListener('submit', function (e) {
  validateConstructionForm(this, e);
});
