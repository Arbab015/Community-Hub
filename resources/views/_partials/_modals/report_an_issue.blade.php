<div class="modal fade" id="report_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header flex-column align-items-center text-center">
        <h5 class="modal-title fw-semibold" id="modalCenterTitle">Report An Issue</h5>
        <p class="text-muted small mb-0">Help us understand the problem and we will do our best to resolve it.</p>
        <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal"
          aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <form id="reportForm">
          @csrf
          <input type="hidden" id="report_id" name="reportable_id">
          <input type="hidden" id="report_type" name="reportable_type">
          <div class="form-control-validation">
            <label class="form-label fw-bolder required" for="type">Report Type:</label>
            <select id="country" name="type" class="form-select select2" Required data-allow-clear="true">
              <option value="">Select report type
              </option>
              <option value="spam">
                Spam</option>
              <option value="misleading">
                Misleading</option>
              <option value="hate_speech">
                Hate speech</option>
              <option value="violence">
                Violence</option>
              <option value="harassment">
                Harassment</option>
              <option value="adult_content">
                Adult content</option>
              <option value="scam">
                Scam</option>
              <option value="illegal_activity">
                Illegal activity</option>
              <option value="off_topic">
                Off topic</option>
              <option value="other">
                Other
              </option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bolder required" for="reason">Describe the Reason:</label>
            <textarea name="reason" class="form-control" rows="5" style="resize: none;" id="reason_id" required
              placeholder="Please describe the issue in detail..."></textarea>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary">Submit Report</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
