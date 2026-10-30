<div class="modal fade" id="applyListingModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-md">
    <form class="modal-content" method="POST" action="{{ route('listing.block_store') }}" id="apply_listing_form">

      @csrf

      <input type="hidden" name="block_uuid" id="listing_block_uuid">
      <input type="hidden" name="block_uuids[]" id="listing_block_uuids">

      <div class="modal-header">
        <h5 class="modal-title">Apply Listing</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- LISTING TYPE -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold">Listing Type</label>
          <div class="d-flex gap-2">
            <input type="radio" class="btn-check" name="listing_type" id="listing_type_sell" value="sell" checked>
            <label class="btn btn-outline-primary flex-fill text-center py-2" for="listing_type_sell"
                   style="border-radius:10px;">
              <i class="ti tabler-currency-dollar d-block fs-5 mb-1"></i>
              <span style="font-size:.8rem;">Sell</span>
            </label>

            <input type="radio" class="btn-check" name="listing_type" id="listing_type_rent" value="rent">
            <label class="btn btn-outline-primary flex-fill text-center py-2" for="listing_type_rent"
                   style="border-radius:10px;">
              <i class="ti tabler-home-dollar d-block fs-5 mb-1"></i>
              <span style="font-size:.8rem;">Rent</span>
            </label>
          </div>
        </div>

        <!-- PROPERTY SIZE -->
        <div class="mb-3" id="listing_size_range_wrapper">
          <label class="form-label text-uppercase fw-semibold">Property Size</label>
          <select class="form-select" name="size_range" id="listing_size_range">
            <option value="">All</option>
            <optgroup label="Marla">
              <option value="0-5-marla">0 - 5 Marla</option>
              <option value="5-10-marla">5 - 10 Marla</option>
              <option value="10-20-marla">10 - 20 Marla</option>
            </optgroup>
            <optgroup label="Kanal">
              <option value="0-2-kanal">0 - 2 Kanal</option>
              <option value="2-4-kanal">2 - 4 Kanal</option>
              <option value="4-8-kanal">4 - 8 Kanal</option>
              <option value="8-kanal-plus">8+ Kanal</option>
            </optgroup>
          </select>
        </div>

        <!-- PROPERTY CATEGORY -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold">Property Category</label>
          <select class="form-select" name="category" id="listing_category">
            <option value="">All Categories</option>
            <option value="commercial">Commercial</option>
            <option value="non_commercial">Non-Commercial</option>
          </select>
        </div>

        <!-- CONSTRUCTION STATUS -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold">Construction Status</label>
          <select class="form-select" name="construction_status" id="listing_construction_status">
            <option value="">All Statuses</option>
            <option value="1">Constructed</option>
            <option value="0">Non-Constructed</option>
          </select>
        </div>

        <!-- PRICE (sell only) -->
        <div class="mb-3" id="listing_price_wrapper">
          <label class="form-label text-uppercase fw-semibold">Price (Rate per area)</label>
          <div class="input-group">
            <input type="number" name="price" id="listing_price" class="form-control" placeholder="e.g. 150000" min="0">
            <select name="price_unit" id="listing_price_unit" class="form-select" style="max-width:140px;">
              <option value="marla">Per Marla</option>
              <option value="square_yard">Per Sq. Yard</option>
            </select>
          </div>
        </div>

        <!-- SHOW PRICE -->
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="show_price" id="listing_show_price" value="1" checked>
          <label class="form-check-label small text-muted" for="listing_show_price">Show price publicly</label>
        </div>

        <hr class="my-3">

        <!-- CONTACT -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold">Contact</label>
          <div class="input-group">
            <span class="input-group-text"><i class="ti tabler-phone"></i></span>
            <input type="text" name="contact_no" class="form-control" placeholder="+92 300 0000000" required>
          </div>
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="show_contact_no" value="1" checked>
            <label class="form-check-label small text-muted">Show contact publicly</label>
          </div>
        </div>

        <!-- EMAIL -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold">Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="ti tabler-mail"></i></span>
            <input type="email" name="email" class="form-control" placeholder="contact@example.com" required>
          </div>
        </div>

        <hr class="my-3">

        <!-- DESCRIPTION -->
        <div class="mb-1">
          <label class="form-label text-uppercase fw-semibold">Description</label>
          <textarea name="description" class="form-control" rows="3" required placeholder="Key features..."></textarea>
        </div>

      </div>

      <div class="modal-footer border-0 pt-0 gap-2">
        <button type="button" class="btn btn-outline-secondary flex-grow-1 mx-0" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary flex-grow-1 mx-0">
          Apply Listing
        </button>
      </div>

    </form>
  </div>
</div>
