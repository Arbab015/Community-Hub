<div class="modal fade" id="printBlockPaymentPlanModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Print Block Payment Plan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="alert alert-info small mb-3">
          <i class="ti tabler-info-circle me-1"></i>
          Choose the plan type and enter the property size. The plan is printed for the whole
          block at the price of the matching size range.
        </div>

        {{-- PROPERTY FEATURES (premiums) --}}
        <div class="mb-3">
          <label class="form-label text-uppercase fw-bold small">Property Features</label>
          <p class="text-muted small mb-2">Select applicable features to include premiums in the payment plan.</p>
          <div class="d-flex flex-wrap gap-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="block_print_corner" value="1">
              <label class="form-check-label small fw-semibold" for="block_print_corner">
                <i class="ti tabler-corner-down-right-double text-primary me-1"></i> Corner
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="block_print_park_facing" value="1">
              <label class="form-check-label small fw-semibold" for="block_print_park_facing">
                <i class="ti tabler-trees text-success me-1"></i> Park Facing
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="block_print_main_boulevard" value="1">
              <label class="form-check-label small fw-semibold" for="block_print_main_boulevard">
                <i class="ti tabler-road text-warning me-1"></i> Main Boulevard
              </label>
            </div>
          </div>
        </div>

        {{-- PLAN TYPE --}}
        <div class="mb-3">
          <label class="form-label text-uppercase fw-bold small">Payment Plan Type</label>
          <div class="d-flex gap-2">
            <input type="radio" class="btn-check" name="block_print_type" id="block_print_commercial"
                   value="commercial">
            <label class="btn btn-outline-success flex-fill text-center py-2" for="block_print_commercial"
                   style="border-radius:10px;">
              <i class="ti tabler-building-store d-block fs-5 mb-1"></i>
              <span style="font-size:.8rem;">Commercial</span>
            </label>

            <input type="radio" class="btn-check" name="block_print_type" id="block_print_non_commercial"
                   value="non_commercial" checked>
            <label class="btn btn-outline-primary flex-fill text-center py-2" for="block_print_non_commercial"
                   style="border-radius:10px;">
              <i class="ti tabler-home d-block fs-5 mb-1"></i>
              <span style="font-size:.8rem;">Non-Commercial</span>
            </label>
          </div>
        </div>

        {{-- SPECIFIC SIZE + LIVE CHECK --}}
        <div class="row g-3 mb-3">
          <div class="col-8">
            <label class="form-label text-uppercase fw-semibold small">Property Size</label>
            <input type="number" class="form-control" id="block_print_size" placeholder="e.g. 5" min="0" step="0.01">
          </div>
          <div class="col-4">
            <label class="form-label text-uppercase fw-semibold small">Unit</label>
            <select class="form-select" id="block_print_unit">
              <option value="marla">Marla</option>
              <option value="square_yard">Sq. Yard</option>
            </select>
          </div>
        </div>

        {{-- LIVE AVAILABILITY STATUS --}}
        <div id="block_print_status" class="d-none">
          <div class="spinner-border spinner-border-sm me-2 d-none" id="block_print_loading"></div>
          <span id="block_print_status_text"></span>
        </div>

      </div>

      <div class="modal-footer gap-2 justify-content-start">
        <button type="button" class="btn btn-label-secondary flex-grow-1 mx-0" data-bs-dismiss="modal">
          Cancel
        </button>
        <a href="#" class="btn btn-primary flex-grow-1 mx-0 d-none" id="block_print_submit" target="_blank">
          <i class="ti tabler-printer me-1"></i> Print Plan
        </a>
      </div>

    </div>
  </div>
</div>