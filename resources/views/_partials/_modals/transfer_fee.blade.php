{{-- Single modal for add + edit transfer fee --}}
<div class="modal fade" id="transferFeeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h4 class="mb-2 fw-bolder" id="transferFeeModalTitle">Add Transfer Fee</h4>
          <p class="text-muted mb-0">Store the transfer fee for a property size range</p>
        </div>

        <form action="{{ route('transfer_fee.store') }}" method="POST" id="transferFeeForm">
          @csrf
          <input type="hidden" name="id" id="transfer_fee_id">

          <div class="mb-3">
            <label class="form-label fw-semibold" for="transfer_fee_society">Select Society</label>
            <select id="transfer_fee_society" name="society_id" class="form-select" required>
              <option value="">-- Select Society --</option>
              @foreach($societies as $society)
                <option value="{{ $society->id }}">{{ $society->name }}</option>
              @endforeach
            </select>
            <div class="invalid-feedback d-block" id="transfer_fee_society_error"></div>
          </div>

          {{-- Property Size + Size Unit (same as Apply Listing / Apply Payment Plan modals) --}}
          <div class="row g-3 mb-3">
            <div class="col-8">
              <label class="form-label text-uppercase fw-semibold" for="transfer_fee_size_range">Property Size</label>
              <select class="form-select" name="size_range" id="transfer_fee_size_range" required>
                <option value="">Select Property Size</option>
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
              <div class="invalid-feedback d-block" id="transfer_fee_size_range_error"></div>
            </div>
            <div class="col-4">
              <label class="form-label text-uppercase fw-semibold" for="transfer_fee_size_unit">Size Unit</label>
              <select class="form-select" name="size_unit" id="transfer_fee_size_unit" required>
                <option value="marla">Marla</option>
                <option value="square_yard">Sq. Yard</option>
              </select>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold" for="transfer_fee_fee">Transfer Fee</label>
            <div class="input-group">
              <input type="number" id="transfer_fee_fee" name="fee" class="form-control" min="0" step="0.01"
                     placeholder="Enter fee or percentage" required>
              <span class="input-group-text" id="transfer_fee_fee_suffix">PKR</span>
            </div>
            <div class="form-check form-switch mt-2">
              <input type="hidden" name="is_percentage" value="0">
              <input type="checkbox" role="switch" id="transfer_fee_is_percentage" class="form-check-input"
                     value="1">
              <label class="form-check-label small text-muted" for="transfer_fee_is_percentage">As Percentage (%)</label>
            </div>
            <small class="d-block text-muted">Percentage must be between 0-100.</small>
            <div class="invalid-feedback d-block" id="transfer_fee_fee_error"></div>
          </div>

          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
              <i class="ti tabler-check me-1"></i> Save Transfer Fee
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
      const KANAL_IN_MARLA = 20;

      const modalEl = document.getElementById('transferFeeModal');
      const form = document.getElementById('transferFeeForm');
      const feeInput = document.getElementById('transfer_fee_fee');
      const percentageToggle = document.getElementById('transfer_fee_is_percentage');
      const percentageHidden = form.querySelector('input[type="hidden"][name="is_percentage"]');
      const suffix = document.getElementById('transfer_fee_fee_suffix');

      // Mirrors PropertyRangeService::parseSizeRange (1 kanal = 20 marla).
      function parseRange(value) {
        const parts = value.split('-');
        const isKanal = parts.includes('kanal');
        const isPlus = parts.includes('plus');

        let min = parseFloat(parts[0]);
        let max = isPlus ? null : parseFloat(parts[1] ?? parts[0]);

        if (isKanal) {
          min *= KANAL_IN_MARLA;
          if (max !== null) max *= KANAL_IN_MARLA;
        }

        return [min, max];
      }

      function sizeRangeValueFor(min, max) {
        const minVal = (min === null || min === undefined || min === '') ? null : parseFloat(min);
        const maxVal = (max === null || max === undefined || max === '') ? null : parseFloat(max);

        for (const opt of document.querySelectorAll('#transfer_fee_size_range option')) {
          if (!opt.value) continue;
          const [oMin, oMax] = parseRange(opt.value);
          if (oMin === minVal && oMax === maxVal) return opt.value;
        }

        return '';
      }

      function showFeeError(message) {
        feeInput.classList.add('is-invalid');
        document.getElementById('transfer_fee_fee_error').textContent = message;
      }

      function clearFeeError() {
        feeInput.classList.remove('is-invalid');
        document.getElementById('transfer_fee_fee_error').textContent = '';
      }

      function syncPercentageUI() {
        suffix.textContent = percentageToggle.checked ? '%' : 'PKR';
        percentageHidden.value = percentageToggle.checked ? 1 : 0;
      }

      function validatePercentage() {
        if (percentageToggle.checked && feeInput.value !== '' && parseFloat(feeInput.value) > 100) {
          showFeeError('Percentage must be between 0-100');
          return false;
        }
        clearFeeError();
        return true;
      }

      percentageToggle.addEventListener('change', function() {
        syncPercentageUI();
        validatePercentage();
      });

      feeInput.addEventListener('input', function() {
        if (percentageToggle.checked) validatePercentage();
      });

      form.addEventListener('submit', function(e) {
        if (!validatePercentage()) e.preventDefault();
      });

      function resetForm() {
        form.reset();
        document.getElementById('transfer_fee_id').value = '';
        document.getElementById('transferFeeModalTitle').textContent = 'Add Transfer Fee';
        percentageToggle.checked = false;
        syncPercentageUI();
        clearFeeError();
      }

      modalEl.addEventListener('hidden.bs.modal', resetForm);

      document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-transfer-fee-btn');
        if (!editBtn) return;

        resetForm();
        try {
          const fee = JSON.parse(editBtn.getAttribute('data-fee'));
          document.getElementById('transferFeeModalTitle').textContent = 'Edit Transfer Fee';
          document.getElementById('transfer_fee_id').value = fee.id;
          document.getElementById('transfer_fee_society').value = fee.society_id;
          document.getElementById('transfer_fee_size_range').value = sizeRangeValueFor(fee.min_size, fee.max_size);
          document.getElementById('transfer_fee_size_unit').value = fee.size_unit;
          feeInput.value = fee.fee;
          percentageToggle.checked = Boolean(fee.is_percentage);
          syncPercentageUI();
          bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } catch (error) {
          console.error('Failed to load transfer fee for editing:', error);
          alert('Error loading transfer fee');
        }
      });
    })();
  </script>
@endpush
