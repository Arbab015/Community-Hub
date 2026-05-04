<template id="tpl-unit">
  <div class="unit-item border rounded-3 mb-2 overflow-hidden">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom unit_header">
        <span class="d-flex align-items-center gap-2 fw-semibold text-success card_title">
          <span class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle title_icon">
            <i class="ti tabler-home" style="font-size:.78rem;"></i>
          </span>
          Unit
        </span>
      <button type="button" class="btn btn-sm btn-remove-unit d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_item">
        <i class="ti tabler-trash" style="font-size:.75rem;"></i> Remove
      </button>
    </div>
    <div class="p-3">
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Name</label>
          <input type="text" name="__PREFIX__[units][__UNIT__][unit_name]" class="form-control" placeholder="e.g. Suite A" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold small text-uppercase text-muted required">Unit Type</label>
          <select name="__PREFIX__[units][__UNIT__][unit_type]" class="form-select" required>
            <option value="" disabled selected>Select type</option>
            <option value="apartment">Apartment</option>
            <option value="office">Office</option>
            <option value="shop">Shop</option>
            <option value="studio">Studio</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
      <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms in this unit</p>
      <div class="unit-rooms-container"></div>
      <button type="button" class="btn btn-sm btn-add-unit-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
              data-room-count="0">
        <i class="ti tabler-plus me-1"></i> Add Room
      </button>
    </div>
  </div>
</template>
