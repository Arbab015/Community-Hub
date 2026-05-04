<template id="tpl-floor">
  <div class="floor-item border rounded-3 mb-3 overflow-hidden shadow-sm">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom floor_header">
        <span class="d-flex align-items-center gap-2 fw-bold text-primary floor_title">
          <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle floor_icon">
            <i class="ti tabler-building" style="font-size:.85rem;"></i>
          </span>
          Floor
        </span>
      <button type="button" class="btn btn-sm btn-remove-floor d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 remove_floor">
        <i class="ti tabler-trash" style="font-size:.8rem;"></i> Remove Floor
      </button>
    </div>
    <div class="p-3">
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold small text-uppercase text-muted required">Floor Type</label>
          <select name="__PREFIX__[floor_type]" class="form-select" required>
            <option value="" disabled selected>Select Floor Type</option>
            <option value="basement">Basement</option>
            <option value="ground">Ground Floor</option>
            <option value="first floor">1st Floor</option>
            <option value="second floor">2nd Floor</option>
            <option value="third floor">3rd Floor</option>
            <option value="fourth floor">4th Floor</option>
            <option value="fifth floor">5th Floor</option>
            <option value="top floor">Top Floor</option>
          </select>
        </div>
      </div>
      <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Floor Dimensions</label>
      {{--        <div class="mb-3 dim-block">--}}
      {{--          <div class="dim-rows"></div>--}}
      {{--          <button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-dim" style="font-size:.72rem;">--}}
      {{--            <i class="ti tabler-plus me-1"></i> Add Dimension--}}
      {{--          </button>--}}
      {{--        </div>--}}
      <div class="units-section-wrapper d-none">
        <div class="form-check mt-3 mb-2">
          <input class="form-check-input has_units_check" type="checkbox" name="__PREFIX__[has_units]" value="1">
          <label class="form-check-label fw-semibold">
            This floor has units (apartments, offices, shops, etc.)
          </label>
        </div>
        <div class="units-section d-none mt-2">
          <p class="small fw-semibold text-uppercase text-muted mb-2" style="font-size:.7rem;letter-spacing:.05em;">Units</p>
          <div class="floor-units-container"></div>
          <button type="button" class="btn btn-sm btn-add-unit d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_unit_btn"
                  data-unit-count="0">
            <i class="ti tabler-plus me-1"></i> Add Unit
          </button>
        </div>
      </div>
      <div class="floor-rooms-section mt-3">
        <p class="small fw-semibold text-uppercase text-muted mb-2 rooms_title">Rooms on this floor</p>
        <div class="floor-rooms-container"></div>
        <button type="button" class="btn btn-sm btn-add-floor-room d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-3 py-1 add_room_btn"
                data-room-count="0">
          <i class="ti tabler-plus me-1"></i> Add Room
        </button>
      </div>
    </div>
  </div>
</template>
