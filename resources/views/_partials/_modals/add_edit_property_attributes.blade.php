<div class="modal fade" id="attributeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="modalTitle">Add Attribute</h4>
          <p class="text-muted mb-2">Create or edit a attributes for your properties management.</p>
        </div>

        <form action="{{ route('attributes.store') }}" method="POST" id="blockForm">
          @csrf
          <div class="alert alert-warning alert-dismissible " role="alert">
            <h5 class="alert-heading mb-1">Ensure that these requirements are met</h5>
            <span>Please ensure that the block name you enter is unique within the selected society. Duplicate entries within the same society are not permitted, in order to maintain data accuracy and consistency.
             </span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <input type="hidden" name="owner_id" value="{{ $owner_id }}">
          <input type="hidden" name="id" id="attribute_id">

          <div class="mb-3">
            <label class="form-label fw-semibold" for="type">Attribute Type:</label>
            <select id="type" name="type" class="form-control" required>
              <option value="">-- Select Attribute type --</option>
              <option value="floor_type">Floor</option>
              <option value="unit_type">Unit</option>
              <option value="room_type">Room</option>
              <option value="amenity">Amenity</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="title">Attribute Title:</label>
            <input type="text" id="title" name="title" class="form-control "
                   placeholder="Enter title" required>
          </div>

          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
              Save Attribute
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>


