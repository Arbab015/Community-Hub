<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow p-4">
      <form id="replyForm" method="POST" action="{{ route('messages.reply') }}">
        @csrf
        <div class="modal-header px-0 border-0 pb-0">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="editBasicInfoModalLabel">
            <span class="d-inline-block bg-primary rounded hr_bar"></span>
            Reply On Message
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body px-0 pt-3">
          <input type="hidden" name="message_id" id="reply_message_id">

          <div class="mb-3">
            <label for="reply_to_email" class="form-label fw-semibold small text-uppercase text-muted">To</label>
            <input type="email" class="form-control" id="reply_to_email" name="to_email" readonly>
          </div>

          <div class="mb-3">
            <label for="reply_subject" class="form-label fw-semibold small text-uppercase text-muted">Subject</label>
            <input type="text" class="form-control" id="reply_subject" name="subject"
                   value="Re: Your message to us" required>
          </div>

          <div class="mb-3">
            <label for="reply_body" class="form-label fw-semibold small text-uppercase text-muted">Message</label>
            <textarea class="form-control" id="reply_body" name="body" rows="6" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-paper-plane me-1"></i> Send Reply
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
