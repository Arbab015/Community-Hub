<div class="modal fade" id="applyBlockPaymentPlanModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form class="modal-content" method="POST" action="{{ route('blocks.apply_plan') }}"
          id="apply_block_payment_plan_form">
      @csrf
      <div class="modal-header">
        <h4 class="modal-title">Apply Payment Plan to Blocks</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <!-- Selected Blocks Info -->
        <div class="block-apply-info mb-4">
          <i class="ti tabler-building-estate"></i>
          <span><strong id="block_plan_selected_count">0</strong> block(s) selected</span>
        </div>

        <!-- PROPERTY TYPE -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold">Property Type</label>
          <div class="d-flex gap-2">
            <input type="radio" class="btn-check" name="commercial" id="plan_commercial_1" value="1" checked>
            <label class="btn btn-outline-primary flex-fill text-center py-2" for="plan_commercial_1"
                   style="border-radius:10px;">
              <i class="ti tabler-building-store d-block fs-5 mb-1"></i>
              <span style="font-size:.8rem;">Commercial</span>
            </label>

            <input type="radio" class="btn-check" name="commercial" id="plan_commercial_0" value="0">
            <label class="btn btn-outline-primary flex-fill text-center py-2" for="plan_commercial_0"
                   style="border-radius:10px;">
              <i class="ti tabler-home d-block fs-5 mb-1"></i>
              <span style="font-size:.8rem;">Non-Commercial</span>
            </label>
          </div>
        </div>

        <!-- CONSTRUCTION STATUS -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold">Construction Status</label>
          <div class="d-flex gap-2">
            <input type="radio" class="btn-check" name="constructed" id="plan_constructed_1" value="1" checked>
            <label class="btn btn-outline-primary flex-fill text-center py-2" for="plan_constructed_1"
                   style="border-radius:10px;">
              <i class="ti tabler-building-bridge-2 d-block fs-5 mb-1"></i>
              <span style="font-size:.8rem;">Constructed</span>
            </label>

            <input type="radio" class="btn-check" name="constructed" id="plan_constructed_0" value="0">
            <label class="btn btn-outline-primary flex-fill text-center py-2" for="plan_constructed_0"
                   style="border-radius:10px;">
              <i class="ti tabler-building-skyscraper d-block fs-5 mb-1"></i>
              <span style="font-size:.8rem;">Non-Constructed</span>
            </label>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <!-- PAYMENT TEMPLATE -->
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-semibold">Payment Template</label>
            <select class="form-select" name="payment_template_id">
              <option value="">Select payment template</option>
              @foreach($payment_templates ?? [] as $tpl)
                <option value="{{ $tpl->id }}"
                        data-commercial="{{ (int) (bool) $tpl->is_commercial }}"
                        data-constructed="{{ (int) (bool) $tpl->is_constructed }}">
                  {{ $tpl->name }}
                </option>
              @endforeach
            </select>
          </div>

          <!-- INSTALLMENT TEMPLATE(S) -->
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-semibold">Installment Template(s)</label>
            <select name="installment_template_ids[]" class="select2 form-select" multiple>
              @foreach($installment_templates ?? [] as $tpl)
                <option value="{{ $tpl->id }}"
                        data-commercial="{{ (int) (bool) $tpl->is_commercial }}"
                        data-constructed="{{ (int) (bool) $tpl->is_constructed }}">
                  {{ $tpl->name }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <!-- SIZE RANGE -->
        <div class="row g-3 mb-3">
          <div class="col-8">
            <label class="form-label text-uppercase fw-semibold">Property Size</label>
            <select class="form-select" name="size_range">
              <option value="">All</option>
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
          <div class="col-4">
            <label class="form-label text-uppercase fw-semibold">Size Unit</label>
            <select class="form-select" name="size_unit">
              <option value="marla">Marla</option>
              <option value="square_yard">Sq. Yard</option>
            </select>
          </div>
        </div>

        <!-- APPLY TO UNITS -->
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" name="apply_to_units"
                 id="block_plan_apply_to_units" value="1">
          <label class="form-check-label small fw-semibold" for="block_plan_apply_to_units">
            Also apply to units within properties
          </label>
        </div>
      </div>

      <div class="modal-footer gap-2 justify-content-start">
        <button type="button" class="btn btn-label-secondary flex-grow-1 mx-0" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary flex-grow-1 mx-0">
          <i class="ti tabler-device-floppy me-1"></i> Apply Payment Plan
        </button>
      </div>
    </form>
  </div>
</div>

@push('styles')
  <style>
    #applyBlockPaymentPlanModal .modal-content {
      border: 0;
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: 0 24px 48px -12px rgba(0, 0, 0, 0.35);
    }

    #applyBlockPaymentPlanModal .modal-body {
      padding: 1.5rem !important;
    }

    .block-apply-info {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      background: linear-gradient(135deg, rgba(105, 108, 255, 0.09), rgba(105, 108, 255, 0.03));
      border: 1px solid rgba(105, 108, 255, 0.22);
      border-radius: 0.75rem;
      padding: 0.75rem 1rem;
      color: #5a5cff;
      font-size: 0.9rem;
    }

    #applyBlockPaymentPlanModal .form-select,
    #applyBlockPaymentPlanModal .form-control {
      border-radius: 0.6rem;
      border-color: #dde1e8;
      font-size: 0.9rem;
    }
  </style>
@endpush

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var modalRoot = document.getElementById('applyBlockPaymentPlanModal');
      if (!modalRoot) return;

      function currentCombo() {
        var typeInput = modalRoot.querySelector('input[name="commercial"]:checked');
        var constructedInput = modalRoot.querySelector('input[name="constructed"]:checked');

        return {
          commercial: typeInput && typeInput.value === '1' ? 1 : 0,
          constructed: constructedInput && constructedInput.value === '1' ? 1 : 0
        };
      }

      function filterTemplates() {
        var combo = currentCombo();

        modalRoot.querySelectorAll('select[name="payment_template_id"], select[name="installment_template_ids[]"]')
          .forEach(function(select) {
            select.querySelectorAll('option[data-commercial]').forEach(function(opt) {
              var matches = parseInt(opt.dataset.commercial, 10) === combo.commercial &&
                parseInt(opt.dataset.constructed, 10) === combo.constructed;
              opt.hidden = !matches;
              opt.disabled = !matches;
              if (!matches && opt.selected) opt.selected = false;
            });

            if (typeof $ !== 'undefined' && $(select).data('select2')) {
              $(select).trigger('change');
            }
          });
      }

      modalRoot.querySelectorAll('input[name="commercial"], input[name="constructed"]').forEach(function(input) {
        input.addEventListener('change', filterTemplates);
      });

      filterTemplates();
    });
  </script>
@endpush
