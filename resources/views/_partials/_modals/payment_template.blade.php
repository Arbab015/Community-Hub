<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="modalTitle">Add Payment Planning Template </h4>
          <p class="text-muted mb-2">Create or edit a payment planning template</p>
        </div>

        <form action="{{ route('payment-templates.store') }}" method="POST" id="templateForm">
          @csrf
          <input type="hidden" name="id" id="templateId">
          <input type="hidden" name="owner_id" value="{{ auth()->id() }}">

          <div class="mb-4">
            <label class="form-label fw-semibold required" for="template_name">Template Name:</label>
            <input type="text" id="template_name" name="name" class="form-control"
                   placeholder="Enter template name" required>
          </div>

          <div class="mb-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="is_commercial" name="is_commercial"
                     value="1">
              <label class="form-check-label fw-semibold" for="is_commercial">Commercial Template</label>
            </div>
            <small class="text-muted">When enabled, this template will only apply to commercial properties</small>
          </div>

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
                       id="development_fee_surplus" name="development_fee_surplus" value="1" checked disabled>
                <label class="form-check-label" for="development_fee_surplus">Added in listed price</label>
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
          <div class="fee-block mb-4 pb-3 border-bottom" data-fee="possession_fee">
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
                       id="possession_fee_surplus" name="possession_fee_surplus" value="1" checked disabled>
                <label class="form-check-label" for="possession_fee_surplus">Added in listed price</label>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <!-- Corner Premium (optional, always a percentage) -->
          <div class="fee-block mb-4 pb-3 border-bottom" data-fee="corner_premium">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label fw-semibold mb-0">Corner Premium</label>
            </div>
            <div class="row align-items-center">
              <div class="col-md-7">
                <div class="input-group">
                  <input type="number" step="0.01" min="0" max="100" id="corner_premium" name="corner_premium"
                         class="form-control" placeholder="Enter percentage">
                  <span class="input-group-text">%</span>
                </div>
              </div>
            </div>
            <input type="hidden" name="corner_premium_in_per" value="1">
          </div>

          <!-- Park Facing Premium (optional, always a percentage) -->
          <div class="fee-block mb-4 pb-3 border-bottom" data-fee="park_facing_premium">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label fw-semibold mb-0">Park Facing Premium</label>
            </div>
            <div class="row align-items-center">
              <div class="col-md-7">
                <div class="input-group">
                  <input type="number" step="0.01" min="0" max="100" id="park_facing_premium" name="park_facing_premium"
                         class="form-control" placeholder="Enter percentage">
                  <span class="input-group-text">%</span>
                </div>
              </div>
            </div>
            <input type="hidden" name="park_facing_premium_in_per" value="1">
          </div>

          <!-- Main Boulevard Premium (optional, always a percentage) -->
          <div class="fee-block mb-4" data-fee="main_boulevard_premium">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label fw-semibold mb-0">Main Boulevard Premium</label>
            </div>
            <div class="row align-items-center">
              <div class="col-md-7">
                <div class="input-group">
                  <input type="number" step="0.01" min="0" max="100" id="main_boulevard_premium"
                         name="main_boulevard_premium"
                         class="form-control" placeholder="Enter percentage">
                  <span class="input-group-text">%</span>
                </div>
              </div>
            </div>
            <input type="hidden" name="main_boulevard_premium_in_per" value="1">
          </div>

          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
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

{{-- Template Info Modal --}}
<div class="modal fade" id="templateInfoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header  bg-secondary  pb-4">
        <h5 class="modal-title text-white fw-bold"><i class="ti tabler-file-invoice me-2"></i>Template Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="templateInfoBody"></div>
    </div>
  </div>
</div>


@push('styles')
  <style>
    .premium-percentage-toggle:checked + label .badge {
      background-color: #03a9f4 !important;
      color: white !important;
    }

    .premium-percentage-toggle + label .badge {
      background-color: #e0e0e0 !important;
      color: #666 !important;
      transition: all 0.3s ease;
    }
  </style>
@endpush

