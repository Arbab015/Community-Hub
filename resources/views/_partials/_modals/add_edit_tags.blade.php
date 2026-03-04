<div class="modal fade" id="tagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content ">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="modalTitle">Add Tag</h4>
          <p class="text-muted">Create or edit tag with custom color</p>
        </div>

        <form action="{{ route('tags.store') }}" method="POST" id="tagForm">
          @csrf
          <input type="hidden" name="id" id="tagId">
          <input type="hidden" name="color" id="selectedColor">

          <div class="mb-4">
            <label class="form-label fw-semibold" for="name">Tag Name:</label>
            <input type="text" id="name" name="name" class="form-control form-control-lg"
              placeholder="Enter tag name" required>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold d-block mb-3">Choose Color:</label>
            <div id="color-picker-wrapper" style="width:100%">
              <div id="color-picker-classic"></div>
            </div>
          </div>
          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="ti ti tabler-check me-1"></i>
              Save Tag
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
