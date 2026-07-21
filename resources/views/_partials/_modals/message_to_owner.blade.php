<div class="modal fade" id="message_to_owner" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">

      <!-- Header -->
      <div class="modal-header border-0">
        <div class="w-100 text-center mb-2">
          <h4 class="mb-0 fw-bold" id="mailForPropertyLabel">Contact Property Owner</h4>
          <small class="opacity-75">
            Send your inquiry directly to the property owner
          </small>
        </div>

        <button type="button"
                class="btn-close btn-close-white position-absolute end-0 me-3"
                data-bs-dismiss="modal">
        </button>
      </div>

      <form method="POST" action="{{ route('inquiry.store') }}" id="sell_rent_form">
        @csrf
        <input type="hidden" name="redirect_url" value="{{ url()->full() }}">
        <input type="hidden" name="listing_id" id="inquiryListingId" value="">
        @foreach($searchInputs ?? [] as $key => $value)
          <input type="hidden" name="search[{{ $key }}]" value="{{ $value }}">
        @endforeach
        <div class="modal-body p-4">
          <!-- Name -->
          <div class="mb-3">
            <label class="form-label fw-semibold required">
              Full Name
            </label>

            <div class="input-group">
              <span class="input-group-text">
                <i class="ti tabler-user"></i>
              </span>

              <input type="text" name="name" id="inquiryName" class="form-control" required
                     placeholder="Enter your name">
            </div>
          </div>

          <!-- Phone -->
          <div class="mb-3">
            <label class="form-label fw-semibold required">
              Contact Number
            </label>

            <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti tabler-phone"></i>
                            </span>

              <input type="text" required
                     name="contact_number"
                     id="inquiryContact"
                     class="form-control"
                     placeholder="+92 300 1234567">
            </div>
          </div>

          <!-- Email -->
          <div class="mb-3">
            <label class="form-label fw-semibold required">
              Email Address
            </label>

            <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti tabler-mail"></i>
                            </span>

              <input type="email" required
                     name="email"
                     id="inquiryEmail"
                     class="form-control"
                     placeholder="john@example.com">
            </div>
          </div>


          <!-- Message -->
          <div class="mb-3">
            <label class="form-label fw-semibold required">
              Message
            </label>

            <textarea name="message" required
                      id="inquiryMessage"
                      class="form-control"
                      rows="4">I'm interested in this property. Please contact me at your earliest convenience.</textarea>
          </div>


        </div>

        <!-- Footer -->
        <div class="modal-footer border-0 px-4 pb-4">

          <button type="button"
                  class="btn btn-light btn-sm"
                  data-bs-dismiss="modal">
            Cancel
          </button>

          <button type="submit"
                  class="btn btn-primary px-4 btn-sm"
                  id="inquirySubmitBtn">
            <i class="ti tabler-send me-1"></i>
            Send Message
          </button>

        </div>

      </form>

    </div>
  </div>
</div>
