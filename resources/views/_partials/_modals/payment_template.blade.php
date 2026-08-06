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



@push('styles')
  <style>
    .bg-light-primary {
      background-color: rgba(99, 115, 129, 0.08) !important;
      border: 1px solid rgba(99, 115, 129, 0.12) !important;
    }

    .bg-light-success {
      background-color: rgba(76, 175, 80, 0.08) !important;
      border: 1px solid rgba(76, 175, 80, 0.12) !important;
    }

    .bg-light-warning {
      background-color: rgba(255, 193, 7, 0.08) !important;
      border: 1px solid rgba(255, 193, 7, 0.12) !important;
    }

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
    document.addEventListener('DOMContentLoaded', function() {

      // Fee blocks configuration
      const feeKeys = [
        { key: 'booking_fee', surplus: false, required: false, alwaysPercentage: false },
        { key: 'development_fee', surplus: true, required: false, alwaysPercentage: false },
        { key: 'down_payment', surplus: false, required: false, alwaysPercentage: false },
        { key: 'confirmation_fee', surplus: false, required: false, alwaysPercentage: false },
        { key: 'possession_fee', surplus: true, required: false, alwaysPercentage: false },
        { key: 'corner_premium', surplus: false, required: false, alwaysPercentage: true },
        { key: 'park_facing_premium', surplus: false, required: false, alwaysPercentage: true },
        { key: 'main_boulevard_premium', surplus: false, required: false, alwaysPercentage: true }
      ];

      // Toggle fee blocks on/off
      document.querySelectorAll('.fee-enable-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
          const block = this.closest('.fee-block');
          const fields = block.querySelector('.fee-fields');
          const inputs = fields.querySelectorAll('input');

          if (this.checked) {
            fields.classList.remove('d-none');
            inputs.forEach(function(input) {
              input.disabled = false;
            });
          } else {
            fields.classList.add('d-none');
            inputs.forEach(function(input) {
              input.disabled = true;
              input.type === 'checkbox' ? (input.checked = false) : (input.value = '');
              clearFieldError(input);
            });
          }
        });
      });

      function extractFeeAmount(html) {
        if (!html || typeof html !== 'string') return null;
        if (html.includes('tabler-x')) return null;
        const match = html.match(/fw-medium">([^<]+)</);
        if (!match) return null;
        const numeric = parseFloat(match[1].replace(/[,%]/g, ''));
        return isNaN(numeric) ? null : numeric;
      }

      function resetTemplateForm() {
        document.getElementById('modalTitle').textContent = 'Add Payment Planning Template';
        document.getElementById('templateForm').reset();
        document.getElementById('templateId').value = '';

        document.querySelectorAll('.fee-block').forEach(function(block) {
          const toggle = block.querySelector('.fee-enable-toggle');
          if (toggle) {
            toggle.checked = false;

            const fields = block.querySelector('.fee-fields');
            if (fields) {
              fields.classList.add('d-none');
              fields.querySelectorAll('input').forEach(function(input) {
                input.disabled = true;
              });
            }
          }
        });

        clearValidationErrors();
      }

      // Clear all validation error messages and classes
      function clearValidationErrors() {
        document.querySelectorAll('.invalid-feedback').forEach(function(el) {
          el.remove();
        });
        document.querySelectorAll('.form-control, .form-check-input').forEach(function(el) {
          el.classList.remove('is-invalid');
        });
      }

      // Clear error for a single field only
      function clearFieldError(input) {
        input.classList.remove('is-invalid');
        const next = input.nextElementSibling;
        if (next && next.classList.contains('invalid-feedback')) {
          next.remove();
        }
      }

      // Add error message to a field
      function addFieldError(element, message) {
        clearFieldError(element);
        element.classList.add('is-invalid');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback d-block';
        errorDiv.textContent = message;
        element.insertAdjacentElement('afterend', errorDiv);
      }

      // Validate a single fee amount field (used for live + full validation)
      function validateFeeAmount(cfg) {
        const key = cfg.key;
        const amountInput = document.getElementById(key);
        const isPercentageCheckbox = document.getElementById(key + '_in_per');

        clearFieldError(amountInput);

        const amount = amountInput.value.trim();
        if (!amount) return true;

        const numAmount = parseFloat(amount);
        if (isNaN(numAmount)) {
          addFieldError(amountInput, 'Please enter a valid number');
          return false;
        }
        if (numAmount < 0) {
          addFieldError(amountInput, 'Amount cannot be negative');
          return false;
        }
        if ((cfg.alwaysPercentage || (isPercentageCheckbox && isPercentageCheckbox.checked)) && numAmount > 100) {
          addFieldError(amountInput, 'Percentage must be between 0-100');
          return false;
        }

        return true;
      }

      // Live validation: as you type, and when the % toggle changes
      feeKeys.forEach(function(cfg) {
        const amountInput = document.getElementById(cfg.key);
        const percentToggle = document.getElementById(cfg.key + '_in_per');

        amountInput.addEventListener('input', function() {
          validateFeeAmount(cfg);
        });

        if (percentToggle) {
          percentToggle.addEventListener('change', function() {
            validateFeeAmount(cfg);
          });
        }
      });

      // Full form validation on submit
      function validateTemplateForm() {
        clearValidationErrors();
        let isValid = true;

        const templateNameInput = document.getElementById('template_name');
        if (!templateNameInput.value.trim()) {
          addFieldError(templateNameInput, 'Template name is required');
          isValid = false;
        }

        feeKeys.forEach(function(cfg) {
          const block = document.querySelector('.fee-block[data-fee="' + cfg.key + '"]');
          const enableToggle = block.querySelector('.fee-enable-toggle');
          const amountInput = document.getElementById(cfg.key);
          const amount = amountInput.value.trim();

          const shouldValidate = cfg.required
            || (enableToggle && enableToggle.checked)
            || (!enableToggle && amount !== '');

          if (!shouldValidate) return;

          if (cfg.required && !amount) {
            addFieldError(amountInput, 'Amount is required');
            isValid = false;
            return;
          }

          if (!validateFeeAmount(cfg)) {
            isValid = false;
          }
        });

        return isValid;
      }

      // Handle form submission
      const templateForm = document.getElementById('templateForm');
      templateForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!validateTemplateForm()) return false;
        this.submit();
      });

      // Prefill the modal when an edit button is clicked
      document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-template-btn');
        if (!editBtn) return;

        const table = new DataTable('#payment_templates_table');
        const row = editBtn.closest('tr');
        const rowData = table.row(row).data();
        if (!rowData) return;

        resetTemplateForm();

        document.getElementById('modalTitle').textContent = 'Edit Payment Planning Template';
        document.getElementById('templateId').value = rowData.id;
        document.getElementById('template_name').value = rowData.name;

        feeKeys.forEach(function(cfg) {
          const key = cfg.key;
          const amount = extractFeeAmount(rowData[key]);
          const block = document.querySelector('.fee-block[data-fee="' + key + '"]');
          const enableToggle = block.querySelector('.fee-enable-toggle');
          const fields = block.querySelector('.fee-fields');

          if (amount !== null) {
            if (enableToggle) enableToggle.checked = true;
            if (fields) {
              fields.classList.remove('d-none');
              fields.querySelectorAll('input').forEach(function(input) {
                input.disabled = false;
              });
            }

            document.getElementById(key).value = amount;

            const percentToggle = document.getElementById(key + '_in_per');
            if (percentToggle && percentToggle.type === 'checkbox') {
              percentToggle.checked = !!rowData[key + '_in_per'];
            }

            if (cfg.surplus) {
              document.getElementById(key + '_surplus').checked = !!rowData[key + '_surplus'];
            }
          }
        });

        const modalEl = document.getElementById('templateModal');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      });

      document.getElementById('templateModal').addEventListener('hidden.bs.modal', function() {
        resetTemplateForm();
      });

    });
  </script>
@endpush