@push('scripts')
  <script>
    (function () {
      // Fees: have an "Add" toggle that shows/hides + enables/disables their fields.
      // surplusField (when present) points at the "Added in listed price" checkbox.
      const FEE_FIELDS = [
        { key: 'booking_fee', label: 'Booking Fee', surplusField: null },
        { key: 'development_fee', label: 'Development Fee', surplusField: 'development_fee_surplus' },
        { key: 'down_payment', label: 'Down Payment', surplusField: null },
        { key: 'confirmation_fee', label: 'Confirmation Fee', surplusField: null },
        { key: 'possession_fee', label: 'Possession Fee', surplusField: 'possession_fee_surplus' },
      ];

      // Premiums: always visible, always a percentage, no "Add" toggle.
      const PREMIUM_FIELDS = [
        { key: 'corner_premium', label: 'Corner Premium' },
        { key: 'park_facing_premium', label: 'Park Facing Premium' },
        { key: 'main_boulevard_premium', label: 'Main Boulevard Premium' },
      ];

      // Combined list used for validation, since premiums are validated the
      // same way as fees (just always treated as a percentage).
      const AMOUNT_FIELDS = [
        ...FEE_FIELDS.map(field => ({ ...field, alwaysPercentage: false })),
        ...PREMIUM_FIELDS.map(field => ({ ...field, alwaysPercentage: true })),
      ];

      const templateForm = document.getElementById('templateForm');
      const templateModalEl = document.getElementById('templateModal');

      function feeBlockFor(key) {
        return document.querySelector(`.fee-block[data-fee="${key}"]`);
      }

      // Shows/hides a fee block's fields and enables/disables its inputs.
      // Used both when the user flips the "Add" toggle and when prefilling
      // the form for edit, so the two stay in sync automatically.
      function setFeeBlockEnabled(block, enabled) {
        const fieldsWrapper = block.querySelector('.fee-fields');
        if (!fieldsWrapper) return;

        fieldsWrapper.classList.toggle('d-none', !enabled);
        fieldsWrapper.querySelectorAll('input').forEach(input => {
          input.disabled = !enabled;
          if (!enabled) {
            input.type === 'checkbox' ? (input.checked = false) : (input.value = '');
            clearFieldError(input);
          }
        });
      }

      function clearValidationErrors() {
        document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
        document.querySelectorAll('.form-control, .form-check-input').forEach(el => el.classList.remove('is-invalid'));
      }

      function clearFieldError(input) {
        input.classList.remove('is-invalid');
        const errorEl = input.nextElementSibling;
        if (errorEl && errorEl.classList.contains('invalid-feedback')) errorEl.remove();
      }

      function addFieldError(input, message) {
        clearFieldError(input);
        input.classList.add('is-invalid');
        const errorEl = document.createElement('div');
        errorEl.className = 'invalid-feedback d-block';
        errorEl.textContent = message;
        input.insertAdjacentElement('afterend', errorEl);
      }

      // Validates a single amount field (fee or premium). Used both live
      // (on input/toggle) and again on submit.
      function validateAmountField(fieldConfig) {
        const amountInput = document.getElementById(fieldConfig.key);
        const percentToggle = document.getElementById(fieldConfig.key + '_in_per');

        clearFieldError(amountInput);

        const rawValue = amountInput.value.trim();
        if (!rawValue) return true;

        const numericValue = parseFloat(rawValue);
        if (isNaN(numericValue)) {
          addFieldError(amountInput, 'Please enter a valid number');
          return false;
        }
        if (numericValue < 0) {
          addFieldError(amountInput, 'Amount cannot be negative');
          return false;
        }

        const isPercentage = fieldConfig.alwaysPercentage || Boolean(percentToggle && percentToggle.checked);
        if (isPercentage && numericValue > 100) {
          addFieldError(amountInput, 'Percentage must be between 0-100');
          return false;
        }

        return true;
      }

      function validateTemplateForm() {
        clearValidationErrors();
        let isValid = true;

        const templateNameInput = document.getElementById('template_name');
        if (!templateNameInput.value.trim()) {
          addFieldError(templateNameInput, 'Template name is required');
          isValid = false;
        }

        AMOUNT_FIELDS.forEach(fieldConfig => {
          const block = feeBlockFor(fieldConfig.key);
          const enableToggle = block.querySelector('.fee-enable-toggle');
          const amount = document.getElementById(fieldConfig.key).value.trim();

          // Fee blocks only need validating when switched on; premiums
          // (no enable toggle) only need validating when filled in.
          const shouldValidate = enableToggle ? enableToggle.checked : amount !== '';
          if (!shouldValidate) return;

          if (!validateAmountField(fieldConfig)) isValid = false;
        });

        return isValid;
      }

      function resetTemplateForm() {
        document.getElementById('modalTitle').textContent = 'Add Payment Planning Template';
        templateForm.reset();
        document.getElementById('templateId').value = '';
        document.getElementById('is_commercial').checked = false;

        FEE_FIELDS.forEach(fieldConfig => setFeeBlockEnabled(feeBlockFor(fieldConfig.key), false));
        clearValidationErrors();
      }

      // Reads a fee amount out of the DataTable's rendered HTML cell,
      // e.g. '<span class="fw-medium">1,500</span>' -> 1500. Returns null
      // when the cell shows the "not set" icon (tabler-x).
      function extractFeeAmount(cellHtml) {
        if (!cellHtml || typeof cellHtml !== 'string' || cellHtml.includes('tabler-x')) return null;

        const match = cellHtml.match(/fw-medium">([^<]+)</);
        if (!match) return null;

        const numericValue = parseFloat(match[1].replace(/[,%]/g, ''));
        return isNaN(numericValue) ? null : numericValue;
      }

      function fillFormForEdit(templateRow) {
        document.getElementById('modalTitle').textContent = 'Edit Payment Planning Template';
        document.getElementById('templateId').value = templateRow.id;
        document.getElementById('template_name').value = templateRow.name;
        document.getElementById('is_commercial').checked = Boolean(templateRow.is_commercial);

        FEE_FIELDS.forEach(fieldConfig => {
          const amount = extractFeeAmount(templateRow[fieldConfig.key]);
          if (amount === null) return;

          setFeeBlockEnabled(feeBlockFor(fieldConfig.key), true);
          document.getElementById(fieldConfig.key).value = amount;

          const percentToggle = document.getElementById(fieldConfig.key + '_in_per');
          if (percentToggle) percentToggle.checked = Boolean(templateRow[fieldConfig.key + '_in_per']);

          if (fieldConfig.surplusField) {
            document.getElementById(fieldConfig.surplusField).checked = Boolean(templateRow[fieldConfig.surplusField]);
          }
        });

        PREMIUM_FIELDS.forEach(fieldConfig => {
          if (templateRow[fieldConfig.key]) {
            document.getElementById(fieldConfig.key).value = templateRow[fieldConfig.key];
          }
        });
      }

      // Builds one info-modal row: "Label   value  [Included/Excluded]".
      function buildFeeInfoRow(fieldConfig, templateData) {
        const amount = templateData[fieldConfig.key];
        if (amount === null || amount === undefined || amount === '') return null;

        const displayValue = templateData[fieldConfig.key + '_in_per']
          ? `${amount}%`
          : Number(amount).toLocaleString();

        // Fields without a surplus toggle are always "Included in listed price".
        const isIncluded = fieldConfig.surplusField ? Boolean(templateData[fieldConfig.surplusField]) : true;
        const badge = isIncluded
          ? '<span class="badge bg-label-success ms-1">Included</span>'
          : '<span class="badge bg-label-danger ms-1">Excluded</span>';

        return `<tr><td class="fw-semibold">${fieldConfig.label}</td><td class="text-end">${displayValue} ${badge}</td></tr>`;
      }

      function buildPremiumInfoRow(fieldConfig, templateData) {
        const amount = templateData[fieldConfig.key];
        if (!amount) return null;
        return `<tr><td class="fw-semibold">${fieldConfig.label}</td><td class="text-end">${amount}%</td></tr>`;
      }

      function buildTemplateInfoHtml(templateData) {
        const typeLabel = templateData.is_commercial ? 'Commercial' : 'Residential';
        const typeBadgeClass = templateData.is_commercial ? 'bg-label-warning' : 'bg-label-success';

        const feeRows = FEE_FIELDS.map(field => buildFeeInfoRow(field, templateData)).filter(Boolean);
        const premiumRows = PREMIUM_FIELDS.map(field => buildPremiumInfoRow(field, templateData)).filter(Boolean);

        let html = `
          <div class="text-center mb-3">
            <h5 class="fw-bold mb-1">${templateData.name}</h5>
            <span class="badge ${typeBadgeClass} fs-6">${typeLabel}</span>
          </div>`;

        if (feeRows.length) {
          html += `<div class="table-responsive"><table class="table table-sm mb-0"><tbody>${feeRows.join('')}</tbody></table></div>`;
        }

        if (premiumRows.length) {
          html += `
            <h6 class="fw-bold mb-2 mt-3"><i class="ti tabler-star me-1"></i>Premiums</h6>
            <div class="table-responsive"><table class="table table-sm mb-0"><tbody>${premiumRows.join('')}</tbody></table></div>`;
        }

        if (!feeRows.length && !premiumRows.length) {
          html += '<p class="text-center text-muted mb-0"><i class="ti tabler-info-circle me-1"></i>No charges configured</p>';
        }

        return html;
      }

      // ── Wiring ──────────────────────────────────────────────────────
      document.querySelectorAll('.fee-enable-toggle').forEach(toggle => {
        toggle.addEventListener('change', () => {
          setFeeBlockEnabled(toggle.closest('.fee-block'), toggle.checked);
        });
      });

      AMOUNT_FIELDS.forEach(fieldConfig => {
        document.getElementById(fieldConfig.key).addEventListener('input', () => validateAmountField(fieldConfig));

        const percentToggle = document.getElementById(fieldConfig.key + '_in_per');
        if (percentToggle) percentToggle.addEventListener('change', () => validateAmountField(fieldConfig));
      });

      templateForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (validateTemplateForm()) this.submit();
      });

      templateModalEl.addEventListener('hidden.bs.modal', resetTemplateForm);

      document.addEventListener('click', function (e) {
        const editButton = e.target.closest('.edit-template-btn');
        if (editButton) {
          const table = new DataTable('#payment_templates_table');
          const templateRow = table.row(editButton.closest('tr')).data();
          if (!templateRow) return;

          resetTemplateForm();
          fillFormForEdit(templateRow);
          bootstrap.Modal.getOrCreateInstance(templateModalEl).show();
          return;
        }

        const infoButton = e.target.closest('.info-template-btn');
        if (infoButton) {
          const templateData = JSON.parse(infoButton.getAttribute('data-template'));
          document.getElementById('templateInfoBody').innerHTML = buildTemplateInfoHtml(templateData);
          bootstrap.Modal.getOrCreateInstance(document.getElementById('templateInfoModal')).show();
        }
      });
    })();
  </script>
@endpush
