@php
  $isUnit = $property instanceof \App\Models\PropertyUnit;
  $entityLabel = $isUnit ? 'Unit' : 'Property';
  $ownerableType = $isUnit ? 'App\\Models\\PropertyUnit' : 'App\\Models\\Property';
  $ownerableId = $property->id;

  $activePlan = $property->ownershipPaymentPlans()
      ->whereHas('propertyOwner', fn ($q) => $q->whereNull('end_date'))
      ->latest('id')
      ->first();

  $allSnapshots = $property->ownershipPaymentPlans()
      ->with('propertyOwner.owner')
      ->orderByDesc('id')->get();

  $currency = $activePlan?->price_unit
      ?? \App\Models\PropertyOwnershipPaymentPlan::query()
          ->whereMorphedTo('propertyOwnerable', $property)
          ->latest('id')->value('price_unit')
      ?? 'PKR';

  if ($activePlan) {
      $ledgerService = app(\App\Support\PaymentLedgerService::class);
      $ledgerService->refreshStatuses($activePlan);

      $ledgerRows = $activePlan->ledgerRows()->orderBy('sequence_order')->get();
      if ($ledgerRows->isEmpty()) {
          try { $ledgerService->createLedgerFromSnapshot($activePlan); } catch (\Throwable $e) {}
          $ledgerService->refreshStatuses($activePlan);
      }
      $ledgerRows = $activePlan->ledgerRows()->orderBy('sequence_order')->get();
      $ledgerSummary = $ledgerService->summary($activePlan);

      $installmentCount = $ledgerRows->where('charge_type', 'installment')->count();
      $balloonCount = $ledgerRows->where('charge_type', 'balloon')->count();
      $totalInstallments = $installmentCount + $balloonCount;
      $perInstallment = (float) ($ledgerRows->where('charge_type', 'installment')->first()?->amount_due ?? 0);
      $perBalloon = (float) ($ledgerRows->where('charge_type', 'balloon')->first()?->amount_due ?? 0);
      $ledgerHasRows = $ledgerRows->isNotEmpty();
      $ledgerHasPayments = $activePlan->paymentTransactions()->exists();

      $installmentPayable = 0.0;
      if ($activePlan->with_installment) {
          try {
              $installmentPayable = (float) (\App\Support\PaymentPlanPresenter::buildFromSnapshot($activePlan)['final_price'] ?? 0);
          } catch (\Throwable $e) {
              $installmentPayable = (float) $activePlan->final_price;
          }
      }
  }

  $statusBadge = function ($status) {
      return match ($status) {
          'paid' => '<span class="badge bg-label-success"><i class="ti tabler-check me-1"></i>Paid</span>',
          'current_due' => '<span class="badge bg-label-warning"><i class="ti tabler-clock me-1"></i>Current Due</span>',
          'late' => '<span class="badge bg-label-danger"><i class="ti tabler-alert-triangle me-1"></i>Late</span>',
          default => '<span class="badge bg-label-secondary"><i class="ti tabler-clock me-1"></i>Pending</span>',
      };
  };
@endphp

