<div class="form-repeater dimensions-inline-repeater">
  <div data-repeater-list="dimensions">
    <div data-repeater-item>
      <div class="row g-2 mb-2 align-items-end rounded-3 px-2 py-1 mx-0 dim-row">

        <div class="col-5 col-sm-5">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-end-0" style="font-size:.78rem;">
              <i class="ti tabler-ruler text-muted"></i>
            </span>
            <input type="text" name="name"
                   class="form-control form-control-sm border-start-0"
                   placeholder="Side (e.g. Length)"
                   style="font-size:.82rem;">
          </div>
        </div>

        <div class="col-3 col-sm-3">
          <div class="input-group input-group-sm">
            <input type="number" name="size" step="0.01"
                   class="form-control form-control-sm"
                   placeholder="Size"
                   style="font-size:.82rem;">
          </div>
        </div>

        <div class="col-3 col-sm-3">
          <select name="unit" class="form-select form-select-sm" style="font-size:.78rem;">
            <option value="" disabled selected>Unit</option>
            <option value="feet">Feet</option>
            <option value="square_feet">Sq.Ft</option>
            <option value="meter">Meter</option>
            <option value="yard">Yard</option>
            <option value="marla">Marla</option>
            <option value="kanal">Kanal</option>
          </select>
        </div>

        <div class="col-1 col-sm-1 text-center">
          <button type="button" data-repeater-delete class="btn btn-sm p-0 d-inline-flex align-items-center justify-content-center text-danger bg-danger bg-opacity-10 rounded-circle" style="width:24px;height:24px;" title="Remove">
            <i class="ti tabler-x" style="font-size:.75rem;"></i>
          </button>
        </div>

      </div>
    </div>
  </div>
  <button type="button" data-repeater-create
          class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 mt-1 rounded-pill px-2 py-1"
          style="font-size:.72rem;">
    <i class="ti tabler-plus" style="font-size:.8rem;"></i> Add Dimension
  </button>
</div>
