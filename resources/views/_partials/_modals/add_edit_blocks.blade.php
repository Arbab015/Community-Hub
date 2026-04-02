<div class="modal fade" id="blockModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="modalTitle">Add Block</h4>
          <p class="text-muted mb-2">Create or edit a block for your society</p>
        </div>

        <form action="{{ route('blocks.store') }}" method="POST" id="blockForm">
          @csrf
          <div class="alert alert-warning alert-dismissible " role="alert">
            <h5 class="alert-heading mb-1">Ensure that these requirements are met</h5>
            <span>Please ensure that the block name you enter is unique within the selected society. Duplicate entries within the same society are not permitted, in order to maintain data accuracy and consistency.
             </span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>

          <input type="hidden" name="id" id="block_id">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="name">Block Name:</label>
            <input type="text" id="name" name="name" class="form-control "
                   placeholder="Enter block name" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="society_id">Select Society:</label>
            <select id="society_id" name="society_id" class="form-control" required>
              <option value="">-- Select Society --</option>
              @foreach($societies as $society)
                <option value="{{ $society->id }}">
                  {{ $society->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
              Save Block
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

