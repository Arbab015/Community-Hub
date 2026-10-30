<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="modalTitle">Add Installment Template</h4>
          <p class="text-muted mb-0">Configure installment payment settings</p>
        </div>

        <form action="{{ route('installment-templates.store') }}" method="POST" id="templateForm">
          @csrf
          <input type="hidden" name="id" id="templateId">

          {{-- Name --}}
          <div class="mb-4">
            <label class="form-label fw-semibold required" for="template_name">Template Name</label>
            <input type="text" name="name" id="template_name" class="form-control"
                   placeholder="e.g., 5-Marla 5-Year Plan" required>
            <div class="invalid-feedback d-block" id="template_name_error"></div>
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

          {{-- Schedule --}}
          <div class="section-label">Schedule</div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold required" for="number_of_installments">Number of Installments</label>
              <input type="number" name="number_of_installments" id="number_of_installments" class="form-control"
                     placeholder="e.g., 24, 36, 48" min="1" required>
              <div class="invalid-feedback d-block" id="number_of_installments_error"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold required" for="installment_frequency">Installment Frequency</label>
              <select name="installment_frequency" id="installment_frequency" class="form-select" required>
                <option value="">Select frequency</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="biannually">Biannually</option>
                <option value="annually">Annually</option>
              </select>
              <div class="invalid-feedback d-block" id="installment_frequency_error"></div>
            </div>
          </div>

          {{-- Additional installment amount --}}
          <div class="plan-card">
            <label class="fw-semibold required d-block mb-2" for="additional_installment_amount">Additional Installment
              Amount</label>
            <div class="row g-3 align-items-center">
              <div class="col-md-7">
                <input type="number" name="additional_installment_amount" id="additional_installment_amount"
                       class="form-control" step="0.01" min="0" placeholder="Enter amount" required>
              </div>
              <div class="col-md-5">
                <div class="form-check form-switch mb-0">
                  <input type="hidden" name="additional_installment_amount_in_per" value="0">
                  <input type="checkbox" role="switch" id="additional_installment_amount_in_per"
                         class="form-check-input">
                  <label class="form-check-label" for="additional_installment_amount_in_per">As Percentage (%)</label>
                </div>
              </div>
            </div>
            <small class="d-block text-muted mt-2">Amount added to base price for installments. Percentage: 0-100,
              Amount: any value</small>
            <div class="invalid-feedback d-block" id="additional_installment_amount_error"></div>
          </div>

          {{-- Balloon payment --}}
          <div class="section-label mt-4">Balloon Payment (optional)</div>
          <div class="plan-card">
            <label class="fw-semibold d-block mb-2" for="balloon_payment">Balloon Payment</label>
            <div class="row g-3 align-items-center">
              <div class="col-md-7">
                <input type="number" name="balloon_payment" id="balloon_payment" class="form-control"
                       step="0.01" min="0" placeholder="Enter amount (optional)">
              </div>
              <div class="col-md-5">
                <div class="form-check form-switch mb-0">
                  <input type="hidden" name="balloon_payment_in_per" value="0">
                  <input type="checkbox" role="switch" id="balloon_payment_in_per" class="form-check-input">
                  <label class="form-check-label" for="balloon_payment_in_per">As Percentage (%)</label>
                </div>
              </div>
            </div>
            <small class="d-block text-muted mt-2">Large payment due at specified intervals. Percentage: 0-100,
              Amount: any value</small>
            <div class="invalid-feedback d-block" id="balloon_payment_error"></div>

            <label class="fw-semibold d-block mb-2 mt-3" for="balloon_payment_frequency">Balloon Payment
              Frequency</label>
            <select name="balloon_payment_frequency" id="balloon_payment_frequency" class="form-select">
              <option value="">Not applicable</option>
              <option value="quarterly">Quarterly</option>
              <option value="biannually">Biannually</option>
              <option value="annually">Annually</option>
            </select>
            <small class="d-block text-muted mt-2">Required when balloon payment amount is specified</small>
            <div class="invalid-feedback d-block" id="balloon_payment_frequency_error"></div>
          </div>

          <div class="structure-preview d-none" id="templateStructurePreview"></div>

          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
              <i class="ti tabler-check me-1"></i>
              Save Template
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
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
        <h5 class="modal-title text-white fw-bold"><i class="ti tabler-calendar-stats me-2"></i>Template Details</h5>
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

    .plan-card {
      border: 1px solid var(--bs-primary);
      background-color: rgba(var(--bs-primary-rgb), .05);
      border-radius: .5rem;
      padding: .875rem 1rem;
      margin-bottom: .75rem;
    }

    .structure-preview {
      margin-top: 1rem;
      padding: .625rem .875rem;
      border-radius: .5rem;
      font-weight: 600;
      background-color: rgba(var(--bs-primary-rgb), .1);
      border: 1px solid rgba(var(--bs-primary-rgb), .3);
    }
  </style>
@endpush

