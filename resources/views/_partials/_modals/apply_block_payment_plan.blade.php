<div class="modal fade" id="applyBlockPaymentPlanModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" action="{{ route('blocks.apply_plan') }}" id="apply_block_payment_plan_form">

      @csrf

      <input type="hidden" name="block_ids[]" id="block_plan_block_ids">

      <div class="modal-header">
        <h5 class="modal-title">Apply Payment Plan to Blocks</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info py-2 px-3 mb-3">
          <span id="block_plan_selected_count">0</span> block(s) selected
        </div>

        {{-- COMMERCIAL SECTION --}}
        <div class="card mb-3 border-primary">
          <div class="card-body">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" name="commercial_enabled"
                     id="block_commercial_enabled" value="1">
              <label class="form-check-label fw-bold text-success" for="block_commercial_enabled">
                <i class="ti tabler-building-store me-1"></i> Commercial Properties
              </label>
            </div>

            <div id="block_commercial_fields" class="d-none">
              <div class="mb-3">
                <label class="form-label text-uppercase fw-semibold small">Payment Template</label>
                <select class="form-select" name="commercial_payment_template_id" id="block_commercial_payment_template">
                  <option value="">Select payment template</option>
                  @foreach($payment_templates ?? [] as $tpl)
                    @if($tpl->is_commercial)
                      <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                    @endif
                  @endforeach
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label text-uppercase fw-semibold small">Installment Template(s)</label>
                <select name="commercial_installment_template_ids[]" id="block_commercial_installment_select"
                        class="select2 form-select" multiple>
                  @foreach($installment_templates ?? [] as $tpl)
                    @if($tpl->is_commercial)
                      <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                    @endif
                  @endforeach
                </select>
              </div>

              <div class="row g-3">
                <div class="col-8">
                  <label class="form-label text-uppercase fw-semibold small">Property Size</label>
                  <select class="form-select" name="commercial_size_range">
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
                  <label class="form-label text-uppercase fw-semibold small">Size Unit</label>
                  <select class="form-select" name="commercial_size_unit">
                    <option value="marla">Marla</option>
                    <option value="square_yard">Sq. Yard</option>
                  </select>
                </div>
              </div>

              <div class="form-check form-switch mt-3 mb-0">
                <input class="form-check-input" type="checkbox" role="switch" name="commercial_apply_to_units"
                       id="block_commercial_apply_to_units" value="1">
                <label class="form-check-label small" for="block_commercial_apply_to_units">
                  Also apply to units within properties
                </label>
              </div>
            </div>
          </div>
        </div>

        {{-- NON-COMMERCIAL SECTION --}}
        <div class="card border-primary">
          <div class="card-body">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" name="non_commercial_enabled"
                     id="block_non_commercial_enabled" value="1">
              <label class="form-check-label fw-bold text-primary" for="block_non_commercial_enabled">
                <i class="ti tabler-home me-1"></i> Non-Commercial Properties
              </label>
            </div>

            <div id="block_non_commercial_fields" class="d-none">
              <div class="mb-3">
                <label class="form-label text-uppercase fw-semibold small">Payment Template</label>
                <select class="form-select" name="non_commercial_payment_template_id" id="block_non_commercial_payment_template">
                  <option value="">Select payment template</option>
                  @foreach($payment_templates ?? [] as $tpl)
                    @if(!$tpl->is_commercial)
                      <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                    @endif
                  @endforeach
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label text-uppercase fw-semibold small">Installment Template(s)</label>
                <select name="non_commercial_installment_template_ids[]" id="block_non_commercial_installment_select"
                        class="select2 form-select" multiple>
                  @foreach($installment_templates ?? [] as $tpl)
                    @if(!$tpl->is_commercial)
                      <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                    @endif
                  @endforeach
                </select>
              </div>

              <div class="row g-3">
                <div class="col-8">
                  <label class="form-label text-uppercase fw-semibold small">Property Size</label>
                  <select class="form-select" name="non_commercial_size_range">
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
                  <label class="form-label text-uppercase fw-semibold small">Size Unit</label>
                  <select class="form-select" name="non_commercial_size_unit">
                    <option value="marla">Marla</option>
                    <option value="square_yard">Sq. Yard</option>
                  </select>
                </div>
              </div>

              <div class="form-check form-switch mt-3 mb-0">
                <input class="form-check-input" type="checkbox" role="switch" name="non_commercial_apply_to_units"
                       id="block_non_commercial_apply_to_units" value="1">
                <label class="form-check-label small" for="block_non_commercial_apply_to_units">
                  Also apply to units within properties
                </label>
              </div>
            </div>
          </div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.getElementById('block_commercial_enabled').addEventListener('change', function () {
    document.getElementById('block_commercial_fields').classList.toggle('d-none', !this.checked);
  });
  document.getElementById('block_non_commercial_enabled').addEventListener('change', function () {
    document.getElementById('block_non_commercial_fields').classList.toggle('d-none', !this.checked);
  });
});
</script>
