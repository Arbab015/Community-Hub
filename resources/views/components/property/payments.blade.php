@php
  $isUnit = $property instanceof \App\Models\PropertyUnit;
  $entityLabel = $isUnit ? 'Unit' : 'Property';
  $ownerableType = $isUnit ? 'App\\Models\\PropertyUnit' : 'App\\Models\\Property';
  $ownerableId = $property->id;

  $viewer = auth()->user();
  $visibility = app(\App\Services\Property\PaymentVisibilityService::class);
  $isMemberViewer = $visibility->isRestricted($viewer);

  $allSnapshots = $visibility->visiblePlans($property, $viewer);

  // The plan of the ownership holding the property right now. For a member
  // this only resolves while the property is still theirs.
  $activeOwnershipPlan = $allSnapshots->first(fn ($plan) => $plan->propertyOwner?->isCurrent());

  // The live ownership, whoever holds it - a member has to be able to see that
  // the property has been transferred to somebody else.
  $currentOwnership = $visibility->activeOwnership($property);
  $viewerHoldsProperty = $currentOwnership
      && (int) $currentOwnership->owner_id === (int) ($viewer?->id ?? 0);
  $currentOwnerName = trim(
      ($currentOwnership?->owner?->first_name ?? '').' '.($currentOwnership?->owner?->last_name ?? '')
  );


  $activePlan = $activeOwnershipPlan ?? $allSnapshots->first();
  $planStatus = $activePlan?->status;
  $planOwner = $activePlan?->propertyOwner?->owner;

  // Only the live ownership's plan can be worked on: everything else is a
  // record of an ownership that has already ended.
  $isCurrentOwnershipPlan = $activePlan && $currentOwnership
      && (int) $activePlan->property_owner_id === (int) $currentOwnership->id;

  $canCollect = $isCurrentOwnershipPlan && ! in_array($planStatus, ['completed', 'reversed', 'cancelled'], true);
  $canAct = $isCurrentOwnershipPlan && ! in_array($planStatus, ['reversed', 'cancelled'], true);

  // The transfer that handed this plan to its owner. A transfer-fee plan is
  // created by a transfer, so its ownership points back at the transfer - the
  // slip of that transfer is the paper trail for this ownership.
  $planTransfer = null;
  if ($activePlan && $activePlan->plan_type === \App\Enums\PlanType::Transfer) {
      $planTransfer = $activePlan->propertyOwner?->incomingTransfer;
  }

  // The "current vs all" switch is only worth showing when there is more than
  // one plan to switch between.
  $canSwitchView = $allSnapshots->count() > 1 || ($viewer && $viewer->can('payment_history_switch'));
  $currentViewLabel = $isMemberViewer ? 'My Latest Plan' : 'Latest / Current Ownership';
  $allViewLabel = $isMemberViewer ? 'All My Plans (History)' : 'All Payments (History)';
  $allHistoryLabel = $isMemberViewer ? 'All My Payments' : 'All Owners of This '.$entityLabel;

  $currency = $activePlan?->price_unit
      ?? \App\Models\PropertyReservedPaymentPlan::query()
          ->whereMorphedTo('propertyOwnerable', $property)
          ->latest('id')->value('price_unit')
      ?? 'PKR';

  if ($activePlan) {
      $ledgerService = app(\App\Support\PaymentLedgerService::class);
      $ledgerService->refreshStatuses($activePlan);

      $ledgerRows = $activePlan->ledgerRows()->with('activeAllocations')->orderBy('sequence_order')->get();
      if ($ledgerRows->isEmpty()) {
          try { $ledgerService->createLedgerFromSnapshot($activePlan); } catch (\Throwable $e) {}
          $ledgerService->refreshStatuses($activePlan);
      }
      $ledgerRows = $activePlan->ledgerRows()->with('activeAllocations')->orderBy('sequence_order')->get();
      $ledgerSummary = $ledgerService->summary($activePlan);

      $installmentCount = $ledgerRows->where('charge_type', 'installment')->count();
      $balloonCount = $ledgerRows->where('charge_type', 'balloon')->count();
      $totalInstallments = $installmentCount + $balloonCount;
      $perInstallment = (float) ($ledgerRows->where('charge_type', 'installment')->first()?->amount_due ?? 0);
      $perBalloon = (float) ($ledgerRows->where('charge_type', 'balloon')->first()?->amount_due ?? 0);
      $ledgerHasRows = $ledgerRows->isNotEmpty();
      $ledgerHasPayments = $activePlan->paymentTransactions()->exists();

  // Payment history for the active plan - one row per recorded transaction, with
  // its own receipt. Loaded here so the history table and the receipt buttons
  // share a single query.
  $paymentHistory = $activePlan->paymentTransactions()
      ->with(['allocations.ledgerRow', 'recordedBy'])
      ->orderByDesc('paid_at')
      ->orderByDesc('id')
      ->get();

  // The modal opens with the first page already rendered by Blade; the data
  // endpoint then pages and filters it in place.
  $historyTransactions = new \Illuminate\Pagination\LengthAwarePaginator(
      $paymentHistory->forPage(1, 25)->values(),
      $paymentHistory->count(),
      25,
      1,
      [
          'path' => route('ownership_payment_plans.payments.history', $activePlan),
          'query' => request()->query(),
      ]
  );

  $taxRate = (float) ($ledgerSummary['tax_rate_percent'] ?? 0);

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
          'reversed' => '<span class="badge bg-label-dark"><i class="ti tabler-arrow-back-up me-1"></i>Reversed</span>',
          default => '<span class="badge bg-label-secondary"><i class="ti tabler-clock me-1"></i>Pending</span>',
      };
  };
@endphp


