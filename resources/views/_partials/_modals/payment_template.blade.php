<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="modalTitle">Add Payment Planning Template</h4>
          <p class="text-muted mb-0">Create or edit a payment planning template</p>
        </div>

        <form action="{{ route('payment-templates.store') }}" method="POST" id="templateForm">
          @csrf
          <input type="hidden" name="id" id="templateId">
          <input type="hidden" name="owner_id" value="{{ auth()->id() }}">

          {{-- Name --}}
          <div class="mb-4">
            <label class="form-label fw-semibold required" for="template_name">Template Name</label>
            <input type="text" id="template_name" name="name" class="form-control"
                   placeholder="Enter template name" required>
          </div>

          {{-- Type toggles --}}
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" role="switch" id="is_commercial"
                         name="is_commercial" value="1">
                  <label class="form-check-label fw-semibold" for="is_commercial">Commercial</label>
                </div>
                <small class="text-muted">Applies only to commercial properties</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" role="switch" id="is_constructed"
                         name="is_constructed" value="1">
                  <label class="form-check-label fw-semibold" for="is_constructed">Constructed</label>
                </div>
                <small class="text-muted">Applies only to constructed properties</small>
              </div>
            </div>
          </div>

          {{-- Fees --}}
          <div class="section-label">Fees</div>

          <div class="fee-block" data-fee="booking_fee">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Booking Fee</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch" id="booking_fee_enable">
                <label class="form-check-label" for="booking_fee_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none mt-3">
              <div class="row g-3 align-items-center">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="booking_fee" name="booking_fee"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="booking_fee_in_per" name="booking_fee_in_per" value="1" disabled>
                    <label class="form-check-label" for="booking_fee_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="fee-block" data-fee="development_fee">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Development Fee</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch"
                       id="development_fee_enable">
                <label class="form-check-label" for="development_fee_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none mt-3">
              <div class="row g-3 align-items-center">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="development_fee" name="development_fee"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="development_fee_in_per" name="development_fee_in_per" value="1" disabled>
                    <label class="form-check-label" for="development_fee_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
              <div class="form-check form-switch mt-3 mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="development_fee_surplus" name="development_fee_surplus" value="1" checked disabled>
                <label class="form-check-label" for="development_fee_surplus">Added in listed price</label>
              </div>
            </div>
          </div>

          <div class="fee-block" data-fee="down_payment">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Down Payment</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch"
                       id="down_payment_enable">
                <label class="form-check-label" for="down_payment_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none mt-3">
              <div class="row g-3 align-items-center">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="down_payment" name="down_payment"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="down_payment_in_per" name="down_payment_in_per" value="1" disabled>
                    <label class="form-check-label" for="down_payment_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="fee-block" data-fee="confirmation_fee">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Confirmation Fee</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch"
                       id="confirmation_fee_enable">
                <label class="form-check-label" for="confirmation_fee_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none mt-3">
              <div class="row g-3 align-items-center">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="confirmation_fee" name="confirmation_fee"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="confirmation_fee_in_per" name="confirmation_fee_in_per" value="1" disabled>
                    <label class="form-check-label" for="confirmation_fee_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="fee-block" data-fee="possession_fee">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Possession Fee</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input fee-enable-toggle" type="checkbox" role="switch"
                       id="possession_fee_enable">
                <label class="form-check-label" for="possession_fee_enable">Add</label>
              </div>
            </div>
            <div class="fee-fields d-none mt-3">
              <div class="row g-3 align-items-center">
                <div class="col-md-7">
                  <input type="number" step="0.01" min="0" id="possession_fee" name="possession_fee"
                         class="form-control" placeholder="Enter amount" disabled>
                </div>
                <div class="col-md-5">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="possession_fee_in_per" name="possession_fee_in_per" value="1" disabled>
                    <label class="form-check-label" for="possession_fee_in_per">Percentage (%)</label>
                  </div>
                </div>
              </div>
              <div class="form-check form-switch mt-3 mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="possession_fee_surplus" name="possession_fee_surplus" value="1" checked disabled>
                <label class="form-check-label" for="possession_fee_surplus">Added in listed price</label>
              </div>
            </div>
          </div>

          {{-- Premiums (always a percentage) --}}
          <div class="section-label mt-4">Premiums (optional, %)</div>
          <div class="row g-3">
            <div class="col-md-4" data-fee="corner_premium">
              <label class="form-label fw-semibold" for="corner_premium">Corner</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" max="100" id="corner_premium" name="corner_premium"
                       class="form-control" placeholder="0">
                <span class="input-group-text">%</span>
              </div>
              <input type="hidden" name="corner_premium_in_per" value="1">
            </div>
            <div class="col-md-4" data-fee="park_facing_premium">
              <label class="form-label fw-semibold" for="park_facing_premium">Park Facing</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" max="100" id="park_facing_premium"
                       name="park_facing_premium" class="form-control" placeholder="0">
                <span class="input-group-text">%</span>
              </div>
              <input type="hidden" name="park_facing_premium_in_per" value="1">
            </div>
            <div class="col-md-4" data-fee="main_boulevard_premium">
              <label class="form-label fw-semibold" for="main_boulevard_premium">Main Boulevard</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" max="100" id="main_boulevard_premium"
                       name="main_boulevard_premium" class="form-control" placeholder="0">
                <span class="input-group-text">%</span>
              </div>
              <input type="hidden" name="main_boulevard_premium_in_per" value="1">
            </div>
          </div>

          <div class="d-grid gap-2 mt-5">
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
    <div class="modal-content shadow-lg overflow-hidden">
      <div class="modal-header bg-secondary pb-4">
        <h5 class="modal-title text-white fw-bold"><i class="ti tabler-file-invoice me-2"></i>Template Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="templateInfoBody"></div>
    </div>
  </div>
