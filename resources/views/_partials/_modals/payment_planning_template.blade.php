<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="modalTitle">Add Payment Planning Template </h4>
          <p class="text-muted mb-2">Create or edit a payment planning template</p>
        </div>

        <form action="{{ route('payment-planning-templates.store') }}" method="POST" id="templateForm">
          @csrf
          <input type="hidden" name="id" id="templateId">
          <input type="hidden" name="owner_id" value="{{ auth()->id() }}">

          <div class="mb-4">
            <label class="form-label fw-semibold" for="template_name">Template Name:</label>
            <input type="text" id="template_name" name="name" class="form-control"
                   placeholder="Enter template name" required>
          </div>

          <hr class="my-4">

          <!-- Booking Fee -->
          <div class="fee-block mb-4 pb-3 border-bottom" data-fee="booking_fee">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label fw-semibold mb-0">Booking Fee</label>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch" id="booking_fee_enable">
                <label class="form-check-label" for="booking_fee_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none">
              <div class="row align-items-center">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="booking_fee" name="booking_fee"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0 mt-2 mt-md-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="booking_fee_in_per" name="booking_fee_in_per" value="1" disabled>
                    <label class="form-check-label" for="booking_fee_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Development Fee -->
          <div class="fee-block mb-4 pb-3 border-bottom" data-fee="development_fee">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label fw-semibold mb-0">Development Fee</label>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch"
                       id="development_fee_enable">
                <label class="form-check-label" for="development_fee_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none">
              <div class="row align-items-center mb-2">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="development_fee" name="development_fee"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0 mt-2 mt-md-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="development_fee_in_per" name="development_fee_in_per" value="1" disabled>
                    <label class="form-check-label" for="development_fee_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="development_fee_surplus" name="development_fee_surplus" value="1" disabled>
                <label class="form-check-label" for="development_fee_surplus">Add in listing price</label>
              </div>
            </div>
          </div>

          <!-- Down Payment -->
          <div class="fee-block mb-4 pb-3 border-bottom" data-fee="down_payment">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label fw-semibold mb-0">Down Payment</label>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch"
                       id="down_payment_enable">
                <label class="form-check-label" for="down_payment_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none">
              <div class="row align-items-center">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="down_payment" name="down_payment"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0 mt-2 mt-md-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="down_payment_in_per" name="down_payment_in_per" value="1" disabled>
                    <label class="form-check-label" for="down_payment_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
            </div>
          </div>


          <!-- Confirmation Fee -->
          <div class="fee-block mb-4 pb-3 border-bottom" data-fee="confirmation_fee">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label fw-semibold mb-0">Confirmation Fee</label>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch"
                       id="confirmation_fee_enable">
                <label class="form-check-label" for="confirmation_fee_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none">
              <div class="row align-items-center">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="confirmation_fee" name="confirmation_fee"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0 mt-2 mt-md-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="confirmation_fee_in_per" name="confirmation_fee_in_per" value="1" disabled>
                    <label class="form-check-label" for="confirmation_fee_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Possession Fee -->
          <div class="fee-block mb-4" data-fee="possession_fee">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label fw-semibold mb-0">Possession Fee</label>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch"
                       id="possession_fee_enable">
                <label class="form-check-label" for="possession_fee_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none">
              <div class="row align-items-center mb-2">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="possession_fee" name="possession_fee"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0 mt-2 mt-md-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="possession_fee_in_per" name="possession_fee_in_per" value="1" disabled>
                    <label class="form-check-label" for="possession_fee_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="possession_fee_surplus" name="possession_fee_surplus" value="1" disabled>
                <label class="form-check-label" for="possession_fee_surplus">Add in listing price </label>
              </div>
            </div>
          </div>

          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="ti tabler-check me-1"></i>
              Save Template
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




@push('scripts')
  <script>
    $(function() {
      $('.fee-enable-toggle').on('change', function() {
        const block = $(this).closest('.fee-block');
        const fields = block.find('.fee-fields');
        const inputs = fields.find('input');

        if (this.checked) {
          fields.removeClass('d-none');
          inputs.prop('disabled', false);
        } else {
          fields.addClass('d-none');
          inputs.prop('disabled', true);
          inputs.each(function() {
            if ($(this).attr('type') === 'checkbox') {
              $(this).prop('checked', $(this).data('default-checked') === true);
            } else {
              $(this).val('');
            }
          });
        }
      });

      // Fee blocks that have a "surplus" (Add in listing price) toggle
      const feeKeys = [
        { key: 'booking_fee', surplus: false },
        { key: 'development_fee', surplus: true },
        { key: 'down_payment', surplus: false },
        { key: 'confirmation_fee', surplus: false },
        { key: 'possession_fee', surplus: true }
      ];

      // The amount columns come back from DataTables as rendered HTML
      // (badge markup from feeCell()), not a raw number, so pull the
      // number back out of it. Returns null if the fee isn't set.
      function extractFeeAmount(html) {
        if (!html || typeof html !== 'string') return null;
        if (html.includes('tabler-x')) return null; // "not set" icon
        const match = html.match(/fw-medium">([^<]+)</);
        if (!match) return null;
        const numeric = parseFloat(match[1].replace(/[,%]/g, ''));
        return isNaN(numeric) ? null : numeric;
      }

      function resetTemplateForm() {
        $('#modalTitle').text('Add Payment Planning Template');
        $('#templateForm')[0].reset();
        $('#templateId').val('');
        $('.fee-block').each(function() {
          $(this).find('.fee-enable-toggle').prop('checked', false);
          const fields = $(this).find('.fee-fields');
          fields.addClass('d-none');
          fields.find('input').prop('disabled', true);
        });
      }

      // Prefill the modal when an edit button is clicked
      $(document).on('click', '.edit-template-btn', function() {
        const table = $('#payment_templates_table').DataTable();
        const rowData = table.row($(this).closest('tr')).data();
        if (!rowData) return;

        resetTemplateForm();

        $('#modalTitle').text('Edit Payment Planning Template');
        $('#templateId').val(rowData.id);
        $('#template_name').val(rowData.name);

        feeKeys.forEach(function(cfg) {
          const key = cfg.key;
          const amount = extractFeeAmount(rowData[key]);
          const block = $('.fee-block[data-fee="' + key + '"]');
          const fields = block.find('.fee-fields');

          if (amount !== null) {
            block.find('.fee-enable-toggle').prop('checked', true);
            fields.removeClass('d-none');
            fields.find('input').prop('disabled', false);

            $('#' + key).val(amount);
            $('#' + key + '_in_per').prop('checked', !!rowData[key + '_in_per']);

            if (cfg.surplus) {
              $('#' + key + '_surplus').prop('checked', !!rowData[key + '_surplus']);
            }
          }
        });

        $('#templateModal').modal('show');
      });

      // Reset the form back to "Add" state whenever the modal closes,
      // so opening it fresh afterwards doesn't carry over edit data.
      $('#templateModal').on('hidden.bs.modal', function() {
        resetTemplateForm();
      });
    });
  </script>
@endpush
