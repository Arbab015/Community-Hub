{{-- Single modal for add + edit late fee rule --}}
<div class="modal fade" id="lateFeeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="lateFeeModalTitle">Add Late Fee Rule</h4>
          <p class="text-muted mb-0">Set the fine applied when a payment is late</p>
        </div>

        <form action="{{ route('late_fee_rules.store') }}" method="POST" id="lateFeeForm">
          @csrf
          <input type="hidden" name="id" id="late_fee_rule_id">

          <div class="mb-3">
            <label class="form-label fw-semibold required" for="late_fee_rule_society">Select Society</label>
            <select id="late_fee_rule_society" name="society_id" class="form-select" required>
              <option value="">-- Select Society --</option>
              @foreach($societies as $society)
                <option value="{{ $society->id }}">{{ $society->name }}</option>
              @endforeach
            </select>
            <div class="invalid-feedback d-block" id="late_fee_rule_society_error"></div>
            <div class="alert alert-info small py-2 px-3 mt-2 mb-0 d-none" id="late_fee_rule_society_info"></div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="late_fee_rule_grace_period">Grace Period (days)</label>
            <div class="input-group">
              <input type="number" id="late_fee_rule_grace_period" name="grace_period_days" class="form-control"
                     min="0" step="1" placeholder="e.g. 15">
              <span class="input-group-text">days</span>
            </div>
            <small class="d-block text-muted">
              The smallest grace period is free for every rule. After it, each rule applies from its own day
              (16-30 uses the 15-day rule, 31-43 uses the 30-day rule, and so on). Leave blank for a catch-all
              rule after the last tier.
            </small>
            <div class="invalid-feedback d-block" id="late_fee_rule_grace_period_error"></div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold required" for="late_fee_rule_fine_amount">Late Fee</label>
            <div class="input-group">
              <input type="number" id="late_fee_rule_fine_amount" name="fine_amount" class="form-control"
                     min="0.01" step="0.01" placeholder="Enter fine or percentage" required>
              <span class="input-group-text" id="late_fee_rule_fine_suffix">PKR</span>
            </div>
            <div class="form-check form-switch mt-2">
              <input type="hidden" name="fine_type" id="late_fee_rule_fine_type" value="fixed">
              <input type="checkbox" role="switch" id="late_fee_rule_is_percentage" class="form-check-input">
              <label class="form-check-label small text-muted " for="late_fee_rule_is_percentage">As Percentage
                (%)</label>
            </div>
            <small class="d-block text-muted">Percentage must be between 0-100.</small>
            <div class="invalid-feedback d-block" id="late_fee_rule_fine_amount_error"></div>
          </div>

          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
              <i class="ti tabler-check me-1"></i> Save Late Fee Rule
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@push('scripts')
  <script>
    (function() {
      const societyRuleMeta = @json($societyRuleMeta ?? []);

      const modalEl = document.getElementById('lateFeeModal');
      const form = document.getElementById('lateFeeForm');
      const societySelect = document.getElementById('late_fee_rule_society');
      const societyInfo = document.getElementById('late_fee_rule_society_info');
      const amountInput = document.getElementById('late_fee_rule_fine_amount');
      const percentageToggle = document.getElementById('late_fee_rule_is_percentage');
      const fineTypeHidden = document.getElementById('late_fee_rule_fine_type');
      const suffix = document.getElementById('late_fee_rule_fine_suffix');

      function showAmountError(message) {
        amountInput.classList.add('is-invalid');
        document.getElementById('late_fee_rule_fine_amount_error').textContent = message;
      }

      function clearAmountError() {
        amountInput.classList.remove('is-invalid');
        document.getElementById('late_fee_rule_fine_amount_error').textContent = '';
      }

      function syncPercentageUI() {
        suffix.textContent = percentageToggle.checked ? '%' : 'PKR';
        fineTypeHidden.value = percentageToggle.checked ? 'percentage' : 'fixed';
      }

      function validatePercentage() {
        if (percentageToggle.checked && amountInput.value !== '' && parseFloat(amountInput.value) > 100) {
          showAmountError('Percentage must be between 0-100');
          return false;
        }
        clearAmountError();
        return true;
      }

      function updateSocietyInfo() {
        const societyId = societySelect.value;

        if (!societyId) {
          societyInfo.classList.add('d-none');
          societyInfo.textContent = '';
          return;
        }

        const meta = societyRuleMeta[societyId];

        if (!meta) {
          societyInfo.textContent = 'This society has no late fee rules yet. The type you choose here will apply to all its future rules.';
        } else {
          const typeLabel = meta.type === 'percentage' ? 'percentage (%)' : 'fixed amount (PKR)';
          const countLabel = meta.count + (meta.count === 1 ? ' rule' : ' rules');
          societyInfo.textContent = 'This society has ' + countLabel + ' and uses ' + typeLabel + ' fines. All its rules must use the same type.';
        }

        societyInfo.classList.remove('d-none');
      }

      societySelect.addEventListener('change', updateSocietyInfo);

      percentageToggle.addEventListener('change', function() {
        syncPercentageUI();
        validatePercentage();
      });

      amountInput.addEventListener('input', function() {
        if (percentageToggle.checked) validatePercentage();
      });

      form.addEventListener('submit', function(e) {
        if (!validatePercentage()) e.preventDefault();
      });

      function resetForm() {
        form.reset();
        document.getElementById('late_fee_rule_id').value = '';
        document.getElementById('lateFeeModalTitle').textContent = 'Add Late Fee Rule';
        percentageToggle.checked = false;
        syncPercentageUI();
        clearAmountError();
        updateSocietyInfo();
      }

      modalEl.addEventListener('hidden.bs.modal', resetForm);

      document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-late-fee-rule-btn');
        if (!editBtn) return;

        resetForm();
        try {
          const rule = JSON.parse(editBtn.getAttribute('data-rule'));
          document.getElementById('lateFeeModalTitle').textContent = 'Edit Late Fee Rule';
          document.getElementById('late_fee_rule_id').value = rule.id;
          societySelect.value = rule.society_id;
          updateSocietyInfo();
          document.getElementById('late_fee_rule_grace_period').value =
            rule.grace_period_days === null ? '' : rule.grace_period_days;
          amountInput.value = rule.fine_amount;
          percentageToggle.checked = rule.fine_type === 'percentage';
          syncPercentageUI();
          bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } catch (error) {
          console.error('Failed to load late fee rule for editing:', error);
          alert('Error loading late fee rule');
        }
      });
    })();
  </script>
@endpush
