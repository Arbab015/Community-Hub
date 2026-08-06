<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="modalTitle">Add Installment Template</h4>
          <p class="text-muted">Configure installment payment settings</p>
        </div>

        <form action="{{ route('installment-templates.store') }}" method="POST" id="templateForm">
          @csrf
          <input type="hidden" name="id" id="templateId">

          <div class="mb-3">
            <label class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="template_name" class="form-control"
                   placeholder="e.g., 5-Marla 5-Year Plan" required>
            <div class="invalid-feedback d-block" id="template_name_error"></div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Number of Installments <span class="text-danger">*</span></label>
            <input type="number" name="number_of_installments" id="number_of_installments" class="form-control"
                   placeholder="e.g., 24, 36, 48" min="1" required>
            <div class="invalid-feedback d-block" id="number_of_installments_error"></div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Installment Frequency <span class="text-danger">*</span></label>
            <select name="installment_frequency" id="installment_frequency" class="form-control" required>
              <option value="">Select frequency</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="biannually">Biannually</option>
              <option value="annually">Annually</option>
            </select>
            <div class="invalid-feedback d-block" id="installment_frequency_error"></div>
          </div>

          <hr class="my-3">

          <div class="mb-3">
            <label class="form-label fw-semibold">Additional Installment Amount <span
                class="text-danger">*</span></label>
            <div class="row align-items-center">
              <div class="col-md-7">
                <input type="number" name="additional_installment_amount" id="additional_installment_amount"
                       class="form-control" step="0.01" min="0" placeholder="Enter amount" required>
              </div>
              <div class="col-md-5">
                <div class="form-check form-switch">
                  <input type="hidden" name="additional_installment_amount_in_per" value="0">
                  <input type="checkbox" id="additional_installment_amount_in_per" class="form-check-input"
                         onchange="document.querySelector('input[name=additional_installment_amount_in_per]').value = this.checked ? 1 : 0">
                  <label class="form-check-label">As Percentage (%)</label>
                </div>
              </div>
            </div>
            <small class="d-block text-muted mt-1">Amount added to base price for installments. Percentage: 0-100,
              Amount: any value</small>
            <div class="invalid-feedback d-block" id="additional_installment_amount_error"></div>
          </div>

          <hr class="my-3">

          <div class="mb-3">
            <label class="form-label fw-semibold">Balloon Payment</label>
            <div class="row align-items-center">
              <div class="col-md-7">
                <input type="number" name="balloon_payment" id="balloon_payment" class="form-control"
                       step="0.01" min="0" placeholder="Enter amount (optional)">
              </div>
              <div class="col-md-5">
                <div class="form-check form-switch">
                  <input type="hidden" name="balloon_payment_in_per" value="0">
                  <input type="checkbox" id="balloon_payment_in_per" class="form-check-input"
                         onchange="document.querySelector('input[name=balloon_payment_in_per]').value = this.checked ? 1 : 0">
                  <label class="form-check-label">As Percentage (%)</label>
                </div>
              </div>
            </div>
            <small class="d-block text-muted mt-1">Large payment due at specified intervals. Percentage: 0-100, Amount:
              any value</small>
            <div class="invalid-feedback d-block" id="balloon_payment_error"></div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Balloon Payment Frequency</label>
            <select name="balloon_payment_frequency" id="balloon_payment_frequency" class="form-control">
              <option value="">Not applicable</option>
              <option value="quarterly">Quarterly</option>
              <option value="biannually">Biannually</option>
              <option value="annually">Annually</option>
            </select>
            <small class="d-block text-muted mt-1">Required when balloon payment amount is specified</small>
            <div class="invalid-feedback d-block" id="balloon_payment_frequency_error"></div>
          </div>

          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Save Template</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@push('scripts')
  <script>
    function clearErrors() {
      document.querySelectorAll('.invalid-feedback').forEach(el => {
        el.textContent = '';
        el.style.display = 'none';
      });
      document.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid'));
    }

    function showError(id, msg) {
      document.getElementById(id).classList.add('is-invalid');
      document.getElementById(id + '_error').textContent = msg;
      document.getElementById(id + '_error').style.display = 'block';
    }

    function validateAmount(id, toggleId) {
      const val = parseFloat(document.getElementById(id).value);
      if (!document.getElementById(id).value) return true;

      if (document.getElementById(toggleId).checked && val > 100) {
        showError(id, 'Percentage must be between 0-100');
        return false;
      }

      document.getElementById(id).classList.remove('is-invalid');
      document.getElementById(id + '_error').textContent = '';
      return true;
    }

    document.getElementById('additional_installment_amount_in_per').addEventListener('change', function() {
      validateAmount('additional_installment_amount', 'additional_installment_amount_in_per');
    });

    document.getElementById('balloon_payment_in_per').addEventListener('change', function() {
      validateAmount('balloon_payment', 'balloon_payment_in_per');
    });

    document.getElementById('additional_installment_amount').addEventListener('input', function() {
      if (document.getElementById('additional_installment_amount_in_per').checked) {
        validateAmount('additional_installment_amount', 'additional_installment_amount_in_per');
      }
    });

    document.getElementById('balloon_payment').addEventListener('input', function() {
      if (document.getElementById('balloon_payment_in_per').checked) {
        validateAmount('balloon_payment', 'balloon_payment_in_per');
      }
    });

    document.getElementById('templateForm').addEventListener('submit', function(e) {
      clearErrors();

      const balloon = document.getElementById('balloon_payment').value.trim();
      if (balloon && !document.getElementById('balloon_payment_frequency').value) {
        showError('balloon_payment_frequency', 'Required when balloon payment is set');
        e.preventDefault();
        return false;
      }

      if (!validateAmount('additional_installment_amount', 'additional_installment_amount_in_per')) {
        e.preventDefault();
        return false;
      }

      if (!validateAmount('balloon_payment', 'balloon_payment_in_per')) {
        e.preventDefault();
        return false;
      }
    });

    document.addEventListener('click', function(e) {
      const editBtn = e.target.closest('.edit-template-btn');
      if (!editBtn) return;

      clearErrors();

      try {
        const jsonStr = editBtn.getAttribute('data-template');
        const data = JSON.parse(jsonStr);

        document.getElementById('modalTitle').textContent = 'Edit Installment Template';
        document.getElementById('templateId').value = data.id;
        document.getElementById('template_name').value = data.name;
        document.getElementById('number_of_installments').value = data.number_of_installments;
        document.getElementById('installment_frequency').value = data.installment_frequency;
        document.getElementById('additional_installment_amount').value = data.additional_installment_amount;
        document.getElementById('balloon_payment').value = data.balloon_payment || '';
        document.getElementById('balloon_payment_frequency').value = data.balloon_payment_frequency || '';

        const isAdditional = Boolean(data.additional_installment_amount_in_per);
        document.getElementById('additional_installment_amount_in_per').checked = isAdditional;
        document.querySelector('input[name=additional_installment_amount_in_per]').value = isAdditional ? 1 : 0;

        const isBalloon = Boolean(data.balloon_payment_in_per);
        document.getElementById('balloon_payment_in_per').checked = isBalloon;
        document.querySelector('input[name=balloon_payment_in_per]').value = isBalloon ? 1 : 0;

        bootstrap.Modal.getOrCreateInstance(document.getElementById('templateModal')).show();
      } catch (error) {
        console.error('Error:', error);
        alert('Error loading template');
      }
    });

    document.getElementById('templateModal').addEventListener('hidden.bs.modal', function() {
      document.getElementById('modalTitle').textContent = 'Add Installment Template';
      document.getElementById('templateForm').reset();
      document.getElementById('templateId').value = '';
      document.querySelector('input[name=additional_installment_amount_in_per]').value = 0;
      document.querySelector('input[name=balloon_payment_in_per]').value = 0;
      clearErrors();
    });
  </script>
@endpush
