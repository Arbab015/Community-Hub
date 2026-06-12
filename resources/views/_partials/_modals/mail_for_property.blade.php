<div class="modal fade" id="mailForProperty" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">

      <!-- Header -->
      <div class="modal-header bg-primary text-white border-0">
        <div class="w-100 text-center mb-2">
          <h5 class=" mb-0 text-white">Contact Property Owner</h5>
          <small class="opacity-75">
            Send your inquiry directly to the property owner
          </small>
        </div>

        <button type="button"
                class="btn-close btn-close-white position-absolute end-0 me-3"
                data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="{{route('property.send.email')}}" id="sell_rent_form">
        @csrf
        <input type="hidden" name="id" value="{{ $property->id }}">
        <input type="hidden" name="property_type" value="">
        <input type="hidden" name="property_for"
               value="{{$listing->listing_type}}">

        <div class="modal-body p-4">
          <!-- Name -->
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Full Name
            </label>

            <div class="input-group">
              <span class="input-group-text">
                <i class="ti tabler-user"></i>
              </span>

              <input type="text" class="form-control" placeholder="Enter your name">
            </div>
          </div>

          <!-- Phone -->
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Contact Number
            </label>

            <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti tabler-phone"></i>
                            </span>

              <input type="text"
                     name="contact_no"
                     class="form-control"
                     placeholder="+92 300 1234567">
            </div>
          </div>

          <!-- Email -->
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Email Address
            </label>

            <div class="input-group">
                            <span class="input-group-text">
                                <i class="ti tabler-mail"></i>
                            </span>

              <input type="email"
                     name="email"
                     class="form-control"
                     placeholder="john@example.com">
            </div>
          </div>

          <!-- User Type -->
          {{--       --}}

          <!-- Message -->
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Message
            </label>

            <textarea name="message"
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
                  class="btn btn-primary px-4 btn-sm">
            <i class="ti tabler-send me-1"></i>
            Send Email
          </button>

        </div>

      </form>

    </div>
  </div>
</div>
