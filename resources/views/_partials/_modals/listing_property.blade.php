<div class="modal fade" id="sell_rent_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          <span id="modal_title">List Property</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="{{ route('listing.store') }}" id="listing_form">
        @csrf
        <input type="hidden" name="type" id="type" value="">
        <input type="hidden" name="id" id="id" value="">
        <input type="hidden" name="ids" id="ids" value="">

        <div class="modal-body pt-3">

          <!-- BULK NOTICE -->
          <div class="alert alert-primary d-none py-2 small mb-3" id="bulk_notice">
            Applying to <strong id="count">0</strong> properties.
          </div>

          <!-- LISTING TYPE -->
          <div class="mb-4">
            <label class="form-label fw-semibold small text-uppercase text-muted">Listing Type</label>
            <div class="d-flex gap-2">
              <input type="radio" class="btn-check" name="listing_type" id="type_sell" value="sell" checked>
              <label class="btn btn-outline-primary flex-fill text-center py-2" for="type_sell" style="border-radius:10px;">
                <i class="ti tabler-currency-dollar d-block fs-5 mb-1"></i>
                <span style="font-size:.8rem;">Sell</span>
              </label>

              <input type="radio" class="btn-check" name="listing_type" id="type_rent" value="rent">
              <label class="btn btn-outline-primary flex-fill text-center py-2" for="type_rent" style="border-radius:10px;">
                <i class="ti tabler-home-dollar d-block fs-5 mb-1"></i>
                <span style="font-size:.8rem;">Rent</span>
              </label>
            </div>
          </div>

          <!-- SINGLE MODE: PRICE -->
          <div id="single_price" class="mb-1">
            <label class="form-label fw-semibold small required">Price</label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti tabler-currency-dollar"></i></span>
              <input type="number" name="price" id="price" class="form-control" placeholder="e.g. 5000000" min="0">
              <select name="unit" id="unit" class="form-select" style="max-width:110px;">
                <option value="PKR">PKR</option>
                <option value="USD">USD</option>
                <option value="AED">AED</option>
                <option value="SAR">SAR</option>
              </select>
            </div>
          </div>

          <!-- BULK MODE: RATE -->
          <div id="bulk_rate" class="mb-1 d-none">
            <label class="form-label fw-semibold small required">Price per Unit Area</label>
            <div class="input-group mb-2">
              <span class="input-group-text"><i class="ti tabler-currency-dollar"></i></span>
              <input type="number" name="rate" id="rate" class="form-control" placeholder="e.g. 150000" min="0">
              <select name="unit" id="rate_unit" class="form-select" style="max-width:100px;">
                <option value="PKR">PKR</option>
                <option value="USD">USD</option>
                <option value="AED">AED</option>
                <option value="SAR">SAR</option>
              </select>
            </div>
            <select name="rate_unit" id="area_unit" class="form-select">
              <option value="marla">Per Marla</option>
              <option value="square_yard">Per Square Yard</option>
            </select>
            <small class="text-muted d-block mt-1">Price calculated from each property's size.</small>
          </div>

          <div class="form-check form-switch mt-2 mb-3">
            <input class="form-check-input" type="checkbox" name="show_price" id="show_price" value="1" checked>
            <label class="form-check-label small text-muted" for="show_price">Show price publicly</label>
          </div>

          <hr class="my-3">

          <!-- CONTACT -->
          <div class="mb-3">
            <label class="form-label fw-semibold small required">Contact</label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti tabler-phone"></i></span>
              <input type="text" name="contact_no" id="contact" class="form-control" placeholder="+92 300 0000000">
            </div>
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" name="show_contact_no" id="show_contact" value="1" checked>
              <label class="form-check-label small text-muted" for="show_contact">Show contact publicly</label>
            </div>
          </div>

          <!-- EMAIL -->
          <div class="mb-3">
            <label class="form-label fw-semibold small required">Email</label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti tabler-mail"></i></span>
              <input type="email" name="email" id="email" class="form-control" placeholder="contact@example.com">
            </div>
          </div>

          <hr class="my-3">

          <!-- DESCRIPTION -->
          <div class="mb-1">
            <label class="form-label fw-semibold small required">Description</label>
            <textarea name="description" id="description" class="form-control" rows="3" placeholder="Key features..."></textarea>
          </div>

        </div>

        <div class="modal-footer border-0 pt-0 gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary d-flex align-items-center gap-1">
            <i class="ti tabler-check"></i> <span id="btn_text">Submit</span>
          </button>
        </div>

      </form>
    </div>
  </div>
</div>