<div class="planner">
  @if(! $activePlan)
    <div class="card border-0 shadow-sm rounded-3 p-4">
      <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted text-center">
        <i class="ti tabler-credit-card fs-2 opacity-50 mb-2"></i>
        <div class="fw-semibold text-dark mb-1">No active ownership</div>
        <small>Assign an owner and commit a payment plan first — payments will appear here.</small>
      </div>
    </div>
  @else
    @php
      $currentOwner = $activePlan->propertyOwner?->owner;
    @endphp

    <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-2">
          <i class="ti tabler-wallet text-primary fs-5"></i>
          <h5 class="fw-bold mb-0">Payments</h5>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <select class="form-select form-select-sm payment-view-switch" style="width:auto;" aria-label="Payment view">
            <option value="current" selected>Latest / Current Ownership</option>
            <option value="all">All Payments (History)</option>
          </select>
          @can('payment_collect')
            <button type="button" class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal" data-bs-target="#initiateModal"
                    @if($ledgerHasPayments)
                      disabled title="Payments are already recorded — the schedule cannot be re-initiated"
                    @else
                      title="Generate all charges and due dates for this plan"
              @endif>
              <i class="ti tabler-calendar-plus me-1"></i> Initiate Payments
            </button>
            <button type="button" class="btn btn-sm btn-primary"
                    data-bs-toggle="modal" data-bs-target="#paymentModal"
                    onclick="prefillPaymentForm(null)">
              <i class="ti tabler-plus me-1"></i> Record Payment
            </button>
          @endcan
        </div>
      </div>

      @if($currentOwner)
        <div class="alert alert-success d-flex align-items-center gap-2 flex-wrap mb-4">
          <i class="ti tabler-user-check"></i>
          <span><b>Current Owner:</b> {{ $currentOwner->first_name }} {{ $currentOwner->last_name }} ({{ $currentOwner->email }})</span>
          <span class="badge bg-label-secondary ms-auto">
            Since {{ $activePlan->propertyOwner->start_date?->format('d M Y') }}
          </span>
        </div>
      @endif

      {{-- Latest / current ownership ledger --}}
      <div class="payment-view-panel payment-panel-current">
        <div class="row g-2 mb-3">
          <div class="col-6 col-xl-2">
            <div class="border rounded-3 p-3 h-100 bg-label-secondary">
              <small class="micro-label text-muted text-uppercase d-block mb-1">Total Due</small>
              <div class="fw-semibold">{{ number_format($ledgerSummary['total_due'], 2) }} {{ $currency }}</div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="border rounded-3 p-3 h-100 bg-label-success">
              <small class="micro-label text-uppercase d-block mb-1 text-success">Total Paid</small>
              <div class="fw-semibold text-success"
                   id="summary-total-paid">{{ number_format($ledgerSummary['total_paid'], 2) }} {{ $currency }}</div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="border rounded-3 p-3 h-100 bg-label-danger">
              <small class="micro-label text-uppercase d-block mb-1 text-danger">Remaining</small>
              <div class="fw-semibold text-danger"
                   id="summary-remaining">{{ number_format($ledgerSummary['remaining'], 2) }} {{ $currency }}</div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="border rounded-3 p-3 h-100 bg-label-warning">
              <small class="micro-label text-uppercase d-block mb-1 text-warning">Next Due</small>
              <div class="fw-semibold text-warning" id="summary-next-due">
                @if($ledgerSummary['next_due_date'])
                  {{ $ledgerSummary['next_due_date']->format('d M Y') }}
                @else
                  —
                @endif
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="border rounded-3 p-3 h-100 bg-label-warning">
              <small class="micro-label text-uppercase d-block mb-1 text-warning">Overdue</small>
              <div class="fw-semibold text-warning"
                   id="summary-overdue">{{ $ledgerSummary['by_status']['late'] + $ledgerSummary['by_status']['current_due'] }}</div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="border rounded-3 p-3 h-100 bg-label-primary">
              <small class="micro-label text-uppercase d-block mb-1 text-primary">Paid %</small>
              <div class="fw-semibold" id="summary-paid-percent">{{ $ledgerSummary['paid_percent'] }}%</div>
            </div>
          </div>
          @if($installmentPayable > 0)
            <div class="col-6 col-xl-2">
              <div class="border rounded-3 p-3 h-100 bg-label-info"
                   title="Total amount payable through installments and balloon payments">
                <small class="micro-label text-uppercase d-block mb-1 text-info">Total Payable</small>
                <div class="fw-semibold">{{ number_format($installmentPayable, 2) }} {{ $currency }}</div>
              </div>
            </div>
          @endif
          @if($totalInstallments)
            <div class="col-6 col-xl-2">
              <div class="border rounded-3 p-3 h-100 bg-label-info"
                   title="Total number of payments in this plan — regular installments plus balloons">
                <small class="micro-label text-uppercase d-block mb-1 text-info">Total Installments</small>
                <div class="fw-semibold">{{ $totalInstallments }}</div>
              </div>
            </div>
          @endif
          @if($balloonCount)
            <div class="col-6 col-xl-2">
              <div class="border rounded-3 p-3 h-100 bg-label-info"
                   title="Per balloon amount and how many balloon payments are due">
                <small class="micro-label text-uppercase d-block mb-1 text-info">Per Balloon</small>
                <div class="fw-semibold">{{ number_format($perBalloon, 2) }} {{ $currency }} x {{ $balloonCount }}</div>
              </div>
            </div>
          @endif
          @if($installmentCount)
            <div class="col-6 col-xl-2">
              <div class="border rounded-3 p-3 h-100 bg-label-primary"
                   title="Per installment amount and how many regular installments are due">
                <small class="micro-label text-uppercase d-block mb-1 text-primary">Per Installment</small>
                <div class="fw-semibold">{{ number_format($perInstallment, 2) }} {{ $currency }}
                  x {{ $installmentCount }}</div>
              </div>
            </div>
          @endif
        </div>

        @include('components.property.ledger-table', [
            'ledgerRows' => $ledgerRows,
            'currency' => $currency,
            'showActions' => true,
        ])
      </div>

      {{-- All payments history (across every ownership of this property) --}}
      <div class="payment-view-panel payment-panel-all d-none">
        @forelse($allSnapshots as $histPlan)
          @php
            $histLedgerService = app(\App\Support\PaymentLedgerService::class);
            $histLedgerService->refreshStatuses($histPlan);
            $histRows = $histPlan->ledgerRows()->orderBy('sequence_order')->get();
            if ($histRows->isEmpty()) {
                try { $histLedgerService->createLedgerFromSnapshot($histPlan); } catch (\Throwable $e) {}
                $histRows = $histPlan->ledgerRows()->orderBy('sequence_order')->get();
            }
            $histOwner = $histPlan->propertyOwner?->owner;
            $histCurrency = $histPlan->price_unit ?? $currency;
            $histPaid = (float) $histRows->sum('amount_paid');
            $isActivePlan = $histPlan->id === $activePlan->id;
            $histStart = $histPlan->propertyOwner?->start_date?->format('d M Y');
            $histEnd = $histPlan->propertyOwner?->end_date?->format('d M Y');
          @endphp
          <div class="border rounded-3 overflow-hidden mb-3">
            <div
              class="px-3 py-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-between flex-wrap gap-2">
              <span class="fw-semibold small text-primary">
                <i
                  class="ti tabler-user me-1"></i>{{ $histOwner->first_name ?? 'Owner' }} {{ $histOwner->last_name ?? '' }}
                @if($isActivePlan)
                  <span class="badge bg-success ms-1">Current</span>
                @endif
              </span>
              <span class="small text-muted">{{ $histStart ?? '—' }} → {{ $histEnd ?? 'ongoing' }}
                · Paid {{ number_format($histPaid, 2) }} {{ $histCurrency }}</span>
            </div>
            <div class="p-2">
              @include('components.property.ledger-table', [
                  'ledgerRows' => $histRows,
                  'currency' => $histCurrency,
                  'showActions' => $isActivePlan,
              ])
            </div>
          </div>
        @empty
          <div class="text-center py-4 text-muted">
            <i class="ti tabler-history fs-1 opacity-50 mb-2"></i>
            <div>No ownership history yet.</div>
          </div>
        @endforelse
      </div>
    </div>

    {{-- Record Payment Modal --}}
    @can('payment_collect')
      <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form action="{{ route('ownership_payment_plans.payments.store', $activePlan) }}" method="POST">
              @csrf
              <div class="modal-header">
                <h5 class="modal-title fw-bold">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Apply To <span class="text-danger">*</span></label>
                  <select class="form-select" name="ledger_id" id="payLedgerId">
                    <option value="">Auto — earliest outstanding charges</option>
                    @foreach($ledgerRows as $row)
                      @if($row->remaining_balance > 0)
                        <option value="{{ $row->id }}" data-amount="{{ $row->remaining_balance }}">
                          #{{ $loop->iteration }} · {{ $row->display_name }} —
                          Balance {{ number_format($row->remaining_balance, 2) }} {{ $currency }}
                        </option>
                      @endif
                    @endforeach
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">{{ $currency }}</span>
                    <input type="number" step="0.01" min="0.01" name="amount" id="payAmount"
                           class="form-control" required placeholder="0.00">
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                  <select class="form-select" name="payment_method" id="payMethod" required>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="card">Card</option>
                    <option value="mobile_wallet">Mobile Wallet</option>
                    <option value="adjustment">Adjustment</option>
                    <option value="waiver">Waiver</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Payment Date</label>
                  <input type="date" name="paid_at" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Payment Reference <span class="text-danger" id="payRefAsterisk">*</span></label>
                  <input type="text" name="reference_no" id="payReference" class="form-control"
                         placeholder="e.g. Cheque #1240, Txn ref, Wallet ID, Cash note">
                  <small class="form-text text-muted">How this payment was made — cheque no., bank/wallet transaction
                    ref, or cash note.</small>
                </div>
                <div class="mb-2">
                  <label class="form-label fw-semibold">Description</label>
                  <textarea name="description" class="form-control" rows="2" placeholder="Optional"></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ti tabler-cash me-1"></i> Save Payment</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endcan

    @can('payment_collect')
      <div class="modal fade" id="initiateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <form action="{{ route('ownership_payment_plans.payments.initiate', $activePlan) }}" method="POST">
              @csrf
              <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="ti tabler-calendar-plus me-1 text-primary"></i> Initiate
                  Payments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label fw-semibold" for="initiateAt">Start / Due Date <span
                      class="text-danger">*</span></label>
                  <input type="date" name="initiate_at" id="initiateAt" class="form-control"
                         value="{{ $activePlan->propertyOwner?->start_date?->toDateString() ?? now()->toDateString() }}">

                </div>
                @if($ledgerHasPayments)
                  <p class="mb-0 text-muted">Payments are already recorded for this plan, so the charge schedule cannot
                    be re-initiated.</p>
                @elseif($ledgerHasRows)
                  <p class="mb-0 text-muted">Charges already exist with no payments recorded. Re-initiating will
                    regenerate the full schedule with updated due dates.</p>
                @else
                  <p class="mb-0 text-muted">This will generate the complete charge schedule with due dates for this
                    ownership's payment plan.</p>
                @endif
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                @if(! $ledgerHasPayments)
                  <button type="submit" class="btn btn-primary"><i class="ti tabler-calendar-plus me-1"></i> Confirm
                    Initiate
                  </button>
                @endif
              </div>
            </form>
          </div>
        </div>
      </div>
    @endcan

    @can('payment_collect')
      <div class="modal fade" id="unpayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="ti tabler-rotate-clockwise-2 me-1 text-warning"></i> Refund
                Payment</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p class="mb-0 text-muted">This will undo the payment recorded against <b id="unpayChargeName"></b> and
                restore its outstanding balance.</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-warning" id="confirmUnpayBtn"><i class="ti tabler-reload me-1"></i>
                Confirm Refund
              </button>
            </div>
          </div>
        </div>
      </div>
    @endcan

    @push('scripts')
      <script>
        var PAYMENT_CURRENCY = '{{ $currency }}';
        var UNPAY_URL = '{{ route('ownership_payment_plans.payments.unpay', ['row' => '__ROW__']) }}';
        var unpayTargetUuid = null;

        function prefillPaymentForm(ledgerId, amount) {
          var select = document.getElementById('payLedgerId');
          if (select) {
            select.value = ledgerId ? String(ledgerId) : '';
          }
          var amountEl = document.getElementById('payAmount');
          if (amountEl && amount) {
            amountEl.value = Number(amount).toFixed(2);
          } else if (amountEl) {
            amountEl.value = '';
          }
          var refEl = document.getElementById('payReference');
          if (refEl && !ledgerId) refEl.value = '';
          syncRefRequirement();
        }

        function syncRefRequirement() {
          var methodSel = document.getElementById('payMethod');
          var refEl = document.getElementById('payReference');
          var ast = document.getElementById('payRefAsterisk');
          if (!refEl) return;
          var needsRef = !methodSel || methodSel.value !== 'cash';
          refEl.required = needsRef;
          if (ast) ast.style.display = needsRef ? '' : 'none';
        }

        function formatMoney(n) {
          return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function buildStatusBadge(status) {
          var map = {
            paid: '<span class="badge bg-label-success"><i class="ti tabler-check me-1"></i>Paid</span>',
            current_due: '<span class="badge bg-label-warning"><i class="ti tabler-clock me-1"></i>Current Due</span>',
            late: '<span class="badge bg-label-danger"><i class="ti tabler-alert-triangle me-1"></i>Late</span>',
            pending: '<span class="badge bg-label-secondary"><i class="ti tabler-clock me-1"></i>Pending</span>'
          };
          return map[status] || map.pending;
        }

        function payButtonHtml(id, amount) {
          var pay = formatMoney(amount) + ' ' + PAYMENT_CURRENCY;
          return '<button type="button" class="btn btn-sm btn-icon btn-outline-success" data-bs-toggle="modal" data-bs-target="#paymentModal" title="Pay ' + pay + '" onclick="prefillPaymentForm(' + id + ', ' + Number(amount).toFixed(2) + ')">' +
            '<i class="ti tabler-cash"></i></button>';
        }

        function updateLedgerRow(row) {
          var tr = document.getElementById('ledger-row-' + row.uuid);
          if (!tr) return;
          tr.querySelector('.row-amount-paid').textContent = formatMoney(row.amount_paid);
          tr.querySelector('.row-balance').textContent = formatMoney(row.remaining_balance);
          tr.querySelector('.row-status').innerHTML = buildStatusBadge(row.payment_status);
          var actions = tr.querySelector('.row-actions');
          if (actions) {
            if (row.remaining_balance > 0) {
              actions.innerHTML = payButtonHtml(row.id, row.remaining_balance);
            } else {
              actions.innerHTML = '<span class="text-muted text-tiny">Paid</span>';
            }
          }
        }

        function updateSummary(s) {
          var el;
          if ((el = document.getElementById('summary-total-paid'))) el.textContent = formatMoney(s.total_paid) + ' ' + PAYMENT_CURRENCY;
          if ((el = document.getElementById('summary-remaining'))) el.textContent = formatMoney(s.remaining) + ' ' + PAYMENT_CURRENCY;
          if ((el = document.getElementById('summary-paid-percent'))) el.textContent = s.paid_percent + '%';
          if ((el = document.getElementById('summary-overdue'))) el.textContent = s.overdue;
          if ((el = document.getElementById('summary-next-due'))) el.textContent = s.next_due_date;
        }

        function openUnpayModal(uuid, name) {
          unpayTargetUuid = uuid;
          var nameEl = document.getElementById('unpayChargeName');
          if (nameEl) nameEl.textContent = name;
          var modalEl = document.getElementById('unpayModal');
          if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
          }
        }

        function showToast(message, type) {
          if (typeof Notyf !== 'undefined') {
            var nf = new Notyf({ duration: 4000, ripple: false });
            if (type === 'error') nf.error(message); else nf.success(message);
          } else {
            alert(message);
          }
        }

        function hideUnpayModal() {
          var modalEl = document.getElementById('unpayModal');
          if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          }
        }

        document.addEventListener('DOMContentLoaded', function() {
          var methodSel = document.getElementById('payMethod');
          if (methodSel) {
            methodSel.addEventListener('change', syncRefRequirement);
            syncRefRequirement();
          }

          var confirmBtn = document.getElementById('confirmUnpayBtn');
          if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
              if (!unpayTargetUuid) return;
              fetch(UNPAY_URL.replace('__ROW__', unpayTargetUuid), {
                method: 'POST',
                headers: {
                  'X-CSRF-TOKEN': '{{ csrf_token() }}',
                  'Accept': 'application/json',
                  'Content-Type': 'application/json'
                }
              })
                .then(function(r) {
                  return r.json().catch(function() {
                    return { ok: false, message: 'Unexpected server response.' };
                  });
                })
                .then(function(data) {
                  hideUnpayModal();
                  unpayTargetUuid = null;
                  if (data.ok) {
                    updateLedgerRow(data.row);
                    updateSummary(data.summary);
                    showToast(data.message, 'success');
                  } else {
                    showToast(data.message || 'Could not reverse this payment.', 'error');
                  }
                })
                .catch(function() {
                  hideUnpayModal();
                  unpayTargetUuid = null;
                  showToast('Something went wrong while reversing the payment.', 'error');
                });
            });
          }

          var sw = document.querySelector('.payment-view-switch');
          if (!sw) return;
          sw.addEventListener('change', function() {
            var showAll = this.value === 'all';
            document.querySelector('.payment-panel-current')?.classList.toggle('d-none', showAll);
            document.querySelector('.payment-panel-all')?.classList.toggle('d-none', !showAll);
          });
        });
      </script>
    @endpush
  @endif
</div>
