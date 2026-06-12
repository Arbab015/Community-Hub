<div class="modal fade" id="sell_rent_modal" tabindex="-1" aria-labelledby="sellRentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="sellRentModalLabel">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          List Property
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" action="{{route('listing.store')}}" id="sell_rent_form">
        @csrf
        <input type="hidden" name="type" value="">
        <input type="hidden" name="id" value="">

        <div class="modal-body pt-3">

          {{-- Listing Type --}}
          <div class="mb-4">
            <label class="form-label fw-semibold small text-uppercase text-muted required"
                   style="letter-spacing:.06em;">Listing
              Type</label>
            <div class="d-flex gap-2">
              <input type="radio" class="btn-check" name="listing_type" id="type_sell" value="sell">
              <label class="btn btn-outline-primary flex-fill text-center py-2" for="type_sell"

                     style="border-radius:10px;">
                <i class="ti tabler-currency-dollar d-block fs-5 mb-1"></i>
                <span style="font-size:.8rem;">Sell</span>
              </label>

              <input type="radio" class="btn-check" name="listing_type" id="type_rent" value="rent">
              <label class="btn btn-outline-primary flex-fill text-center py-2" for="type_rent"
                     {{ optional($property->listing)->listing_type === "rent" ? 'checked' : ''}}
                     style="border-radius:10px;">
                <i class="ti tabler-home-dollar d-block fs-5 mb-1"></i>
                <span style="font-size:.8rem;">Rent</span>
              </label>
            </div>
          </div>

          {{-- Price --}}
          <div class="mb-1">
            <label class="form-label fw-semibold small required" for="listing_price">Price</label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti tabler-currency-dollar"></i></span>
              {{-- Price --}}
              <input type="number" name="price" id="listing_price" class="form-control" placeholder="e.g. 5000000"
                     required min="0"
              >

              <select name="unit" class="form-select" style="max-width:90px;">
                <option value="PKR">PKR</option>
                <option value="USD">USD</option>
                <option value="AED">AED</option>
              </select>
            </div>
            <div class="form-check form-switch mt-2">
              {{-- Show Price --}}
              <input class="form-check-input" type="checkbox" name="show_price" id="show_price" value="1">
              <label class="form-check-label small text-muted" for="show_price">Show price to public</label>
            </div>
          </div>

          <hr class="my-3">

          {{-- Contact --}}
          <div class="mb-1">
            <label class="form-label fw-semibold small required" for="listing_contact">Contact Number</label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti tabler-phone"></i></span>
              {{-- Contact --}}
              <input type="text" name="contact_no" id="listing_contact" class="form-control" required
                     placeholder="+92 300 0000000">
            </div>
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" name="show_contact_no" id="show_contact" value="1">
              <label class="form-check-label small text-muted" for="show_contact">Show contact to public</label>
            </div>
          </div>

          {{-- Email --}}
          <div class="mb-1 mt-3">
            <label class="form-label fw-semibold small required" for="listing_email">Email <span
                class="text-muted fw-normal"></span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti tabler-mail"></i></span>
              <input type="email" name="email" id="listing_email" class="form-control" required
                     placeholder="contact@example.com">
            </div>
          </div>

          <hr class="my-3">

          {{-- Description --}}
          <div class="mb-1">
            <label class="form-label fw-semibold small required" for="listing_description ">Description </label>
            {{-- Description --}}
            <textarea name="description" id="listing_description" class="form-control" rows="3" required
                      placeholder="Describe key features, nearby amenities, deal terms..."></textarea>
          </div>

        </div>

        <div class="modal-footer border-0 pt-0 gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary d-flex align-items-center gap-1 px-4">
            <i class="ti tabler-check"></i> Submit Listing
          </button>
        </div>

      </form>
    </div>
  </div>
</div>
