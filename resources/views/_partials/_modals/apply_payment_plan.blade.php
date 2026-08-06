<div class="modal fade" id="applyPaymentPlanModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-md">
    <form class="modal-content" method="POST" action="{{ route('payment_plan.apply') }}" id="apply_payment_plan_form">

      @csrf

      <input type="hidden" name="ids" id="apply_plan_ids">
      <input type="hidden" name="planable_type" id="apply_plan_type">

      <div class="modal-header">
        <h5 class="modal-title">Apply Payment Plan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info py-2 px-3 mb-3">
          <span id="apply_plan_selected_count">0</span> item(s) selected
        </div>

        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold">Payment Template</label>
          <select class="form-select" name="payment_template_id" id="payment_template_select" required>
            <option value="">Select payment template</option>
            @foreach($payment_templates ?? [] as $payment_template)
              <option value="{{ $payment_template->id }}">{{ $payment_template->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label for="installment_template_select" class="form-label fw-semibold small text-muted">Installment
            Template(s)</label>
          <div class="select2-primary">
            <select id="select2Primary" name="installment_template_ids[]"
                    class="select2 form-select" multiple>
              @foreach($installment_templates ?? [] as $installment_template)
                <option value="{{ $installment_template->id }}">{{ $installment_template->name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold">Property Size</label>
          <select class="form-select" name="size_range" id="size_range">
            <option value="">Select size range</option>
            <optgroup label="Marla">
              <option value="0-5-marla">0 - 5 Marla</option>
              <option value="5-10-marla">5 - 10 Marla</option>
              <option value="10-20-marla">10 - 20 Marla</option>
            </optgroup>
            <optgroup label="Kanal">
              <option value="0-2-kanal">0 - 2 Kanal</option>
              <option value="2-4-kanal">2 - 4 Kanal</option>
              <option value="4-8-kanal">4 - 8 Kanal</option>
              <option value="8-kanal-plus">8+ Kanal</option>
            </optgroup>
          </select>
        </div>

        <div class="form-check form-switch mb-1">
          <input class="form-check-input" type="checkbox" role="switch" name="apply_to_units" id="apply_to_units"
                 value="1">
          <label class="form-check-label" for="apply_to_units">
            Also apply to units within selected properties
          </label>
        </div>
      </div>

      <div class="modal-footer gap-2 justify-content-start">
        <button type="button" class="btn btn-label-secondary flex-grow-1 mx-0" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary flex-grow-1 mx-0">
          Apply Payment Plan
        </button>
      </div>

    </form>
  </div>
</div>