@push('scripts')
  <script>
    (function() {

      const PERCENTAGE_TOGGLE_FIELDS = [
        { amountFieldId: 'additional_installment_amount', toggleId: 'additional_installment_amount_in_per' },
        { amountFieldId: 'balloon_payment', toggleId: 'balloon_payment_in_per' }
      ];

      const templateForm = document.getElementById('templateForm');
      const templateModalEl = document.getElementById('templateModal');
      const structurePreviewEl = document.getElementById('templateStructurePreview');
      const InstallmentCalculator = window.InstallmentPlanCalculator;

      const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[ch]
      ));

      const capitalize = value => value ? value.charAt(0).toUpperCase() + value.slice(1) : '';

      function hiddenInputFor(toggleId) {
        return document.querySelector(`input[type="hidden"][name="${toggleId}"]`);
      }

      function clearAllErrors() {
        document.querySelectorAll('#templateForm .invalid-feedback').forEach(el => {
          el.textContent = '';
        });
        document.querySelectorAll('#templateForm .form-control, #templateForm .form-select')
          .forEach(el => el.classList.remove('is-invalid'));
      }

      function showFieldError(fieldId, message) {
        document.getElementById(fieldId).classList.add('is-invalid');
        document.getElementById(fieldId + '_error').textContent = message;
      }

      function clearFieldError(fieldId) {
        document.getElementById(fieldId).classList.remove('is-invalid');
        document.getElementById(fieldId + '_error').textContent = '';
      }

      // A value marked "as percentage" must not exceed 100.
      function validatePercentageAmount(amountFieldId, toggleId) {
        const amountInput = document.getElementById(amountFieldId);
        if (!amountInput.value) return true;

        const isPercentage = document.getElementById(toggleId).checked;
        const amountValue = parseFloat(amountInput.value);

        if (isPercentage && amountValue > 100) {
          showFieldError(amountFieldId, 'Percentage must be between 0-100');
          return false;
        }

        clearFieldError(amountFieldId);
        return true;
      }

      // Keeps the hidden 0/1 input in sync and re-validates when toggled.
      function bindPercentageToggle({ amountFieldId, toggleId }) {
        const toggleInput = document.getElementById(toggleId);

        toggleInput.addEventListener('change', function() {
          hiddenInputFor(toggleId).value = toggleInput.checked ? 1 : 0;
          validatePercentageAmount(amountFieldId, toggleId);
          renderStructurePreview();
        });

        document.getElementById(amountFieldId).addEventListener('input', function() {
          if (toggleInput.checked) {
            validatePercentageAmount(amountFieldId, toggleId);
          }
          if (amountFieldId === 'balloon_payment') renderStructurePreview();
        });
      }

      function resetPercentageToggle({ toggleId }) {
        document.getElementById(toggleId).checked = false;
        hiddenInputFor(toggleId).value = 0;
      }

      // Balloon rules: needs a frequency, and it must be less frequent than the installments.
      function getStructureErrors() {
        const balloonAmount = document.getElementById('balloon_payment').value.trim();
        const balloonFrequency = document.getElementById('balloon_payment_frequency').value;
        const installmentFrequency = document.getElementById('installment_frequency').value;

        const errors = {};
        if (!balloonAmount) return errors;

        if (!balloonFrequency) {
          errors.balloon_payment_frequency = 'Required when balloon payment is set';
          return errors;
        }

        if (!InstallmentCalculator || !installmentFrequency) return errors;

        if (!InstallmentCalculator.balloonFrequencyValid(installmentFrequency, balloonFrequency)) {
          errors.balloon_payment_frequency = 'Balloon frequency must be less frequent than the installment frequency';
        }

        return errors;
      }

      function validateStructure() {
        clearAllErrors();
        const errors = getStructureErrors();
        Object.entries(errors).forEach(([fieldId, message]) => showFieldError(fieldId, message));
        return Object.keys(errors).length === 0;
      }

      function renderStructurePreview() {
        if (!structurePreviewEl || !InstallmentCalculator) return;

        const installmentCount = parseInt(document.getElementById('number_of_installments').value, 10) || 0;
        const installmentFrequency = document.getElementById('installment_frequency').value;
        const balloonAmount = document.getElementById('balloon_payment').value.trim();
        const balloonFrequency = document.getElementById('balloon_payment_frequency').value;

        const result = InstallmentCalculator.calculate({
          listing_price: 0,
          included_charges_total: 0,
          excluded_charges_total: 0,
          additional_amount: 0,
          number_of_installments: installmentCount,
          installment_frequency: installmentFrequency,
          balloon_payment: balloonAmount,
          balloon_payment_in_per: document.getElementById('balloon_payment_in_per').checked,
          balloon_payment_frequency: balloonFrequency
        });

        let previewText = `${installmentCount} installments`;
        if (installmentFrequency) {
          previewText += ' · ' + capitalize(installmentFrequency);
        }

        if (balloonAmount && balloonFrequency) {
          previewText += result.valid
            ? ` · ${result.balloon_count} balloons (every ${result.step} installments) · ${result.normal_count} regular installments`
            : ` · <span class="text-danger">${escapeHtml(result.errors[0])}</span>`;
        }

        structurePreviewEl.innerHTML = previewText;
        structurePreviewEl.classList.toggle('d-none', !installmentFrequency && !(balloonAmount && balloonFrequency));
      }

      function fillFormForEdit(template) {
        document.getElementById('modalTitle').textContent = 'Edit Installment Template';
        document.getElementById('templateId').value = template.id;
        document.getElementById('template_name').value = template.name;
        document.getElementById('is_commercial').checked = Boolean(template.is_commercial);
        document.getElementById('is_constructed').checked = Boolean(template.is_constructed);
        document.getElementById('number_of_installments').value = template.number_of_installments;
        document.getElementById('installment_frequency').value = template.installment_frequency;
        document.getElementById('additional_installment_amount').value = template.additional_installment_amount;
        document.getElementById('balloon_payment').value = template.balloon_payment || '';
        document.getElementById('balloon_payment_frequency').value = template.balloon_payment_frequency || '';

        PERCENTAGE_TOGGLE_FIELDS.forEach(({ toggleId }) => {
          const isPercentage = Boolean(template[toggleId]);
          document.getElementById(toggleId).checked = isPercentage;
          hiddenInputFor(toggleId).value = isPercentage ? 1 : 0;
        });

        renderStructurePreview();
      }

      function resetForm() {
        document.getElementById('modalTitle').textContent = 'Add Installment Template';
        templateForm.reset();
        document.getElementById('templateId').value = '';
        document.getElementById('is_commercial').checked = false;
        document.getElementById('is_constructed').checked = false;
        PERCENTAGE_TOGGLE_FIELDS.forEach(resetPercentageToggle);
        structurePreviewEl.classList.add('d-none');
        clearAllErrors();
      }

      function buildTemplateInfoHtml(template) {
        const typeLabel = template.is_commercial ? 'Commercial' : 'Non-Commercial';
        const typeBadgeClass = template.is_commercial ? 'bg-label-warning' : 'bg-label-success';
        const constructedLabel = template.is_constructed ? 'Constructed' : 'Non-Constructed';
        const constructedBadgeClass = template.is_constructed ? 'bg-label-dark' : 'bg-label-info';

        const formatAmount = (amount, isPercentage) =>
          isPercentage ? `${escapeHtml(amount)}%` : Number(amount).toLocaleString();

        const rows = [
          ['Installments', escapeHtml(template.number_of_installments)],
          ['Frequency', capitalize(template.installment_frequency) || '—']
        ];

        if (template.additional_installment_amount) {
          rows.push(['Additional Amount', formatAmount(template.additional_installment_amount, template.additional_installment_amount_in_per)]);
        }

        if (template.balloon_payment) {
          rows.push(['Balloon Payment', formatAmount(template.balloon_payment, template.balloon_payment_in_per)]);
        }

        if (template.balloon_payment_frequency) {
          rows.push(['Balloon Frequency', capitalize(template.balloon_payment_frequency)]);
        }

        const rowsHtml = rows
          .map(([label, value]) => `<tr><td class="fw-semibold">${label}</td><td class="text-end">${value}</td></tr>`)
          .join('');

        return `
          <div class="text-center mb-3">
            <h5 class="fw-bold mb-2">${escapeHtml(template.name)}</h5>
            <span class="badge ${typeBadgeClass} fs-6">${typeLabel}</span>
            <span class="badge ${constructedBadgeClass} fs-6 ms-1">${constructedLabel}</span>
          </div>
          <div class="table-responsive">
            <table class="table table-sm mb-0"><tbody>${rowsHtml}</tbody></table>
          </div>
        `;
      }

      PERCENTAGE_TOGGLE_FIELDS.forEach(bindPercentageToggle);

      ['number_of_installments', 'installment_frequency', 'balloon_payment_frequency'].forEach(fieldId => {
        const field = document.getElementById(fieldId);
        field.addEventListener('input', renderStructurePreview);
        field.addEventListener('change', renderStructurePreview);
      });

      templateForm.addEventListener('submit', function(e) {
        clearAllErrors();

        const isValid = validateStructure()
          && validatePercentageAmount('additional_installment_amount', 'additional_installment_amount_in_per')
          && validatePercentageAmount('balloon_payment', 'balloon_payment_in_per');

        if (!isValid) e.preventDefault();
      });

      templateModalEl.addEventListener('hidden.bs.modal', resetForm);

      document.addEventListener('click', function(e) {
        const editButton = e.target.closest('.edit-template-btn');
        if (editButton) {
          clearAllErrors();
          try {
            fillFormForEdit(JSON.parse(editButton.getAttribute('data-template')));
            bootstrap.Modal.getOrCreateInstance(templateModalEl).show();
          } catch (error) {
            console.error('Failed to load template for editing:', error);
            alert('Error loading template');
          }
          return;
        }

        const infoButton = e.target.closest('.info-template-btn');
        if (infoButton) {
          const template = JSON.parse(infoButton.getAttribute('data-template'));
          document.getElementById('templateInfoBody').innerHTML = buildTemplateInfoHtml(template);
          bootstrap.Modal.getOrCreateInstance(document.getElementById('templateInfoModal')).show();
        }
      });
    })();
  </script>
@endpush
