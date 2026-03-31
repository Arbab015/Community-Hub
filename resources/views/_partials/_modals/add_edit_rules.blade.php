<div class="modal fade" id="ruleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="modalTitle">Add Rule</h4>
          <p class="text-muted">Create or edit a rule for your society</p>
        </div>

        <form action="{{ route('rules.store') }}" method="POST" id="ruleForm">
          @csrf
          <input type="hidden" name="id" id="ruleId">
          <input type="hidden" name="society_owner_id" value="{{ auth()->id() }}">

          <div class="mb-3">
            <label class="form-label fw-semibold" for="name">Rule Name:</label>
            <input type="text" id="name" name="name" class="form-control "
                   placeholder="Enter rule name" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="description">Description:</label>
            <textarea id="description" name="description" class="form-control form-control-lg"
                      placeholder="Enter description" rows="3" required></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold d-block mb-2">Related To:</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="related_to[]" value="discussions" id="relatedDiscussions">
              <label class="form-check-label" for="relatedDiscussions">Discussions</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="related_to[]" value="suggestions" id="relatedSuggestions">
              <label class="form-check-label" for="relatedSuggestions">Suggestions</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="related_to[]" value="issues" id="relatedIssues">
              <label class="form-check-label" for="relatedIssues">Issues</label>
            </div>
          </div>

          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="ti tabler-check me-1"></i>
              Save Rule
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