<div class="planner">
  @if(! $activePlan)
    @php $sellerPlan = $property->paymentPlan; @endphp
    <div class="card border-0 shadow-sm rounded-3 p-4">
      <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted text-center">
        <i class="ti tabler-credit-card fs-2 opacity-50 mb-2"></i>
        <div class="fw-semibold text-dark mb-1">No ownership payments yet</div>
        <small>
          @if($isMemberViewer)
            You have no payment plan on this {{ strtolower($entityLabel) }} yet. The payments recorded
            against your own ownership will appear here.
          @elseif($sellerPlan)
            <span class="badge bg-label-success mb-2">Payment plan is set</span><br>
            This {{ strtolower($entityLabel) }} has not been reserved yet. Payments will appear here
            once an owner is reserved.
          @else
            <span class="badge bg-label-secondary mb-2">No payment plan set</span><br>
            Apply a payment plan in the Payments Planning tab first, then reserve the
            {{ strtolower($entityLabel) }} for an owner. Payments will appear here after that.
          @endif
        </small>
      </div>
    </div>
  @else
    @if(! $currentOwnership)
      <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4">
        <i class="ti tabler-history"></i>
        <span class="small">
          <b>No active ownership</b> — showing the payments recorded for the most recent ownership
          ({{ $planOwner->first_name ?? '' }} {{ $planOwner->last_name ?? '' }}).
          This view is read-only.
        </span>
      </div>
    @elseif($isMemberViewer && ! $viewerHoldsProperty)
      <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
        <i class="ti tabler-user-exclamation"></i>
        <span class="small">
          <b>This property is no longer yours</b> — it now belongs to
          {{ $currentOwnerName ?: 'another member' }}. You are seeing the payment plan and payments
          recorded against your own ownership
          ({{ $planOwner->first_name ?? '' }} {{ $planOwner->last_name ?? '' }}).
          This view is read-only.
        </span>
      </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-2">
          <i class="ti tabler-wallet text-primary fs-5"></i>
          <h5 class="fw-bold mb-0">Payments</h5>
          @if($planStatus === 'completed')
            <span class="badge bg-label-success d-inline-flex align-items-center gap-1"
                  title="Every charge for this ownership has been settled">
              <i class="ti tabler-circle-check-filled"></i> Fully Paid
            </span>
          @elseif(in_array($planStatus, ['reversed', 'cancelled']))
            <span class="badge bg-label-dark text-capitalize">
              <i class="ti tabler-arrow-back-up me-1"></i>{{ $planStatus }}
            </span>
          @endif
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
          @if($canSwitchView)
            <select class="form-select form-select-sm payment-view-switch" style="width:auto;"
                    aria-label="Payment view">
              <option value="current" selected>{{ $currentViewLabel }}</option>
              <option value="all">{{ $allViewLabel }}</option>
            </select>
          @endif

          @if($canCollect)
            @can('reschedule_payments')
              <button type="button" class="btn btn-sm btn-outline-primary"
                      data-bs-toggle="modal" data-bs-target="#reschedulePaymentsModal"
                      @if($ledgerHasPayments)
                        disabled title="Payments are already recorded — the schedule cannot be rescheduled"
                      @else
                        title="Generate all charges and due dates for this plan"
                @endif>
                <i class="ti tabler-calendar-plus me-1"></i>Reschedule Payments
              </button>
            @endcan
          @endif

          @if($planTransfer)

            @can('property_transfer_print')
              <a href="{{ route('property_transfers.print', ['transfer' => $planTransfer, 'print' => 1]) }}"
                 onclick="event.preventDefault(); printDocument(this.href)"
                 class="btn btn-sm btn-outline-primary"
                 title="Print the transfer slip for this ownership">
                <i class="ti tabler-printer me-1"></i> Print Transfer Slip
              </a>
            @endcan
          @endif
          {{--          @if($ledgerHasRows)--}}
          {{--            @can('payment_print_by_period')--}}
          {{--              <button type="button" class="btn btn-sm btn-outline-primary"--}}
          {{--                      data-bs-toggle="modal" data-bs-target="#printPaymentsModal">--}}
          {{--                <i class="ti tabler-printer me-1"></i> Print Payments--}}
          {{--              </button>--}}
          {{--            @endcan--}}
          {{--          @endif--}}
          @can('payment_history')
            <button type="button" class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal" data-bs-target="#paymentHistoryModal"
                    title="Every payment recorded against this plan, with a downloadable receipt">
              <i class="ti tabler-history me-1"></i> Payment History
              @if($ledgerHasPayments)
                <span class="badge bg-label-primary ms-1">{{ $paymentHistory->count() }}</span>
              @endif
            </button>
          @endcan

          @if($canCollect)
            @can('payment_collect')
              <button type="button" class="btn btn-sm btn-primary"
                      data-bs-toggle="modal" data-bs-target="#paymentModal"
                      onclick="prefillPaymentForm(null)">
                <i class="ti tabler-plus me-1"></i> Record Payment
              </button>
            @endcan
          @endif
        </div>

      </div>


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
              <small class="micro-label text-muted text-uppercase d-block mb-1">Paid %</small>
              <div class="fw-semibold" id="summary-paid-percent">{{ $ledgerSummary['paid_percent'] }}%</div>
            </div>
          </div>
          @if($taxRate > 0)
            <div class="col-6 col-xl-2">
              <div class="border rounded-3 p-3 h-100 bg-label-primary"
                   title="FBR tax collected on top of the charges, at the rate frozen when this plan was first paid">
                <small class="micro-label text-muted text-uppercase d-block mb-1">
                  FBR Tax ({{ rtrim(rtrim(number_format($taxRate, 2, '.', ''), '0'), '.') }}%)
                </small>
                <div class="fw-semibold" id="summary-tax-collected">
                  {{ number_format($ledgerSummary['tax_collected'], 2) }} {{ $currency }}
                </div>
                <small class="text-muted">of {{ number_format($ledgerSummary['tax_total_due'], 2) }}</small>
              </div>
            </div>
            <div class="col-6 col-xl-2">
              <div class="border rounded-3 p-3 h-100 bg-label-success"
                   title="Cash actually received: charges paid plus FBR tax and late fees collected">
                <small class="micro-label text-uppercase d-block mb-1 text-success">Total Received</small>
                <div class="fw-semibold text-success" id="summary-total-received">
                  {{ number_format($ledgerSummary['total_received'], 2) }} {{ $currency }}
                </div>
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
            'showActions' => $canAct,
            'tableId' => 'ledger-current',
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
            $isActivePlan = $histPlan->propertyOwner?->isCurrent() ?? false;
            $histStart = $histPlan->propertyOwner?->started_at?->format('d M Y');
            $histEnd = $histPlan->propertyOwner?->ended_at?->format('d M Y');
            $histIsTransfer = $histPlan->plan_type === \App\Enums\PlanType::Transfer;
            $histTransfer = $histIsTransfer ? $histPlan->propertyOwner?->incomingTransfer : null;
            $histCollapseId = 'ownership-history-'.$histPlan->id;
          @endphp
          <div class="border rounded-3 overflow-hidden mb-3">
            <div
              class="px-3 py-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-between flex-wrap gap-2">
              <span class="fw-semibold small text-primary">
                <i
                  class="ti tabler-user me-1"></i>{{ $histOwner->first_name ?? 'Owner' }} {{ $histOwner->last_name ?? '' }}
                @if($histIsTransfer)
                  <span class="badge bg-label-info ms-1">Transfer Fee Plan</span>
                  @if($histTransfer)
                    @can('property_transfer_print')
                      <a href="{{ route('property_transfers.print', ['transfer' => $histTransfer, 'print' => 1]) }}"
                         onclick="event.preventDefault(); printDocument(this.href)"
                         class="btn btn-sm btn-link p-0 ms-1 align-baseline"
                         title="Print the transfer slip"><i class="ti tabler-printer"></i></a>
                    @endcan
                  @endif
                @endif
                @if($isActivePlan)
                  <span class="badge bg-success ms-1">Current</span>
                @endif
              </span>
              <div class="d-flex align-items-center gap-3">
                <span class="small text-muted">{{ $histStart ?? '—' }} → {{ $histEnd ?? 'ongoing' }}
                  · Paid {{ number_format($histPaid, 2) }} {{ $histCurrency }}</span>
                <button type="button" class="btn btn-sm btn-icon btn-text-primary ownership-history-toggle"
                        data-bs-toggle="collapse" data-bs-target="#{{ $histCollapseId }}"
                        aria-expanded="true" aria-controls="{{ $histCollapseId }}"
                        title="Show / hide payment details">
                  <i class="ti tabler-chevron-up"></i>
                </button>
              </div>
            </div>
            <div class="collapse show" id="{{ $histCollapseId }}">
              <div class="p-2">
                @include('components.property.ledger-table', [
                    'ledgerRows' => $histRows,
                    'currency' => $histCurrency,
                    'showActions' => $isActivePlan && $canAct,
                    'tableId' => 'ledger-hist-'.$histPlan->id,
                ])
              </div>
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
    @if($canCollect)
      @can('payment_collect')
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <form action="{{ route('payment.store', $activePlan) }}" method="POST">
                @csrf
                <div class="modal-header">
                  <h5 class="modal-title fw-bold">Record Payment</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div class="mb-3" id="payModeBlock">
                    <label class="form-label fw-semibold required">Apply Payment To</label>
                    <div class="btn-group w-100" role="group" aria-label="Payment application mode">
                      <input type="radio" class="btn-check" name="payment_mode" id="payModeAmount" value="amount"
                             checked>
                      <label class="btn btn-outline-primary" for="payModeAmount">
                        <i class="ti tabler-cash me-1"></i>By Amount
                      </label>
                      <input type="radio" class="btn-check" name="payment_mode" id="payModeCharges" value="charges">
                      <label class="btn btn-outline-primary" for="payModeCharges">
                        <i class="ti tabler-list-check me-1"></i>By Charges
                      </label>
                    </div>
                    <small class="form-text text-muted" id="payModeHelp"></small>
                  </div>

                  <div class="mb-3 d-none" id="payChargesBlock">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                      <label class="form-label fw-semibold mb-0" for="payLedgerIds">Select Charges
                        <span class="text-danger">*</span></label>
                      <button type="button" class="btn btn-sm btn-link p-0" id="paySelectAllBtn">Select all</button>
                    </div>
                    {{-- A payment opened from a charge row pays that one charge only.
                         The select below is disabled in that case, and disabled fields
                         are not submitted, so this hidden input carries the id instead. --}}
                    <input type="hidden" name="ledger_ids[]" id="payLedgerIdsLocked" value="" disabled>
                    <div class="select2-primary">
                      <select class="select2 form-select" id="payLedgerIds" name="ledger_ids[]" multiple>
                        @foreach($ledgerRows as $row)
                          @if($row->remaining_balance > 0)
                            <option value="{{ $row->id }}" data-amount="{{ $row->remaining_balance }}">
                              #{{ $loop->iteration }} · {{ $row->display_name }} — Balance
                              {{ number_format($row->remaining_balance, 2) }} {{ $currency }}
                            </option>
                          @endif
                        @endforeach
                      </select>
                    </div>
                    <small class="form-text text-muted">
                      Selected
                      <span id="paySelectedCount" class="fw-semibold">0</span> charge(s) · total
                      <span id="paySelectedTotal" class="fw-semibold">0.00</span> {{ $currency }} — the amount below is
                      pre-filled with this total, and you can still pay less.
                    </small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold required" for="payAmount">Amount</label>
                    <div class="input-group">
                      <span class="input-group-text">{{ $currency }}</span>
                      <input type="number" step="0.01" min="0.01" name="amount" id="payAmount"
                             class="form-control" required placeholder="0.00">
                    </div>
                    @if($taxRate > 0)
                      <small class="form-text text-muted" id="payTaxHint">
                        FBR tax is added on top of this amount at
                        <span
                          class="fw-semibold">{{ rtrim(rtrim(number_format($taxRate, 2, '.', ''), '0'), '.') }}%</span>.
                      </small>
                    @else
                      <small class="form-text text-muted">
                        No FBR rate is configured for this buyer, so no tax is added on top.
                      </small>
                    @endif
                  </div>
                  <div class="mb-3 d-none" id="lateFeePreview">
                    <div class="border rounded p-2 bg-light">
                      <div class="d-flex justify-content-between small">
                        <span>Charges</span>
                        <span id="previewCharges">0.00</span>
                      </div>
                      <div id="previewLateFees"></div>
                      <div class="d-flex justify-content-between small">
                        <span>FBR Tax</span>
                        <span id="previewTax">0.00</span>
                      </div>
                      <div class="d-flex justify-content-between fw-semibold border-top mt-1 pt-1">
                        <span>Payment Total</span>
                        <span id="previewTotal">0.00</span>
                      </div>
                      <div class="small text-danger mt-1 d-none" id="previewError"></div>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Payment Method </label>
                    <select class="form-select" name="payment_method" id="payMethod" required>
                      <option value="cash">Cash</option>
                      <option value="bank_transfer">Bank Transfer</option>
                      <option value="cheque">Cheque</option>
                      <option value="card">Card</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Payment Date</label>
                    <input type="date" name="paid_at" class="form-control" value="{{ now()->toDateString() }}">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold ">Payment Reference <span class="text-danger"
                                                                                   id="payRefAsterisk">*</span></label>
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
                  <button type="submit" name="print_receipt" value="0" class="btn btn-outline-primary">
                    <i class="ti tabler-device-floppy me-1"></i> Save
                  </button>
                  <button type="submit" name="print_receipt" value="1" class="btn btn-primary">
                    <i class="ti tabler-printer me-1"></i> Save &amp; Print
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      @endcan
    @endif

    @if($canCollect)
      @can('payment_collect')
        <div class="modal fade" id="reschedulePaymentsModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">

              <form action="{{ route('payments.reschedule', $activePlan) }}" method="POST">
                @csrf
                <div class="modal-header">
                  <h5 class="modal-title fw-bold"><i class="ti tabler-calendar-plus me-1 text-primary"></i> Reschedule
                    Payments</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="rescheduleAt">Start / Due Date <span
                        class="text-danger">*</span></label>
                    <input type="date" name="reschedule_at" id="rescheduleAt" class="form-control"
                           value="{{ $activePlan->propertyOwner?->started_at?->toDateString() ?? now()->toDateString() }}">

                  </div>
                  @if($ledgerHasPayments)
                    <p class="mb-0 text-muted">Payments are already recorded for this plan, so the charge schedule
                      cannot
                      be rescheduled.</p>
                  @elseif($ledgerHasRows)
                    <p class="mb-0 text-muted">Charges already exist with no payments recorded. Re-scheduling will
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
                      Reschedule
                    </button>
                  @endif
                </div>
              </form>
            </div>
          </div>
        </div>
      @endcan
    @endif

    {{-- Payment History: every transaction for this plan, with a downloadable receipt --}}
    <div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title fw-bold">
                <i class="ti tabler-history me-1 text-primary"></i> Payment History
              </h5>
              <div class="text-muted small">
                {{ $entityLabel }}: <span class="fw-semibold">{{ $property->name }}</span>
                @if($property->property_no ?? $property->unit_no ?? null)
                  <span class="mx-1">&middot;</span>{{ $property->property_no ?? $property->unit_no }}
                @endif
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
              <div style="width: 215px;">
                <label class="form-label form-label-sm fw-semibold mb-1" for="historyScope">Show Payments</label>
                <select class="form-select form-select-sm" id="historyScope">
                  <option value="current" selected>Current Ownership</option>
                  <option value="all">{{ $allHistoryLabel }}</option>
                </select>
              </div>
              <div class="flex-grow-1" style="min-width: 180px;">
                <label class="form-label form-label-sm fw-semibold mb-1" for="historySearch">Search</label>
                <input type="search" class="form-control form-control-sm" id="historySearch"
                       placeholder="Reference or charge name">
              </div>
              <div style="width: 150px;">
                <label class="form-label form-label-sm fw-semibold mb-1" for="historyMethod">Method</label>
                <select class="form-select form-select-sm" id="historyMethod">
                  <option value="">All methods</option>
                  <option value="cash">Cash</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="cheque">Cheque</option>
                  <option value="card">Card</option>
                  <option value="mobile_wallet">Mobile Wallet</option>
                  <option value="adjustment">Adjustment</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div style="width: 160px;">
                <label class="form-label form-label-sm fw-semibold mb-1" for="historyFrom">From</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white border-end-0"><i
                      class="ti tabler-calendar text-muted"></i></span>
                  <input type="text" class="form-control border-start-0" id="historyFrom" placeholder="Select date"
                         autocomplete="off">
                </div>
              </div>
              <div style="width: 160px;">
                <label class="form-label form-label-sm fw-semibold mb-1" for="historyTo">To</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white border-end-0"><i
                      class="ti tabler-calendar text-muted"></i></span>
                  <input type="text" class="form-control border-start-0" id="historyTo" placeholder="Select date"
                         autocomplete="off">
                </div>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-primary" id="historyApplyBtn">
                  <i class="ti tabler-filter me-1"></i> Apply
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="historyResetBtn">
                  <i class="ti tabler-refresh me-1"></i> Reset
                </button>
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6 col-md-3">
                <div class="border rounded-3 p-2 bg-label-secondary h-100">
                  <small class="micro-label text-muted text-uppercase d-block mb-1">Transactions</small>
                  <div class="fw-semibold" id="historyCount">0</div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="border rounded-3 p-2 bg-label-primary h-100">
                  <small class="micro-label text-muted text-uppercase d-block mb-1">Charges Paid</small>
                  <div class="fw-semibold" id="historyCharges">0.00</div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="border rounded-3 p-2 bg-label-warning h-100">
                  <small class="micro-label text-muted text-uppercase d-block mb-1">FBR Tax</small>
                  <div class="fw-semibold" id="historyTax">0.00</div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="border rounded-3 p-2 bg-label-success h-100">
                  <small class="micro-label text-muted text-uppercase d-block mb-1">Total Received</small>
                  <div class="fw-semibold text-success" id="historyTotal">0.00</div>
                </div>
              </div>
            </div>

            {{-- Plain Bootstrap table (no table-responsive): long text wraps via text-break,
                 numbers and headers stay on one line, so nothing scrolls horizontally. --}}
            <style>
              .receipt-btn {
                width: 2.25rem;
                height: 2.25rem;
              }

              .receipt-btn i {
                font-size: 1.25rem;
                line-height: 1;
              }

              /* Keep a long list of charges to one truncated line; the full
                 list is revealed by the title tooltip on hover. */
              .history-charges {
                max-width: 230px;
              }

              /* Chevron flips when the ownership details are collapsed. */
              .ownership-history-toggle i {
                transition: transform .2s ease;
              }

              .ownership-history-toggle[aria-expanded="false"] i {
                transform: rotate(180deg);
              }
            </style>
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
              <tr class="text-nowrap">
                <th>Date</th>
                <th>Charges Settled</th>
                <th class="text-end">Charges</th>
                <th class="text-end">Tax</th>
                <th class="text-end">Late Fee</th>
                <th class="text-end">Total</th>
                <th class="text-center">Status</th>
                <th class="text-center">Receipt</th>
              </tr>
              </thead>
              <tbody id="historyTableBody">
              <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                  <span class="spinner-border spinner-border-sm me-2"></span>Loading transactions…
                </td>
              </tr>
              </tbody>
            </table>

            {{-- The pagination of the app, rendered like the properties page. --}}
            <div class="mt-3" id="historyPagination">
              {!! $historyTransactions->withQueryString()->links('pagination::bootstrap-5') !!}
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    @if($ledgerHasRows)
      @can('payment_print_by_period')
        <div class="modal fade" id="printPaymentsModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-md">
            <div class="modal-content">
              {{-- Printing is handled in JS (hidden iframe), so this form is the
                   no-JS fallback rather than something that opens a new tab. --}}
              <form action="{{ route('ownership_payment_plans.payments.report', $activePlan) }}" method="GET"
                    id="reportPrintForm">
                <div class="modal-header">
                  <h5 class="modal-title fw-bold"><i class="ti tabler-printer me-1 text-primary"></i> Print Payments
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="reportPreset">Period</label>
                    <select class="form-select" name="preset" id="reportPreset">
                      <option value="day">Today</option>
                      <option value="week">Last 7 days</option>
                      <option value="fifteen">Last 15 days</option>
                      <option value="month" selected>Last 30 days</option>
                      <option value="custom">Custom range</option>
                    </select>
                  </div>
                  <div class="mb-3 d-none" id="reportCustomRange">
                    <label class="form-label fw-semibold" for="reportFrom">From</label>
                    <input type="date" name="from" id="reportFrom" class="form-control mb-2"
                           value="{{ now()->startOfMonth()->toDateString() }}">
                    <label class="form-label fw-semibold" for="reportTo">To</label>
                    <input type="date" name="to" id="reportTo" class="form-control"
                           value="{{ now()->toDateString() }}">
                  </div>
                  <p class="mb-0 text-muted small">
                    Lists the payments received in this period and the charge status as at the end of it.
                  </p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">
                    <i class="ti tabler-printer me-1"></i> Print
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      @endcan
    @endif

    @if($canAct)
      @can('payment_collect')
        <div class="modal fade" id="unpayChargesModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="ti tabler-rotate-clockwise-2 me-1 text-warning"></i>
                  Unpay Charges</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p class="mb-2 text-muted">
                  These charges go back to unpaid and their balance is restored. If they were settled by a bulk
                  payment, only the selected charges are un-paid &mdash; the rest of that payment stays settled.
                </p>
                <ul class="list-group list-group-flush mb-3 overflow-y-auto" id="unpayChargeList"
                    style="max-height: 300px"></ul>
                <div class="d-flex justify-content-between border-top pt-2">
                  <span class="fw-semibold">Total to un-pay</span>
                  <span class="fw-semibold" id="unpayChargeTotal">0.00</span>
                </div>
                <div class="d-flex justify-content-between pt-1">
                  <span class="text-muted small">FBR tax included (refunded too)</span>
                  <span class="text-muted small" id="unpayChargeTax">0.00</span>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmUnpayChargesBtn">
                  <i class="ti tabler-reload me-1"></i> Confirm Unpay
                </button>
              </div>
            </div>
          </div>
        </div>
      @endcan
    @endif

    @push('styles')
      <style>
        /* Selected charges stay on the primary tint even when the select is
           locked (disabled) because the modal was opened from one charge row. */
        #paymentModal .select2-container .select2-selection--multiple .select2-selection__choice {
          background-color: color-mix(in sRGB, var(--bs-paper-bg) var(--bs-bg-label-tint-amount), var(--bs-primary));
          border-color: color-mix(in sRGB, var(--bs-paper-bg) var(--bs-bg-label-tint-amount), var(--bs-primary));
          color: var(--bs-primary);
        }
        #paymentModal .select2-container .select2-selection--multiple .select2-selection__choice__remove,
        #paymentModal .select2-container .select2-selection--multiple .select2-selection__choice__remove:hover {
          color: var(--bs-primary);
        }
      </style>
    @endpush

    @push('scripts')
      <script>
        var PAYMENT_CURRENCY = '{{ $currency }}';
        var PAYMENT_TOTAL_OUTSTANDING = {{ number_format((float) ($ledgerSummary['remaining'] ?? 0), 2, '.', '') }};
        var REVERSE_CHARGES_URL = '{{ route('ownership_payment_plans.payments.reverse_charges', $activePlan) }}';
        var REPORT_URL = '{{ route('ownership_payment_plans.payments.report', $activePlan) }}';
        var PAYMENT_HISTORY_URL = '{{ route('ownership_payment_plans.payments.history', $activePlan) }}';
        var LATE_FEE_PREVIEW_URL = '{{ route('payments.late_fee_preview', $activePlan) }}';
        var historyState = { page: 1 };
        var unpaySelection = { tableId: null, charges: [] };
        var pendingPrefill = { ledgerId: null };

        function payModeEl() {
          var charges = document.getElementById('payModeCharges');
          return charges && charges.checked ? 'charges' : 'amount';
        }

        function selectedChargeValues() {
          var el = document.getElementById('payLedgerIds');
          if (!el) return [];
          return Array.prototype.slice.call(el.options)
            .filter(function(o) {
              return o.selected;
            })
            .map(function(o) {
              return o.value;
            });
        }

        function selectedChargeTotal() {
          var el = document.getElementById('payLedgerIds');
          if (!el) return 0;
          return Array.prototype.slice.call(el.options)
            .filter(function(o) {
              return o.selected;
            })
            .reduce(function(sum, o) {
              return sum + (parseFloat(o.getAttribute('data-amount')) || 0);
            }, 0);
        }

        function setChargeSelection(ids) {
          var el = document.getElementById('payLedgerIds');
          if (!el) return;
          if (window.jQuery) {
            jQuery(el).val(ids).trigger('change');
          } else {
            Array.prototype.forEach.call(el.options, function(o) {
              o.selected = ids.indexOf(o.value) !== -1;
            });
          }
        }

        function onChargesChanged() {
          var el = document.getElementById('payLedgerIds');
          var picked = selectedChargeValues();
          var total = el ? el.options.length : 0;

          var countEl = document.getElementById('paySelectedCount');
          if (countEl) countEl.textContent = picked.length;
          var totalEl = document.getElementById('paySelectedTotal');
          if (totalEl) totalEl.textContent = formatMoney(selectedChargeTotal());

          var btn = document.getElementById('paySelectAllBtn');
          if (btn) btn.textContent = (total > 0 && picked.length === total) ? 'Clear all' : 'Select all';

          // Picking or dropping a charge has to be reflected in the amount
          // field straight away, so the figure being paid is never stale.
          if (payModeEl() === 'charges') {
            var amountEl = document.getElementById('payAmount');
            if (amountEl) amountEl.value = selectedChargeTotal().toFixed(2);
          }
        }

        function applyPaymentMode() {
          var byCharges = payModeEl() === 'charges';
          var block = document.getElementById('payChargesBlock');
          if (block) block.classList.toggle('d-none', !byCharges);

          var help = document.getElementById('payModeHelp');
          if (help) {
            help.textContent = byCharges
              ? 'The amount is applied to the selected charges only, oldest first.'
              : 'The amount is spread across every outstanding charge, oldest first.';
          }

          var amountEl = document.getElementById('payAmount');
          if (amountEl) {
            amountEl.value = (byCharges ? selectedChargeTotal() : PAYMENT_TOTAL_OUTSTANDING).toFixed(2);
          }

          onChargesChanged();
        }

        function applyPrefill() {
          var ledgerId = pendingPrefill.ledgerId;
          var locked = !!ledgerId;

          // Opened from a charge row: this payment is against that charge only,
          // so the By Amount / By Charges tabs are hidden, the mode is forced to
          // charges and the charge select is locked to the one relevant charge.
          var modeBlock = document.getElementById('payModeBlock');
          if (modeBlock) modeBlock.classList.toggle('d-none', locked);

          var selectAllBtn = document.getElementById('paySelectAllBtn');
          if (selectAllBtn) selectAllBtn.classList.toggle('d-none', locked);

          var selectEl = document.getElementById('payLedgerIds');
          if (selectEl) selectEl.disabled = locked;

          var lockedEl = document.getElementById('payLedgerIdsLocked');
          if (lockedEl) {
            lockedEl.disabled = !locked;
            lockedEl.value = locked ? String(ledgerId) : '';
          }

          var radio = document.getElementById(locked ? 'payModeCharges' : 'payModeAmount');
          if (radio) radio.checked = true;

          setChargeSelection(locked ? [String(ledgerId)] : []);

          // The amount starts at the charge's own balance and can be lowered,
          // but never raised above it.
          var amountEl = document.getElementById('payAmount');
          if (amountEl) {
            var cap = selectedChargeTotal();
            if (locked && cap > 0) {
              amountEl.setAttribute('max', moneyRaw(cap));
            } else {
              amountEl.removeAttribute('max');
            }
          }

          var refEl = document.getElementById('payReference');
          if (refEl) refEl.value = '';
          syncRefRequirement();
          applyPaymentMode();
        }

        function prefillPaymentForm(ledgerId) {
          pendingPrefill.ledgerId = ledgerId || null;
          applyPrefill();
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

        function formatMoney(value) {
          var amount = Number(value);
          if (isNaN(amount)) amount = 0;
          return amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        var lateFeePreviewTimer = null;
        var lateFeePreviewSeq = 0;

        function scheduleLateFeePreview() {
          clearTimeout(lateFeePreviewTimer);
          lateFeePreviewTimer = setTimeout(refreshLateFeePreview, 300);
        }

        function refreshLateFeePreview() {
          var box = document.getElementById('lateFeePreview');
          var amountEl = document.getElementById('payAmount');
          if (!box || !amountEl) return;

          var amount = parseFloat(amountEl.value);
          if (!(amount > 0)) {
            box.classList.add('d-none');
            return;
          }

          var mode = payModeEl();
          var ids = selectedChargeValues();

          if (mode === 'charges' && ids.length === 0) {
            box.classList.add('d-none');
            return;
          }

          var params = new URLSearchParams();
          params.set('payment_mode', mode);
          params.set('amount', amount.toFixed(2));

          var paidEl = document.querySelector('#paymentModal input[name="paid_at"]');
          if (paidEl && paidEl.value) params.set('paid_at', paidEl.value);

          if (mode === 'charges') {
            ids.forEach(function(id) {
              params.append('ledger_ids[]', id);
            });
          }

          var seq = ++lateFeePreviewSeq;

          fetch(LATE_FEE_PREVIEW_URL + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
          }).then(function(response) {
            return response.json().then(function(data) {
              return { ok: response.ok, data: data };
            });
          }).then(function(result) {
            if (seq !== lateFeePreviewSeq) return;

            var data = result.data || {};
            if (!result.ok || !data.ok) {
              box.classList.add('d-none');
              return;
            }

            box.classList.remove('d-none');

            var chargesEl = document.getElementById('previewCharges');
            var taxEl = document.getElementById('previewTax');
            var totalEl = document.getElementById('previewTotal');
            var feesEl = document.getElementById('previewLateFees');
            var errorEl = document.getElementById('previewError');

            if (chargesEl) chargesEl.textContent = formatMoney(data.charge_subtotal) + ' ' + PAYMENT_CURRENCY;
            if (taxEl) taxEl.textContent = formatMoney(data.tax_amount) + ' ' + PAYMENT_CURRENCY;
            if (totalEl) totalEl.textContent = formatMoney(data.amount_paid) + ' ' + PAYMENT_CURRENCY;

            if (feesEl) {
              feesEl.innerHTML = '';
              (data.late_fees || []).forEach(function(fee) {
                var row = document.createElement('div');
                row.className = 'd-flex justify-content-between small text-warning';
                var label = document.createElement('span');
                label.textContent = 'Late fee · ' + (fee.display_name || 'Charge') + ' (' + fee.days_late + ' day' + (fee.days_late === 1 ? '' : 's') + ' late)';
                var value = document.createElement('span');
                value.textContent = formatMoney(fee.fee_amount) + ' ' + PAYMENT_CURRENCY;
                row.appendChild(label);
                row.appendChild(value);
                feesEl.appendChild(row);
              });
            }

            if (errorEl) {
              errorEl.classList.add('d-none');
              errorEl.textContent = '';
            }
          }).catch(function() {
            if (seq === lateFeePreviewSeq) box.classList.add('d-none');
          });
        }

        function buildStatusBadge(status) {
          var map = {
            paid: '<span class="badge bg-label-success"><i class="ti tabler-check me-1"></i>Paid</span>',
            current_due: '<span class="badge bg-label-warning"><i class="ti tabler-clock me-1"></i>Current Due</span>',
            late: '<span class="badge bg-label-danger"><i class="ti tabler-alert-triangle me-1"></i>Late</span>',
            reversed: '<span class="badge bg-label-dark"><i class="ti tabler-arrow-back-up me-1"></i>Reversed</span>',
            pending: '<span class="badge bg-label-secondary"><i class="ti tabler-clock me-1"></i>Pending</span>'
          };
          return map[status] || map.pending;
        }

        function payButtonHtml(id, amount) {
          var pay = formatMoney(amount) + ' ' + PAYMENT_CURRENCY;
          return '<button type="button" class="btn btn-sm btn-icon btn-outline-success" data-bs-toggle="modal" data-bs-target="#paymentModal" title="Pay ' + pay + '" onclick="prefillPaymentForm(' + id + ')">' +
            '<i class="ti tabler-cash"></i></button>';
        }

        function moneyRaw(value) {
          var amount = Number(value);
          if (isNaN(amount)) amount = 0;
          return amount.toFixed(2);
        }

        function unpayButtonHtml(tableId, uuid, amount) {
          return '<button type="button" class="btn btn-sm btn-icon btn-outline-warning"' +
            ' title="Unpay ' + formatMoney(amount) + ' against this charge"' +
            ' onclick="openUnpayCharges(\'' + tableId + '\', [\'' + uuid + '\'])">' +
            '<i class="ti tabler-rotate-clockwise-2"></i></button>';
        }

        function updateLedgerRow(row) {
          var tr = document.getElementById('ledger-row-' + row.uuid);
          if (!tr) return;
          tr.querySelector('.row-amount-paid').textContent = formatMoney(row.amount_paid);
          tr.querySelector('.row-balance').textContent = formatMoney(row.remaining_balance);
          tr.querySelector('.row-status').innerHTML = buildStatusBadge(row.payment_status);

          // A reopened charge has nothing left to un-pay, so retire its checkbox.
          var check = tr.querySelector('[data-charge-check]');
          if (check) {
            check.checked = false;
            check.disabled = !(row.amount_paid > 0);
            check.setAttribute('data-amount', moneyRaw(row.amount_paid));
          }

          var actions = tr.querySelector('.row-actions');
          if (actions) {
            var tableId = check ? check.getAttribute('data-charge-check') : null;
            if (row.remaining_balance > 0) {
              actions.innerHTML = payButtonHtml(row.id, row.remaining_balance);
            } else if (tableId) {
              actions.innerHTML = unpayButtonHtml(tableId, row.uuid, row.amount_paid);
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

        function chargeChecks(tableId) {
          return Array.prototype.slice.call(
            document.querySelectorAll('[data-charge-check="' + tableId + '"]')
          );
        }

        function selectedCharges(tableId) {
          return chargeChecks(tableId).filter(function(el) {
            return el.checked;
          });
        }

        function syncSelectAll(tableId) {
          var master = document.querySelector('[data-select-all="' + tableId + '"]');
          if (!master) return;
          var checks = chargeChecks(tableId);
          var picked = checks.filter(function(el) {
            return el.checked;
          });
          master.checked = checks.length > 0 && picked.length === checks.length;
          master.indeterminate = picked.length > 0 && picked.length < checks.length;
        }

        function syncUnpayBar(tableId) {
          var bar = document.querySelector('[data-unpay-bar="' + tableId + '"]');
          if (!bar) return;

          var picked = selectedCharges(tableId);
          var total = picked.reduce(function(sum, el) {
            return sum + (parseFloat(el.getAttribute('data-amount')) || 0);
          }, 0);

          var countEl = bar.querySelector('[data-selected-count]');
          var totalEl = bar.querySelector('[data-selected-total]');
          if (countEl) countEl.textContent = picked.length;
          if (totalEl) totalEl.textContent = formatMoney(total);

          bar.classList.toggle('d-none', picked.length === 0);
          bar.classList.toggle('d-flex', picked.length > 0);
          syncSelectAll(tableId);
        }

        function clearChargeSelection(tableId) {
          if (!tableId) return;
          chargeChecks(tableId).forEach(function(el) {
            el.checked = false;
          });
          syncUnpayBar(tableId);
        }

        function selectCharge(tableId, uuid) {
          chargeChecks(tableId).forEach(function(el) {
            if (el.getAttribute('data-uuid') === uuid) el.checked = true;
          });
          syncUnpayBar(tableId);
        }

        function openUnpayCharges(tableId, uuids) {
          // The row button funnels through here, so the dialog always lists
          // exactly the charges that are about to be un-paid.
          (uuids || []).forEach(function(uuid) {
            selectCharge(tableId, uuid);
          });

          var picked = selectedCharges(tableId);

          if (!picked.length) {
            showToast('That charge has nothing left to un-pay.', 'error');
            return;
          }

          unpaySelection = {
            tableId: tableId,
            charges: picked.map(function(el) {
              return {
                uuid: el.getAttribute('data-uuid'),
                name: el.getAttribute('data-name'),
                amount: parseFloat(el.getAttribute('data-amount')) || 0,
                tax: parseFloat(el.getAttribute('data-tax')) || 0
              };
            })
          };

          var list = document.getElementById('unpayChargeList');
          if (list) {
            list.innerHTML = unpaySelection.charges.map(function(c) {
              return '<li class="list-group-item d-flex justify-content-between align-items-start px-0">' +
                '<div class="pe-2">' +
                  '<div class="text-truncate">' + escapeHtml(c.name) + '</div>' +
                  '<div class="text-muted text-tiny">' +
                    (c.tax > 0
                      ? 'Includes FBR tax ' + formatMoney(c.tax) + ' ' + PAYMENT_CURRENCY
                      : 'No tax included') +
                  '</div>' +
                '</div>' +
                '<span class="fw-semibold text-nowrap">' + formatMoney(c.amount) + '</span></li>';
            }).join('');
          }

          var totalEl = document.getElementById('unpayChargeTotal');
          if (totalEl) {
            totalEl.textContent = formatMoney(unpaySelection.charges.reduce(function(sum, c) {
              return sum + c.amount;
            }, 0)) + ' ' + PAYMENT_CURRENCY;
          }

          var taxEl = document.getElementById('unpayChargeTax');
          if (taxEl) {
            taxEl.textContent = formatMoney(unpaySelection.charges.reduce(function(sum, c) {
              return sum + c.tax;
            }, 0)) + ' ' + PAYMENT_CURRENCY;
          }

          var modalEl = document.getElementById('unpayChargesModal');
          if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
          }
        }

        function hideUnpayChargesModal() {
          var modalEl = document.getElementById('unpayChargesModal');
          if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          }
        }

        function submitUnpayCharges() {
          if (!unpaySelection.charges.length) return;

          var btn = document.getElementById('confirmUnpayChargesBtn');
          if (btn) btn.disabled = true;

          fetch(REVERSE_CHARGES_URL, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': '{{ csrf_token() }}',
              'Accept': 'application/json',
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              ledger_uuids: unpaySelection.charges.map(function(c) {
                return c.uuid;
              })
            })
          })
            .then(function(r) {
              return r.json().catch(function() {
                return { ok: false, message: 'Unexpected server response.' };
              });
            })
            .then(function(data) {
              hideUnpayChargesModal();

              if (data.ok) {
                (data.rows || []).forEach(updateLedgerRow);
                updateSummary(data.summary);
                clearChargeSelection(unpaySelection.tableId);
                unpaySelection = { tableId: null, charges: [] };
                showToast(data.message, 'success');

                // Fully un-paying a completed plan reopens it for editing; the
                // reserved-plan controls are server-rendered, so refresh once.
                if (data.reload) {
                  window.setTimeout(function() { window.location.reload(); }, 800);
                }
              } else {
                showToast(data.message || 'Could not un-pay these charges.', 'error');
              }
            })
            .catch(function() {
              hideUnpayChargesModal();
              showToast('Something went wrong while un-paying the charges.', 'error');
            })
            .then(function() {
              if (btn) btn.disabled = false;
            });
        }

        function showToast(message, type) {
          if (typeof Notyf !== 'undefined') {
            var nf = new Notyf({ duration: 4000, ripple: false });
            if (type === 'error') nf.error(message); else nf.success(message);
          } else {
            alert(message);
          }
        }

        // --- Printing -----------------------------------------------------------------
        // The shared helper prints from a hidden frame on every page, so printing
        // never opens a new tab and never asks for a second confirmation click.

        function printPaymentsDocument(url) {
          if (typeof window.printDocument === 'function') {
            window.printDocument(url);
            return;
          }

          if (url) window.open(url, '_blank');
        }

        function hidePrintPaymentsModal() {
          var modalEl = document.getElementById('printPaymentsModal');
          if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          }
        }

        function printPaymentsReport() {
          var form = document.getElementById('reportPrintForm');
          if (!form) return false;

          // Disabled fields (the from/to dates outside a custom range) are
          // dropped by FormData, which matches how the report reads them.
          var query = new URLSearchParams(new FormData(form)).toString();

          hidePrintPaymentsModal();
          printPaymentsDocument(REPORT_URL + (query ? '?' + query : ''));

          return true;
        }

        // --- Payment History modal -------------------------------------------------
        // Rows come from the server on every filter/page change so the totals
        // always describe the filtered set, not just the visible page.

        function historyFilters() {
          return {
            scope: document.getElementById('historyScope')?.value || 'current',
            q: (document.getElementById('historySearch')?.value || '').trim(),
            method: document.getElementById('historyMethod')?.value || '',
            from: document.getElementById('historyFrom')?.value || '',
            to: document.getElementById('historyTo')?.value || ''
          };
        }

        function escapeHtml(value) {
          if (value === null || value === undefined) return '';
          return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function statusBadge(status, label) {
          var value = status || 'paid';
          var color = value === 'returned' ? 'danger' : (value === 'pending' ? 'warning' : 'success');

          return '<span class="badge bg-label-' + color + ' text-capitalize">' +
            escapeHtml(label || value) + '</span>';
        }

        // Tooltips are created after every render because the rows are replaced.
        function initHistoryTooltips() {
          if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;

          document.querySelectorAll('#historyTableBody [data-bs-toggle="tooltip"]').forEach(function(element) {
            bootstrap.Tooltip.getOrCreateInstance(element);
          });
        }

        function renderHistoryRows(rows, scope) {
          var body = document.getElementById('historyTableBody');
          if (!body) return;

          var showOwner = scope === 'all';

          if (!rows.length) {
            body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">' +
              'No transactions match these filters.</td></tr>';
            return;
          }

          body.innerHTML = rows.map(function(txn) {
            var chargeNames = (txn.charges || []).filter(Boolean);
            var fullCharges = chargeNames.join(', ');
            var shortCharges = fullCharges.length > 42
              ? fullCharges.slice(0, 42).trim() + '…'
              : fullCharges;

            // Limited to one line here; the tooltip lists every settled charge.
            var chargesHtml = chargeNames.length
              ? '<span class="d-inline-flex align-items-center gap-1 cursor-pointer" data-bs-toggle="tooltip" ' +
              'data-bs-html="true" data-bs-placement="top" ' +
              'data-bs-title="' + chargeNames.map(function(name) {
                return escapeHtml(name);
              }).join('<br>') + '">' +
              '<span class="d-inline-block text-truncate history-charges align-middle">' + escapeHtml(shortCharges) + '</span>' +
              '<span class="badge bg-label-secondary rounded-pill">' + chargeNames.length + '</span>' +
              '</span>'
              : '<span class="text-muted">&mdash;</span>';

            // In the "all" scope the date cell also shows the owner, so payments
            // made by a previous owner are attributable at a glance.
            var ownerHtml = '';
            if (showOwner) {
              var ownerBadge = txn.is_current_plan
                ? ' <span class="badge bg-success">Current</span>'
                : ' <span class="badge bg-label-secondary">Previous</span>';
              ownerHtml =
                '<div class="fw-semibold">' + escapeHtml(txn.owner_name || 'Unknown owner') + ownerBadge + '</div>' +
                '<div class="text-muted">' + escapeHtml(txn.ownership_period || '—') + '</div>';
            }

            return '<tr>' +
              '<td><div class="fw-semibold text-nowrap">' + escapeHtml(txn.paid_at_label) + '</div>' + ownerHtml + '</td>' +
              '<td>' + chargesHtml + '</td>' +
              '<td class="text-end text-nowrap">' + formatMoney(txn.charge_subtotal) + '</td>' +
              '<td class="text-end text-nowrap">' + formatMoney(txn.tax_amount) + '</td>' +
              '<td class="text-end text-nowrap">' + formatMoney(txn.late_fee_amount) + '</td>' +
              '<td class="text-end text-nowrap fw-semibold">' + formatMoney(txn.amount_paid) + '</td>' +
              '<td class="text-center text-nowrap">' + statusBadge(txn.status, txn.status_label) + '</td>' +
              '<td class="text-center text-nowrap">' +
              // The plain receipt URL, not the auto-print one: the frame prints
              // it itself, and ?print=1 would make it print twice.
              '<button type="button" class="btn btn-sm btn-icon btn-text-primary receipt-btn me-1" ' +
              'title="Print receipt" onclick="printPaymentsDocument(\'' + escapeHtml(txn.receipt_url) + '\')">' +
              '<i class="ti tabler-printer"></i></button>' +
              '<a class="btn btn-sm btn-icon btn-text-primary receipt-btn" ' +
              'href="' + escapeHtml(txn.download_url) + '" title="Download receipt (PDF)">' +
              '<i class="ti tabler-download"></i></a>' +
              '</td>' +
              '</tr>';
          }).join('');

          initHistoryTooltips();
        }

        function updateHistorySummary(summary, paginationHtml) {
          var currency = PAYMENT_CURRENCY || '';
          if (document.getElementById('historyCount')) {
            document.getElementById('historyCount').textContent = summary.count;
          }
          if (document.getElementById('historyCharges')) {
            document.getElementById('historyCharges').textContent = formatMoney(summary.charges) + ' ' + currency;
          }
          if (document.getElementById('historyTax')) {
            document.getElementById('historyTax').textContent = formatMoney(summary.tax) + ' ' + currency;
          }
          if (document.getElementById('historyTotal')) {
            document.getElementById('historyTotal').textContent = formatMoney(summary.total) + ' ' + currency;
          }

          // The pagination of the app - the same one the properties page uses.
          var holder = document.getElementById('historyPagination');
          if (holder) {
            holder.innerHTML = paginationHtml || '';
          }
        }

        function loadPaymentHistory() {
          var body = document.getElementById('historyTableBody');
          if (!body) return;

          body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">' +
            '<span class="spinner-border spinner-border-sm me-2"></span>Loading transactions…</td></tr>';

          var params = new URLSearchParams(historyFilters());
          params.set('page', historyState.page);

          fetch(PAYMENT_HISTORY_URL + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
          })
            .then(function(r) {
              if (!r.ok) throw new Error('Request failed with status ' + r.status);
              return r.json();
            })
            .then(function(data) {
              if (!data || !data.ok) throw new Error('Could not load payment history.');
              renderHistoryRows(data.transactions, data.scope);
              updateHistorySummary(data.summary, data.pagination_html);
            })
            .catch(function(err) {
              body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">' +
                escapeHtml(err.message || 'Could not load payment history.') + '</td></tr>';
            });
        }

        function resetHistoryFilters() {
          var scope = document.getElementById('historyScope');
          if (scope) scope.value = 'current';
          ['historySearch', 'historyMethod', 'historyFrom', 'historyTo'].forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;

            // A date picker keeps its own state, so it has to be cleared.
            if (el._flatpickr) {
              el._flatpickr.clear();
            }
            el.value = '';
          });
        }

        document.addEventListener('DOMContentLoaded', function() {
          var methodSel = document.getElementById('payMethod');
          if (methodSel) {
            methodSel.addEventListener('change', syncRefRequirement);
            syncRefRequirement();
          }

          var amountInput = document.getElementById('payAmount');
          if (amountInput) amountInput.addEventListener('input', scheduleLateFeePreview);

          var paidInput = document.querySelector('#paymentModal input[name="paid_at"]');
          if (paidInput) paidInput.addEventListener('change', scheduleLateFeePreview);

          // Print report: reveal the date pickers only for a custom range, and
          // clear them otherwise so a stale from/to cannot leak into the report.
          var presetSel = document.getElementById('reportPreset');
          var customRange = document.getElementById('reportCustomRange');
          if (presetSel && customRange) {
            var syncCustomRange = function() {
              var isCustom = presetSel.value === 'custom';
              customRange.classList.toggle('d-none', !isCustom);
              if (!isCustom) {
                var from = document.getElementById('reportFrom');
                var to = document.getElementById('reportTo');
                if (from) from.disabled = true;
                if (to) to.disabled = true;
              } else {
                var from2 = document.getElementById('reportFrom');
                var to2 = document.getElementById('reportTo');
                if (from2) from2.disabled = false;
                if (to2) to2.disabled = false;
              }
            };
            presetSel.addEventListener('change', syncCustomRange);
            syncCustomRange();
          }

          document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'payment_mode') {
              applyPaymentMode();
              scheduleLateFeePreview();
            }
          });

          if (window.jQuery) {
            jQuery(document).on('change', '#payLedgerIds', function() {
              onChargesChanged();
              scheduleLateFeePreview();
            });
            jQuery(document).on('shown.bs.modal', '#paymentModal', function() {
              applyPrefill();
              scheduleLateFeePreview();
            });
            jQuery(document).on('click', '#paySelectAllBtn', function() {
              var el = document.getElementById('payLedgerIds');
              if (!el) return;
              var allSelected = this.textContent.trim() === 'Clear all';
              setChargeSelection(allSelected
                ? []
                : Array.prototype.slice.call(el.options).map(function(o) {
                  return o.value;
                }));
              applyPaymentMode();
            });
          }

          // Delegated so it also covers the table rendered for each ownership in the
          // "all payments" view, all of which share this one script.
          document.addEventListener('change', function(e) {
            var el = e.target;
            if (!el || !el.hasAttribute) return;

            if (el.hasAttribute('data-charge-check')) {
              syncUnpayBar(el.getAttribute('data-charge-check'));
              return;
            }

            if (el.hasAttribute('data-select-all')) {
              var tableId = el.getAttribute('data-select-all');
              chargeChecks(tableId).forEach(function(check) {
                if (!check.disabled) check.checked = el.checked;
              });
              syncUnpayBar(tableId);
            }
          });

          document.addEventListener('click', function(e) {
            var el = e.target && e.target.closest
              ? e.target.closest('[data-unpay-selected],[data-unpay-clear]')
              : null;
            if (!el) return;

            if (el.hasAttribute('data-unpay-clear')) {
              clearChargeSelection(el.getAttribute('data-unpay-clear'));
              return;
            }

            openUnpayCharges(el.getAttribute('data-unpay-selected'), []);
          });

          var unpayChargesBtn = document.getElementById('confirmUnpayChargesBtn');
          if (unpayChargesBtn) {
            unpayChargesBtn.addEventListener('click', submitUnpayCharges);
          }

          // Print Payments prints from this page rather than navigating, so the
          // dialog opens straight away. The action attribute stays as fallback.
          var reportForm = document.getElementById('reportPrintForm');
          if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
              e.preventDefault();
              printPaymentsReport();
            });
          }

          var sw = document.querySelector('.payment-view-switch');
          if (sw) {
            sw.addEventListener('change', function() {
              var showAll = this.value === 'all';
              document.querySelector('.payment-panel-current')?.classList.toggle('d-none', showAll);
              document.querySelector('.payment-panel-all')?.classList.toggle('d-none', !showAll);
            });
          }

          // Payment History modal: load on open, then filter/page in place.
          var historyModal = document.getElementById('paymentHistoryModal');
          if (historyModal && typeof bootstrap !== 'undefined') {
            historyModal.addEventListener('shown.bs.modal', function() {
              historyState.page = 1;
              loadPaymentHistory();
            });
          }

          var historyApply = document.getElementById('historyApplyBtn');
          if (historyApply) {
            historyApply.addEventListener('click', function() {
              historyState.page = 1;
              loadPaymentHistory();
            });
          }

          var historyReset = document.getElementById('historyResetBtn');
          if (historyReset) {
            historyReset.addEventListener('click', function() {
              resetHistoryFilters();
              historyState.page = 1;
              loadPaymentHistory();
            });
          }

          var historySearch = document.getElementById('historySearch');
          if (historySearch) {
            historySearch.addEventListener('keydown', function(e) {
              if (e.key === 'Enter') {
                e.preventDefault();
                historyState.page = 1;
                loadPaymentHistory();
              }
            });
          }

          ['historyScope', 'historyMethod', 'historyFrom', 'historyTo'].forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', function() {
              historyState.page = 1;
              loadPaymentHistory();
            });
          });

          // The pagination links are rendered by the server; they page the table
          // instead of leaving the page.
          var historyPagination = document.getElementById('historyPagination');
          if (historyPagination) {
            historyPagination.addEventListener('click', function(e) {
              var link = e.target.closest('a.page-link');
              if (!link) return;

              e.preventDefault();
              var url = new URL(link.href, window.location.origin);
              historyState.page = parseInt(url.searchParams.get('page') || '1', 10) || 1;
              loadPaymentHistory();
            });
          }

          // The date pickers (flatpickr) come with the theme scripts, which run
          // after this script - so they are set up once the page is ready.
          var initHistoryDatePickers = function() {
            if (!window.flatpickr) return;

            ['historyFrom', 'historyTo'].forEach(function(id) {
              var el = document.getElementById(id);
              if (!el || el._flatpickr) return;

              el.flatpickr({
                monthSelectorType: 'static',
                dateFormat: 'Y-m-d',
                allowInput: true,
                altInput: false
              });
            });
          };

          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initHistoryDatePickers);
          } else {
            initHistoryDatePickers();
          }
        });
      </script>
    @endpush
  @endif
</div>
