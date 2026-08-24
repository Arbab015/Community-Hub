<div class="modal fade" id="printPlanModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-md">
    <form class="modal-content" method="POST" action="{{ route('payment_plans.print') }}" enctype="multipart/form-data">


      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Print Payment Plans</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      {{--      <input type="hidden" name="block_id" id="block_id" value="{{ $block->id }}">--}}
      <div class="modal-body">

        <div class="alert alert-info small mb-3">
          <i class="ti tabler-info-circle me-1"></i>
          Select the payment plans you want to print for the properties related to this block.
        </div>

        <div class="mb-3">
          <label for="payment_plan_select" class="form-label fw-semibold small text-muted">Payments
            Plans</label>
          <div class="select2-primary">
            <select id="select2Primary" name="plan_ids[]"
                    class="select2 form-select " multiple>
              @foreach($property_plans_templates ?? [] as $plan)
                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
              @endforeach
            </select>

          </div>
          <div id="societyInstallmentPreview" class="d-none">
            <div class="it-preview-list"></div>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer gap-2 justify-content-start">
        <button type="button" class="btn btn-label-secondary flex-grow-1 mx-0 " data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary flex-grow-1 mx-0" id="btn-section-block-overlay">
          Print plans
        </button>
      </div>

    </form>
  </div>
</div>
