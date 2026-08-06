{{-- Plan Preview Modal — uses shared component --}}
<div class="modal fade" id="plan_preview_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment Plan Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        {{-- Actions row (moved above the print area) --}}
        <div class="d-flex justify-content-end gap-2 mb-4" id="plan_action_buttons">
          <button type="button" class="btn btn-label-secondary" id="plan_download_btn">
            <i class="ti tabler-download me-1"></i> Download
          </button>
          <button type="button" class="btn btn-label-secondary" id="plan_print_btn">
            <i class="ti tabler-printer me-1"></i> Print
          </button>
          <button type="button" class="btn btn-success" id="plan_send_btn">
            <i class="ti tabler-send me-1"></i> Send Invoice
          </button>
        </div>

        {{-- Content --}}
        <div class="row invoice-preview g-4">
          <div class="col-12" id="plan_print_area">
            <x-property.payment-plans-display
              :property="$property"
              :planPropertyName="$planPropertyName"
              :planPropertyAddress="$planPropertyAddress"
              :shadow="true"
              :tablePrefix="'plan'"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
