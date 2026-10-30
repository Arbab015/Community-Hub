@php
  $ownerableType = $ownerableType ?? '';
  $ownerableId = $ownerableId ?? '';
  $entityLabel = strtolower($entityLabel ?? 'property');
  $canCancelReservation = $canCancelReservation ?? true;
@endphp

@if($canCancelReservation && Auth()->user()->can('property_reserve_owner'))
  {{-- Cancel ownership reservation: refund of paid amount (charges + FBR tax) minus optional commission / reduction --}}
  <div class="modal fade" id="cancelOwnershipModal" tabindex="-1" aria-labelledby="cancelOwnershipTitle"
       aria-hidden="true"
       data-summary-url="{{ route('property_owners.cancellation.summary') }}"
       data-ownerable-type="{{ $ownerableType }}"
       data-ownerable-id="{{ $ownerableId }}">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">

        {{-- ================= Header ================= --}}
        <div class="modal-header border-0 pb-0 align-items-start">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-lg flex-shrink-0">
              <span class="avatar-initial rounded-3 bg-label-danger"><i class="ti tabler-user-off fs-2"></i></span>
            </div>
            <div>
              <h4 class="mb-1 fw-bold" id="cancelOwnershipTitle">Cancel Ownership Reservation</h4>
              <p class="text-muted small mb-0">
                The owner is refunded what they paid (charges + FBR tax), minus any reduction, and the
                {{ $entityLabel }} goes back on sale.
              </p>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body pt-4">
          <form action="{{ route('property_owners.cancel_reservation_settlement') }}" method="POST"
                id="cancelOwnershipForm" novalidate>
            @csrf
            <input type="hidden" name="ownerable_type" value="{{ $ownerableType }}">
            <input type="hidden" name="ownerable_id" value="{{ $ownerableId }}">

            {{-- ================= Step 1: commission / reduction ================= --}}
            <div class="mb-4">
              <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-label-primary rounded-pill">1</span>
                <label class="form-label fw-semibold mb-0">Commission / reduction on refund (paid amount + tax)</label>
                <span class="text-muted small">(optional)</span>
              </div>

              <div class="btn-group w-100 mb-3" role="group" aria-label="Commission type">
                <input type="radio" class="btn-check" name="commission_mode" id="commissionModeNone"
                       value="none" checked>
                <label class="btn btn-outline-secondary" for="commissionModeNone">
                  <i class="ti tabler-ban me-1"></i>None
                </label>

                <input type="radio" class="btn-check" name="commission_mode" id="commissionModeAmount"
                       value="amount">
                <label class="btn btn-outline-secondary" for="commissionModeAmount">
                  <i class="ti tabler-cash me-1"></i>Fixed amount
                </label>

                <input type="radio" class="btn-check" name="commission_mode" id="commissionModePercentage"
                       value="percentage">
                <label class="btn btn-outline-secondary" for="commissionModePercentage">
                  <i class="ti tabler-percentage me-1"></i>Percentage
                </label>
              </div>

              <div class="d-none" id="commissionValueSection">
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="ti tabler-rosette-discount"></i></span>
                  <input type="number" name="commission_value" id="commissionValueInput" class="form-control"
                         step="0.01" min="0" placeholder="0.00" inputmode="decimal"
                         aria-describedby="commissionHint">
                  <span class="input-group-text" id="commissionValueSuffix">PKR</span>
                </div>

                <div class="d-none flex-wrap gap-2 mt-2" id="commissionQuickPicks">
                  @foreach([1, 2, 5, 10] as $quickPercentage)
                    <button type="button" class="btn btn-sm btn-label-secondary"
                            data-quick-percentage="{{ $quickPercentage }}">{{ $quickPercentage }}%
                    </button>
                  @endforeach
                </div>

                <div class="invalid-feedback" id="commissionValueError">Enter a value greater than zero.</div>
                <div class="form-text" id="commissionHint"></div>
              </div>
            </div>

            {{-- ================= Step 2: ONE combined summary ================= --}}
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge bg-label-primary rounded-pill">2</span>
              <span class="fw-semibold">Settlement summary</span>
            </div>

            <div class="border rounded-3 overflow-hidden mb-4" id="settlementSummary" aria-live="polite">
              {{-- Net refund to owner (paid charges + FBR tax - reduction; shown in minus when the reduction is more than what was paid) --}}
              <div class="bg-label-primary p-3">
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <div>
                    <div class="text-uppercase small fw-semibold opacity-75">Net refund to owner</div>
                    <div class="fs-3 fw-bold lh-1 mt-1" data-field="returnTotal">&mdash;</div>
                  </div>
                  <span class="badge bg-label-info" data-field="paymentCount">&mdash;</span>
                </div>

                <div class="small mt-3 pt-3 border-top border-primary border-opacity-25">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Paid charges</span>
                    <span data-field="returnAmount">&mdash;</span>
                  </div>
                  <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">FBR tax</span>
                    <span data-field="returnTax">&mdash;</span>
                  </div>
                  <div class="d-flex justify-content-between fw-semibold border-top pt-2 mt-2 mb-1">
                    <span>Total paid</span>
                    <span data-field="paidTotal">&mdash;</span>
                  </div>
                  <div class="d-flex justify-content-between">
                    <span class="text-muted">Reduction / commission</span>
                    <span class="text-danger" data-field="reduction">0.00</span>
                  </div>
                </div>

                <div class="small text-muted mt-2" data-field="returnHint"></div>
              </div>
            </div>

            {{-- ================= Remarks ================= --}}
            <div class="mb-3">
              <label class="form-label fw-semibold" for="cancellationRemarks">
                Remarks <span class="text-muted fw-normal">(optional)</span>
              </label>
              <textarea name="remarks" id="cancellationRemarks" rows="2" maxlength="500" class="form-control"
                        placeholder="Reason for cancellation / notes for the receipt"></textarea>
            </div>

            <div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="alert">
              <i class="ti tabler-alert-triangle mt-1"></i>
              <span class="small">
                This ends the ownership and relists the {{ $entityLabel }}. The owner is refunded the paid amount
                (charges + FBR tax) minus any reduction. Payment records are
                <b>not deleted</b>; they are marked as returned and stay available for audit.
              </span>
            </div>
          </form>
        </div>

        {{-- ================= Footer ================= --}}
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="ti tabler-arrow-back-up me-1"></i>Keep Reservation
          </button>
          <button type="submit" class="btn btn-danger" id="confirmCancellationButton" form="cancelOwnershipForm">
            <i class="ti tabler-user-off me-1"></i>Confirm Cancellation
          </button>
        </div>
      </div>
    </div>
  </div>

  @push('scripts')
    <script>
      (() => {
        'use strict';

        const modalElement = document.getElementById('cancelOwnershipModal');
        if (!modalElement) return;

        // ---------- Elements ----------
        const form = document.getElementById('cancelOwnershipForm');
        const commissionValueSection = document.getElementById('commissionValueSection');
        const commissionValueInput = document.getElementById('commissionValueInput');
        const commissionValueSuffix = document.getElementById('commissionValueSuffix');
        const commissionQuickPicks = document.getElementById('commissionQuickPicks');
        const commissionHint = document.getElementById('commissionHint');
        const confirmButton = document.getElementById('confirmCancellationButton');
        const confirmButtonHtml = confirmButton.innerHTML;

        // Summary fields are tagged data-field="<name>".
        const field = name => modalElement.querySelector(`[data-field="${name}"]`);

        // ---------- State (filled from the server summary) ----------
        // paidTotal  = paid charges + FBR tax (what the owner actually paid).
        // baseAmount = payment plan final price; a percentage reduction is calculated on this.
        // Net refund = paidTotal - reduction (can be negative, e.g. nothing was paid).
        const settlement = { paidTotal: 0, baseAmount: 0, currency: 'PKR', loaded: false };
        let pendingRequest = null;

        // ---------- Helpers ----------
        const formatNumber = amount => Number(amount || 0).toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
        const formatMoney = amount => `${formatNumber(amount)} ${settlement.currency}`;
        const formatSignedMoney = amount => (amount < 0 ? '− ' : '') + formatMoney(Math.abs(amount));

        const getSelectedMode = () =>
          form.querySelector('input[name="commission_mode"]:checked')?.value || 'none';

        const getCommissionValue = () => {
          const value = parseFloat(commissionValueInput.value);
          return Number.isFinite(value) && value > 0 ? value : 0;
        };

        // Fixed amount is taken as entered; a percentage is calculated on the
        // payment plan final price.
        const calculateReduction = (mode, value) => {
          if (mode === 'percentage') return settlement.baseAmount * value / 100;
          if (mode === 'amount') return value;
          return 0;
        };

        // ---------- Rendering ----------
        const renderSummary = () => {
          if (!settlement.loaded) return;

          const reduction = calculateReduction(getSelectedMode(), getCommissionValue());
          const netRefund = Math.round((settlement.paidTotal - reduction) * 100) / 100;
          const totalField = field('returnTotal');

          field('reduction').textContent = (reduction > 0 ? '− ' : '') + formatMoney(reduction);
          totalField.textContent = formatSignedMoney(netRefund);
          totalField.classList.toggle('text-danger', netRefund < 0);
        };

        const renderCommissionControls = () => {
          const mode = getSelectedMode();
          const isPercentage = mode === 'percentage';

          commissionValueSection.classList.toggle('d-none', mode === 'none');
          commissionQuickPicks.classList.toggle('d-none', !isPercentage);
          commissionQuickPicks.classList.toggle('d-flex', isPercentage);
          commissionValueSuffix.textContent = isPercentage ? '%' : settlement.currency;

          if (isPercentage) {
            commissionValueInput.max = 100;
          } else {
            commissionValueInput.removeAttribute('max');
          }

          if (mode === 'none') {
            commissionHint.textContent = '';
          } else if (isPercentage) {
            const value = Math.min(getCommissionValue(), 100);
            commissionHint.textContent = `Calculated on the payment plan final price of ${formatMoney(settlement.baseAmount)}`
              + ` → ${formatMoney(calculateReduction(mode, value))}`;
          } else {
            commissionHint.textContent = 'The fixed amount is deducted from the paid amount (charges + FBR tax).';
          }

          renderSummary();
        };

        const showRefundPlaceholders = hintText => {
          ['returnAmount', 'returnTax', 'paidTotal', 'returnTotal', 'paymentCount'].forEach(name => {
            field(name).innerHTML = '&mdash;';
          });
          field('returnTotal').classList.remove('text-danger');
          field('reduction').textContent = '0.00';
          field('returnHint').textContent = hintText;
        };

        const clearCommissionError = () => commissionValueInput.classList.remove('is-invalid');

        // ---------- Events ----------
        form.querySelectorAll('input[name="commission_mode"]').forEach(radio => {
          radio.addEventListener('change', () => {
            clearCommissionError();
            if (getSelectedMode() === 'none') commissionValueInput.value = '';
            renderCommissionControls();
            if (getSelectedMode() !== 'none') commissionValueInput.focus();
          });
        });

        commissionValueInput.addEventListener('input', () => {
          clearCommissionError();
          renderCommissionControls();
        });

        commissionQuickPicks.addEventListener('click', event => {
          const quickButton = event.target.closest('[data-quick-percentage]');
          if (!quickButton) return;

          commissionValueInput.value = quickButton.dataset.quickPercentage;
          clearCommissionError();
          renderCommissionControls();
        });

        modalElement.addEventListener('show.bs.modal', async () => {
          const params = new URLSearchParams({
            ownerable_type: modalElement.dataset.ownerableType,
            ownerable_id: modalElement.dataset.ownerableId
          });

          settlement.loaded = false;
          settlement.paidTotal = 0;
          settlement.baseAmount = 0;
          showRefundPlaceholders('Loading…');

          pendingRequest?.abort();
          pendingRequest = new AbortController();

          try {
            const response = await fetch(`${modalElement.dataset.summaryUrl}?${params}`, {
              signal: pendingRequest.signal,
              headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error(`Request failed with status ${response.status}`);

            const data = await response.json();
            if (!data?.ok) throw new Error('Summary not available');

            settlement.paidTotal = Number(data.return_total) || 0;
            settlement.baseAmount = Number(data.commission_base) || 0;
            settlement.currency = data.currency || 'PKR';
            settlement.loaded = true;

            field('returnAmount').textContent = formatMoney(data.return_amount);
            field('returnTax').textContent = formatMoney(data.return_tax);
            field('paidTotal').textContent = formatMoney(data.return_total);
            field('paymentCount').textContent = `${data.payment_count} payment(s)`;
            field('returnHint').textContent = data.has_payment
              ? 'The reduction is deducted from the paid amount; the rest is returned to the owner.'
              : 'No paid amount found. Any reduction will show in minus (to be received from the owner).';

            renderCommissionControls(); // also renders the reduction and the net refund
          } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Could not load the cancellation summary:', error);
            showRefundPlaceholders('Could not load the payment summary. Close and reopen this window to try again.');
          }
        });

        modalElement.addEventListener('hidden.bs.modal', () => {
          pendingRequest?.abort();
          form.reset();
          clearCommissionError();
          settlement.paidTotal = 0;
          settlement.baseAmount = 0;
          settlement.loaded = false;
          confirmButton.disabled = false;
          confirmButton.innerHTML = confirmButtonHtml;
          renderCommissionControls();
        });

        form.addEventListener('submit', event => {
          if (getSelectedMode() !== 'none' && getCommissionValue() <= 0) {
            event.preventDefault();
            commissionValueInput.classList.add('is-invalid');
            commissionValueInput.focus();
            if (typeof notify === 'function') {
              notify('Enter a commission value, or choose "None".', 'warning');
            }
            return;
          }

          confirmButton.disabled = true;
          confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Cancelling…';
        });

        renderCommissionControls();
      })();
    </script>
  @endpush
@endif