</div>

@push('styles')
  <style>
    .section-label {
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--bs-secondary-color, #6c757d);
      margin-bottom: .625rem;
    }

    /* Each fee is a card; it gets a primary tint when switched on */
    .fee-block {
      border: 1px solid var(--bs-border-color, #dee2e6);
      border-radius: .5rem;
      padding: .75rem 1rem;
      margin-bottom: .75rem;
      transition: border-color .15s ease, background-color .15s ease;
    }

    .fee-block.is-on {
      border-color: var(--bs-primary);
      background-color: rgba(var(--bs-primary-rgb), .05);
    }
  </style>
@endpush

@push('scripts')
  <script>
    (function() {
      const FEE_FIELDS = [
        { key: 'booking_fee', label: 'Booking Fee', surplusField: null },
        { key: 'development_fee', label: 'Development Fee', surplusField: 'development_fee_surplus' },
        { key: 'down_payment', label: 'Down Payment', surplusField: null },
        { key: 'confirmation_fee', label: 'Confirmation Fee', surplusField: null },
        { key: 'possession_fee', label: 'Possession Fee', surplusField: 'possession_fee_surplus' }
      ];

      const PREMIUM_FIELDS = [
        { key: 'corner_premium', label: 'Corner Premium' },
        { key: 'park_facing_premium', label: 'Park Facing Premium' },
        { key: 'main_boulevard_premium', label: 'Main Boulevard Premium' }
      ];

      const AMOUNT_FIELDS = [
        ...FEE_FIELDS.map(field => ({ ...field, alwaysPercentage: false })),
        ...PREMIUM_FIELDS.map(field => ({ ...field, alwaysPercentage: true }))
      ];

      const templateForm = document.getElementById('templateForm');
      const templateModalEl = document.getElementById('templateModal');

      const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[ch]
      ));

      // Premiums now live in a plain column (not .fee-block), so select by data-fee only.
      function feeBlockFor(key) {
        return document.querySelector(`[data-fee="${key}"]`);
      }

      // Shows/hides a fee card, enables/disables its inputs, keeps the
      // "Add" switch and the highlighted state in sync (also used when editing).
      function setFeeBlockEnabled(block, enabled) {
        const fieldsWrapper = block.querySelector('.fee-fields');
        if (!fieldsWrapper) return;

        const enableToggle = block.querySelector('.fee-enable-toggle');
        if (enableToggle) enableToggle.checked = enabled;
        block.classList.toggle('is-on', enabled);

        fieldsWrapper.classList.toggle('d-none', !enabled);
        fieldsWrapper.querySelectorAll('input').forEach(input => {
          input.disabled = !enabled;
          if (input.type === 'checkbox') {
            // Restore the HTML default (e.g. "Added in listed price" is on by default)
            input.checked = enabled ? input.defaultChecked : false;
          } else if (!enabled) {
            input.value = '';
          }
          if (!enabled) clearFieldError(input);
        });
      }

      function clearValidationErrors() {
        document.querySelectorAll('#templateForm .invalid-feedback').forEach(el => el.remove());
        document.querySelectorAll('#templateForm .is-invalid').forEach(el => el.classList.remove('is-invalid'));
      }

      // Errors go after the input, or after its .input-group so the % suffix stays attached.
      function errorAnchor(input) {
        const group = input.closest('.input-group');
        return group || input;
      }

      function clearFieldError(input) {
        input.classList.remove('is-invalid');
        const anchor = errorAnchor(input);
        const errorEl = anchor.nextElementSibling;
        if (errorEl && errorEl.classList.contains('invalid-feedback')) errorEl.remove();
      }

      function addFieldError(input, message) {
        clearFieldError(input);
        input.classList.add('is-invalid');
        const errorEl = document.createElement('div');
        errorEl.className = 'invalid-feedback d-block';
        errorEl.textContent = message;
        errorAnchor(input).insertAdjacentElement('afterend', errorEl);
      }

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
        document.getElementById('is_constructed').checked = false;

        FEE_FIELDS.forEach(fieldConfig => setFeeBlockEnabled(feeBlockFor(fieldConfig.key), false));
        clearValidationErrors();
      }

      function extractFeeAmount(cellHtml) {
        if (!cellHtml || typeof cellHtml !== 'string' || cellHtml.includes('tabler-x')) return null;

        const match = cellHtml.match(/fw-medium">([^<]+)</);
        if (!match) return null;

        const numericValue = parseFloat(match[1].replace(/[,%]/g, ''));
        return isNaN(numericValue) ? null : numericValue;
      }

      function fillFormForEdit(templateRow, editData) {
        document.getElementById('modalTitle').textContent = 'Edit Payment Planning Template';
        document.getElementById('templateId').value = editData.id ?? templateRow.id;
        document.getElementById('template_name').value = editData.name ?? templateRow.name;
        document.getElementById('is_commercial').checked = Boolean(editData.is_commercial);
        document.getElementById('is_constructed').checked = Boolean(editData.is_constructed);

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

      // ── Info modal ──────────────────────────────────────────────────
      function buildFeeInfoRow(fieldConfig, templateData) {
        const amount = templateData[fieldConfig.key];
        if (amount === null || amount === undefined || amount === '') return null;

        const displayValue = templateData[fieldConfig.key + '_in_per']
          ? `${escapeHtml(amount)}%`
          : Number(amount).toLocaleString();

        const isIncluded = fieldConfig.surplusField ? Boolean(templateData[fieldConfig.surplusField]) : true;
        const badge = isIncluded
          ? '<span class="badge bg-label-success ms-1">Included</span>'
          : '<span class="badge bg-label-danger ms-1">Excluded</span>';

        return `<tr><td class="fw-semibold">${fieldConfig.label}</td><td class="text-end">${displayValue} ${badge}</td></tr>`;
      }

      function buildPremiumInfoRow(fieldConfig, templateData) {
        const amount = templateData[fieldConfig.key];
        if (!amount) return null;
        return `<tr><td class="fw-semibold">${fieldConfig.label}</td><td class="text-end">${escapeHtml(amount)}%</td></tr>`;
      }

      function buildTemplateInfoHtml(templateData) {
        const typeLabel = templateData.is_commercial ? 'Commercial' : 'Non-Commercial';
        const typeBadgeClass = templateData.is_commercial ? 'bg-label-warning' : 'bg-label-success';
        const constructedLabel = templateData.is_constructed ? 'Constructed' : 'Non-Constructed';
        const constructedBadgeClass = templateData.is_constructed ? 'bg-label-dark' : 'bg-label-info';

        const feeRows = FEE_FIELDS.map(field => buildFeeInfoRow(field, templateData)).filter(Boolean);
        const premiumRows = PREMIUM_FIELDS.map(field => buildPremiumInfoRow(field, templateData)).filter(Boolean);

        let html = `
          <div class="text-center mb-3">
            <h5 class="fw-bold mb-2">${escapeHtml(templateData.name)}</h5>
            <span class="badge ${typeBadgeClass} fs-6">${typeLabel}</span>
            <span class="badge ${constructedBadgeClass} fs-6 ms-1">${constructedLabel}</span>
          </div>`;

        if (feeRows.length) {
          html += `<div class="table-responsive"><table class="table table-sm mb-0"><tbody>${feeRows.join('')}</tbody></table></div>`;
        }

        if (premiumRows.length) {
          html += `
            <div class="section-label mt-4 mb-1">Premiums</div>
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

      templateForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (validateTemplateForm()) this.submit();
      });

      templateModalEl.addEventListener('hidden.bs.modal', resetTemplateForm);

      document.addEventListener('click', function(e) {
        const editButton = e.target.closest('.edit-template-btn');
        if (editButton) {
          const table = new DataTable('#payment_templates_table');
          const templateRow = table.row(editButton.closest('tr')).data();
          if (!templateRow) return;

          let editData;
          try {
            editData = JSON.parse(editButton.getAttribute('data-template'));
          } catch (error) {
            editData = templateRow;
          }

          resetTemplateForm();
          fillFormForEdit(templateRow, editData);
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
