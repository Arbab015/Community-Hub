<template id="tpl-dim-row">
  <div class="dimension-row row g-3 mb-2 align-items-end rounded-3 p-2 mx-0">
    <div class="col-md-4">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-ruler text-muted"></i></span>
        <input type="text" name="__PREFIX__[dimensions][__DIM__][name]" required
               class="form-control border-start-0 " placeholder="e.g. Length, Width, Front, Right">
      </div>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-number text-muted"></i></span>
        <input type="number" name="__PREFIX__[dimensions][__DIM__][size]" step="0.01" required
               class="form-control border-start-0" placeholder="e.g 40, 12.21">
      </div>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
      <select name="__PREFIX__[dimensions][__DIM__][unit]" class="form-select" required>
        <option value="" disabled selected>Select Unit</option>
        <option value="feet">Feet</option>
        <option value="meter">Meter</option>
        <option value="yard">Yard</option>
      </select>
    </div>
    <div class="col-md-1">
      <button type="button" class="btn btn-remove-dim" title="Remove">
        <i class="ti tabler-x icon-lg text-danger"></i>
      </button>
    </div>
  </div>
</template>
