@php
  // All committed-plan data is prepared here so this file is self-contained.
  $paymentPlanStructure = app(\App\Services\Property\PaymentPlanStructureService::class)->build($property);
  extract($paymentPlanStructure, EXTR_OVERWRITE);

  if (! $ownershipPlan) return;

  $isPlanCompleted = $ownershipPlan->status === 'completed';

  // A transfer plan carries the society transfer fee only - it is not edited
  // and it never gets an installment plan attached.
  $isTransferPlan = (bool) $ownershipPlan->isTransfer();

  // A plan is billed like a transfer plan when it is a transfer plan itself, or
  // when its snapshot carries a transfer fee (even a frozen 0). Such a plan's
  // whole value is the transfer fee: gross = final = fee, not the listed price.
  $hasTransferFee = $isTransferPlan || $ownershipPlan->transfer_fee !== null;
  $transferFeeAmount = $hasTransferFee
      ? (float) ($isTransferPlan ? $data['final_price'] : $ownershipPlan->transfer_fee)
      : 0.0;
  $transferFeePercent = null;

  if ($hasTransferFee) {
      if ($ownershipPlan->transfer_fee_in_per && (float) $data['listing_price'] > 0) {
          $transferFeeAmount = (float) $data['listing_price'] * (float) $ownershipPlan->transfer_fee / 100;
      }

      $transferFeeAmount = round($transferFeeAmount, 2);
      $transferFeePercent = (float) $data['listing_price'] > 0
          ? $transferFeeAmount / (float) $data['listing_price'] * 100
          : 0.0;
  }

  // Build the shared summary structure from the committed snapshot so this blade
  // and payments-planning render through the same plan-structure-summary component.
  $planStructureSummary = [
      'listingPrice'         => $data['listing_price'],
      'listingUnit'          => $unit,
      'premiumCharges'       => $hasTransferFee ? [] : $premiumRows,
      'premiumsTotal'        => $hasTransferFee ? 0 : $data['premiums_total'],
      'grossAmount'          => $hasTransferFee ? $transferFeeAmount : $grossAmount,
      'excludedCharges'      => $hasTransferFee ? [] : $excludedFees,
      'includedCharges'      => $hasTransferFee
          ? [['label' => 'Transfer Fee', 'amount' => $transferFeeAmount, 'note' => 'Included']]
          : $includedFees,
      'excludedTotal'        => $hasTransferFee ? 0 : $excludedTotal,
      'includedTotal'        => $hasTransferFee ? $transferFeeAmount : $includedTotal,
      'totalOneTime'         => $hasTransferFee ? $transferFeeAmount : $excludedTotal + $includedTotal,
      'remainingAmount'      => $hasTransferFee ? 0 : $remainingAmount,
      'hasDefaultPlan'       => (bool) $installment,
      'extraInstallment'     => $extraAmount,
      'balloonAmount'        => $totalBalloon,
      'installmentRemaining' => $installmentRemaining,
      'finalAmount'          => $hasTransferFee ? $transferFeeAmount : $data['final_price'],
      'perBalloon'           => $installment['per_balloon'] ?? 0,
      'perInstallment'       => $installment['per_installment'] ?? 0,
      'installmentCount'     => $installment['num'] ?? null,
      'transferFee'          => $hasTransferFee ? [
          'label' => 'Transfer Fee',
          'amount' => $transferFeeAmount,
          'percent' => $transferFeePercent,
      ] : null,
      'isTransferPlan'       => $hasTransferFee,
  ];
@endphp

<div class="planner">
  <div class="row g-4">

    {{-- Main column --}}
    <div class="col-lg-8 order-2 order-lg-1">

      @if($owner)
        @php
          $transferSvc = app(\App\Services\Property\PropertyTransferService::class);
          $transferState = $transferSvc->eligibility($property);
          $transferPending = $transferState['pending'];
          $canTransferNow = $transferState['can_transfer'];

          // Fee card: approved transfer => frozen values; otherwise today's quote.
          $tfTransfer = $transferState['plan']?->isTransfer() ? $transferState['plan']->propertyOwner?->incomingTransfer : null;
          $tfFee = $tfTransfer ? [
              'is_percentage' => (bool) $tfTransfer->transfer_fee_in_per,
              'percent' => (float) $tfTransfer->transfer_fee,
              'base' => (float) $tfTransfer->fee_base,
              'amount' => (float) $tfTransfer->fee_amount,
              'currency' => $tfTransfer->currency,
              'matched' => true,
          ] : $transferState['quote'];
          $tfLabel = ! $tfFee['matched']
              ? 'No transfer fee configured for this society — 0.00 '.$tfFee['currency']
              : ($tfFee['is_percentage'] ? floatval($tfFee['percent']).'% of listed price = ' : '')
                .number_format($tfFee['amount'], 2).' '.$tfFee['currency'];

          // Fee card is visible only to the owner, society owner, super admin or a transfer party.
          $tfUser = auth()->user();
          $tfIsOwner = $tfUser->id === $owner->id;
          $tfShow = $tfUser->hasRole('Super Admin')
              || $tfIsOwner
              || $tfUser->id === \App\Models\Society::find($tfFee['society_id'] ?? $transferState['quote']['society_id'])?->owner_id
              || in_array($tfUser->id, [$transferPending?->requested_by_user_id, $transferPending?->recipient_user_id], true);
        @endphp

        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
          {{-- Owner / Member --}}
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div class="d-flex align-items-center gap-2">
              <i class="ti tabler-user text-primary fs-5"></i>
              <h5 class="fw-bold mb-0">Owner / Member</h5>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <a href="{{ route('property_owners.plan.print', $ownershipPlan) }}?mode=brief"
                 onclick="event.preventDefault(); printDocument(this.href)"
                 class="btn btn-sm btn-outline-primary" title="Print the committed payment plan for this owner">
                <i class="ti tabler-printer me-1"></i> Print Owner Plan
              </a>
              @if($isPlanCompleted && $transferPending)
                @can('property_transfer_print')
                  <a href="{{ route('property_transfers.print', ['transfer' => $transferPending, 'print' => 1]) }}"
                     onclick="event.preventDefault(); printDocument(this.href)"
                     class="btn btn-sm btn-outline-secondary" title="Print the pending transfer">
                    <i class="ti tabler-printer me-1"></i> Print Pending Transfer
                  </a>
                @endcan
              @endif
            </div>
          </div>

          @if($isPlanCompleted)
            <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0">
              <div class="d-flex align-items-center gap-2">
                <i class="ti tabler-certificate"></i>
                <span><b>Owner (fully paid):</b> {{ $owner->first_name }} {{ $owner->last_name }} ({{ $owner->email }})</span>
              </div>
              <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge bg-label-success d-inline-flex align-items-center gap-1">
            <i class="ti tabler-circle-check-filled"></i> Payments Complete
          </span>
                @can('property_transfer_create')
                  @if($transferPending)
                    <span class="badge bg-label-warning d-inline-flex align-items-center gap-1">
                <i class="ti tabler-hourglass-high"></i> Transfer Pending ({{ $transferPending->reference_no }})
              </span>
                  @elseif($canTransferNow)
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                            data-bs-target="#transferPropertyModal" title="Transfer this property to another member">
                      <i class="ti tabler-exchange me-1"></i> Transfer Property
                    </button>
                  @endif
                @endcan
              </div>
            </div>
          @elseif($unCompletePayments)
            <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0">
              <div class="d-flex align-items-center gap-2">
                <i class="ti tabler-user-check"></i>
                <span><b>Reserved Owner:</b> {{ $owner->first_name }} {{ $owner->last_name }} ({{ $owner->email }})</span>
              </div>
              @can('property_reserve_owner')
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#cancelOwnershipModal"
                        title="Cancel this ownership reservation and relist for sale">
                  <i class="ti tabler-user-off me-1"></i> Cancel Ownership Reservation
                </button>
              @endcan
            </div>
          @endif

          {{-- Society Transfer Fee --}}
          @if($tfShow)
            <hr class="my-4">

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
              <div class="d-flex align-items-center gap-2">
                <i class="ti tabler-cash-banknote text-primary fs-5"></i>
                <h5 class="fw-bold mb-0">Society Transfer Fee</h5>
              </div>
              <span class="badge bg-label-primary">
          {{ ! $tfFee['matched'] ? 'Not configured' : ($tfFee['is_percentage'] ? 'Percentage' : 'Fixed') }}
        </span>
            </div>

            <div class="fw-bold mb-1">{{ $tfLabel }}</div>

            @if($tfFee['matched'] && $tfFee['is_percentage'])
              <small class="text-muted d-block mb-3">
                Base (listed price): {{ number_format($tfFee['base'], 2) }} {{ $tfFee['currency'] }}
                — frozen on the transfer at submission.
              </small>
            @else
              <div class="mb-3"></div>
            @endif

            @if($tfTransfer)
              <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
                <i class="ti tabler-info-circle"></i>
                <span class="small">
            @if($tfIsOwner)
                    This fee was charged to you when transfer <b>{{ $tfTransfer->reference_no }}</b> was approved — it
                    is payable to the society now. Record it from the Payments tab.
                  @else
                    Transfer <b>{{ $tfTransfer->reference_no }}</b> was approved — this fee is payable to the society by
                    the incoming owner.
                  @endif
          </span>
              </div>
            @elseif($transferPending)
              <div class="alert alert-warning d-flex align-items-center gap-2 mb-0">
                <i class="ti tabler-hourglass-high"></i>
                <span class="small"><b>{{ $transferPending->reference_no }}</b> is pending — waiting for the society owner's approval.</span>
              </div>
            @elseif(! $canTransferNow)
              <div class="alert alert-secondary d-flex align-items-center gap-2 mb-0">
                <i class="ti tabler-info-circle"></i>
                <span class="small">{{ $transferSvc->eligibilityMessage($transferState['reasons']) }}</span>
              </div>
            @else
              <div class="alert alert-success d-flex align-items-center gap-2 mb-0">
                <i class="ti tabler-circle-check-filled"></i>
                <span
                  class="small">This property is ready to transfer — the fee above is what the society charges.</span>
              </div>
            @endif
          @endif
        </div>

        @if($isPlanCompleted)
          @include('_partials._modals.transfer_property', [
            'ownerableType' => $ownerableType,
            'ownerableId' => $ownerableId,
            'ownerable' => $property,
            'entityLabel' => $entityLabel,
          ])
        @endif
      @endif
      {{-- Charges Section (read-only snapshot) --}}
      <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
          <div class="d-flex align-items-center gap-2">
            <i class="ti tabler-receipt text-primary fs-5"></i>
            <h5 class="fw-bold mb-0">Charges</h5>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
           <span class="badge bg-label-success d-inline-flex align-items-center gap-1">
             <i class="ti tabler-circle-check-filled"></i> {{ $isPlanCompleted ? 'Fully Paid Plan' : 'Committed Plan' }}
           </span>
            @if($unCompletePayments && ! $isTransferPlan)
              @can('payment_plan_edit')
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                        data-bs-target="#editOwnershipPlanModal">
                  <i class="ti tabler-edit me-1"></i> Edit Values
                </button>
              @endcan
            @endif
          </div>
        </div>

        <div class="border rounded-3 p-3 mb-3 bg-label-secondary">
          <div class="row g-3">
            <div class="col-md-6">
              <small class="micro-label text-muted text-uppercase d-block mb-1">Listed Price</small>
              <div class="fw-semibold small">{{ number_format($data['listing_price'], 2) }} {{ $unit }}</div>
            </div>
            <div class="col-md-6">
              <small class="micro-label text-muted text-uppercase d-block mb-1">Final Price</small>
              <div class="fw-semibold small">{{ number_format($data['final_price'], 2) }} {{ $unit }}</div>
            </div>
          </div>
        </div>

        @if($premiumRows)
          <div class="row g-3 mb-3">
            @foreach($premiumRows as $pc)
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 border-success border-opacity-25 bg-success bg-opacity-10">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                     <span class="rounded bg-primary bg-opacity-10 p-2 d-inline-flex flex-shrink-0">
                       <i class="ti tabler-star text-primary"></i>
                     </span>
                      <div class="min-w-0">
                        <div class="fw-semibold small">{{ $pc['label'] }}</div>
                        <div class="text-muted text-tiny">{{ $pc['detail'] }}</div>
                      </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                      <div class="fw-bold">{{ number_format($pc['amount'], 2) }} {{ $unit }}</div>
                      <span class="badge badge-xs bg-danger">Premium</span>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @endif

        @if($excludedFees->count() || $includedFees->count())
          <div class="row g-3">
            @foreach($excludedFees as $fee)
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 border-success border-opacity-25 bg-success bg-opacity-10">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                     <span class="rounded bg-primary bg-opacity-10 p-2 d-inline-flex flex-shrink-0">
                       <i class="ti tabler-receipt text-primary"></i>
                     </span>
                      <div class="min-w-0">
                        <div class="fw-semibold small">{{ $fee['label'] }}</div>
                        <div class="text-muted text-tiny">{{ $fee['detail'] }}</div>
                      </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                      <div class="fw-bold">{{ number_format($fee['amount'], 2) }} {{ $unit }}</div>
                      <span class="badge badge-xs bg-danger">Excluded</span>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
            @foreach($includedFees as $fee)
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                     <span class="rounded bg-primary bg-opacity-10 p-2 d-inline-flex flex-shrink-0">
                       <i class="ti tabler-receipt text-primary"></i>
                     </span>
                      <div class="min-w-0">
                        <div class="fw-semibold small">{{ $fee['label'] }}</div>
                        <div class="text-muted text-tiny">{{ $fee['detail'] }}</div>
                      </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                      <div class="fw-bold">{{ number_format($fee['amount'], 2) }} {{ $unit }}</div>
                      <span class="badge badge-xs bg-primary">Included</span>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
            <i class="ti tabler-calendar-x fs-2 opacity-50 mb-2"></i>
            <small>No charges configured.</small>
          </div>
        @endif

        @include('components.property._charges-explanation')
      </div>

      {{-- Installment Section (single plan) --}}
      <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
          <div class="d-flex align-items-center gap-2">
            <i class="ti tabler-calendar-repeat text-primary fs-5"></i>
            <h5 class="fw-bold mb-0">Installment Plan</h5>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($installment)
              <span class="badge bg-label-primary">
               <i class="ti tabler-check me-1"></i>{{ $installment['name'] }}
             </span>
              @if($unCompletePayments)
                @can('installment_plan_edit')
                  <button type="button" class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#editOwnershipInstallmentModal">
                    <i class="ti tabler-edit me-1"></i> Edit Values
                  </button>
                @endcan
                @can('installment_plan_delete')
                  <form action="{{ route('ownership_payment_plans.installment.remove', $ownershipPlan) }}" method="POST"
                        onsubmit="return confirmDelete(event, null)">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            title="Remove the installment plan and reset the installment values to none">
                      <i class="ti tabler-trash me-1"></i> Remove Installment
                    </button>
                  </form>
                @endcan
              @endif
            @else
              @if($unCompletePayments && ! $isTransferPlan)
                @can('installment_plan_add')
                  <button type="button" class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#addOwnershipInstallmentModal">
                    <i class="ti tabler-plus me-1"></i> Add Installment Plan
                  </button>
                @endcan
              @endif
            @endif
          </div>
        </div>

        @if($installment)
          <div class="row g-2 mb-3">
            <div class="col-sm-6 col-xl-3">
              <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                <small class="micro-label text-muted text-uppercase d-block mb-1">Installments</small>
                <div class="fw-semibold">{{ $installment['num'] }}
                  @if($installment['frequency'])
                    <span class="text-muted fw-normal">X· {{ ucfirst($installment['frequency']) }}</span>
                  @endif
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-xl-3">
              <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                <small class="micro-label text-muted text-uppercase d-block mb-1">Per Balloon</small>
                <div class="fw-semibold">
                  @if($installment['per_balloon'] > 0)
                    {{ number_format($installment['per_balloon'], 2) }} {{ $unit }}
                    @if($installment['balloon_count'] > 0)
                      <span class="text-muted fw-normal">({{ $installment['balloon_count'] }}x)</span>
                    @endif
                  @else
                    <span class="text-muted fw-normal">—</span>
                  @endif
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-xl-3">
              <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                <small class="micro-label text-muted text-uppercase d-block mb-1">Per Installment</small>
                <div class="fw-semibold">{{ number_format($installment['per_installment'], 2) }} {{ $unit }}
                  @if(($installment['normal_count'] ?? 0) > 0)
                    <span class="text-muted fw-normal">({{ $installment['normal_count'] }}x)</span>
                  @endif
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-xl-3">
              <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                <small class="micro-label text-muted text-uppercase d-block mb-1">Total Amount</small>
                <div class="fw-semibold">{{ number_format($installment['total'], 2) }} {{ $unit }}</div>
              </div>
            </div>
          </div>

          {{-- Installment schedule --}}
          <div class="border rounded-3 overflow-hidden">
            <div class="px-3 py-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-between">
              <span class="fw-semibold small text-primary">Installment Schedule</span>
              <span
                class="small text-muted">{{ $installment['num'] }} {{ $installment['num'] > 1 ? 'installments' : 'installment' }} · {{ ucfirst($installment['frequency'] ?? '—') }}
                @if($installment['balloon_count'] > 0)
                  · {{ $installment['balloon_count'] }} balloon{{ $installment['balloon_count'] > 1 ? 's' : '' }}
                @endif
             </span>
            </div>
            <div class="table-responsive schedule-scroll">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr>
                  <th class="ps-3 col-seq">#</th>
                  <th class="ps-3">Type</th>
                  <th class="ps-3">Amount</th>
                  <th class="ps-3">Cumulative</th>
                </tr>
                </thead>
                <tbody>
                @foreach($installment['schedule'] as $seq => $row)
                  <tr class="{{ $row['type'] === 'balloon' ? 'bg-label-primary' : '' }}">
                    <td class="ps-3">{{ $seq + 1 }}</td>
                    <td class="ps-3 text-heading">
                      @if($row['type'] === 'balloon')
                        Balloon #{{ $row['balloon_no'] }}
                        @if($installment['balloon_frequency'])
                          <span class="text-muted fw-normal">({{ ucfirst($installment['balloon_frequency']) }})</span>
                        @endif
                      @else
                        Installment #{{ $row['no'] }}
                      @endif
                    </td>
                    <td class="ps-3 fw-semibold">{{ number_format($row['amount'], 2) }} {{ $unit }}</td>
                    <td class="ps-3 text-muted">{{ number_format($row['cumulative'], 2) }} {{ $unit }}</td>
                  </tr>
                @endforeach
                <tr class="table-light">
                  <td class="ps-3 text-heading fw-bold" colspan="2">Final Amount:</td>
                  <td class="ps-3 fw-bold">{{ number_format($data['final_price'], 2) }} {{ $unit }}</td>
                  <td class="ps-3 text-muted">—</td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>
        @else
          <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
            <i class="ti tabler-calendar-x fs-2 opacity-50 mb-2"></i>
            @if($isTransferPlan)
              <small class="text-center">
                This is a transfer plan - the society transfer fee is billed as a single payment,
                there is no installment plan.
              </small>
            @else
              <small class="text-center">
                No installment plan attached to this committed plan yet.
                <br>Click <b>Add Installment Plan</b> above to attach one.
              </small>
            @endif
          </div>
        @endif
      </div>
    </div>

    {{-- Summary sidebar (shared component) --}}
    <x-property.plan-structure-summary :summary="$planStructureSummary" :is-unit="$isUnit"
                                       :entity-label="$entityLabel" :listing-unit="$unit" />
  </div>
</div>

{{-- Edit Ownership Plan Modal --}}
@if($unCompletePayments && ! $isTransferPlan && Auth()->user()->can('payment_plan_edit'))
  <div class="modal fade" id="editOwnershipPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-simple modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Edit Committed Plan</h4>
            <p class="text-muted mb-0">Update the committed charge values for this owner's plan.</p>
          </div>

          <form action="{{ route('ownership_payment_plans.update', $ownershipPlan) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold">Listed Price</label>
                <input type="number" name="listed_price" class="form-control" step="0.01" min="0"
                       value="{{ $ownershipPlan->listed_price }}">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Currency</label>
                <select name="price_unit" class="form-select">
                  <option value="PKR" @selected($ownershipPlan->price_unit === 'PKR')>PKR</option>
                  <option value="USD" @selected($ownershipPlan->price_unit === 'USD')>USD</option>
                </select>
              </div>
            </div>

            <hr class="my-2">
            <p class="section-caption fw-semibold small text-uppercase text-muted mb-3">
              Charge Values
              <span class="caption-hint fw-normal">(leave blank to keep current)</span>
            </p>

            <div class="row g-3">
              @foreach([
                  ['key' => 'booking_fee', 'label' => 'Booking Fee', 'surplus' => false],
                  ['key' => 'development_fee', 'label' => 'Development Fee', 'surplus' => true],
                  ['key' => 'down_payment', 'label' => 'Down Payment', 'surplus' => false],
                  ['key' => 'confirmation_fee', 'label' => 'Confirmation Fee', 'surplus' => false],
                  ['key' => 'possession_fee', 'label' => 'Possession Fee', 'surplus' => true],
              ] as $fk)
                <div class="col-md-6">
                  <div class="border rounded-3 p-3 bg-label-secondary h-100">
                    <label class="form-label fw-semibold mb-2">{{ $fk['label'] }}</label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <input type="number" name="{{ $fk['key'] }}" class="form-control" step="0.01" min="0"
                             placeholder="{{ $unit }} or %" value="{{ $ownershipPlan->{$fk['key']} }}">
                      <div class="form-check form-switch mb-0 flex-shrink-0">
                        <input type="checkbox" name="{{ $fk['key'] }}_in_per" class="form-check-input"
                               value="1" {{ $ownershipPlan->{$fk['key'].'_in_per'} ? 'checked' : '' }}>
                        <label class="form-check-label small text-nowrap">%</label>
                      </div>
                    </div>
                    @if($fk['surplus'])
                      <div class="form-check">
                        <input type="checkbox" name="{{ $fk['key'] }}_surplus" class="form-check-input"
                               value="1" {{ $ownershipPlan->{$fk['key'].'_surplus'} ? 'checked' : '' }}>
                        <label class="form-check-label small">Excluded (extra / billed on top)</label>
                      </div>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>

            @if(count($featurePremiumKeys))
              <hr class="my-2">
              <p class="section-caption fw-semibold small text-uppercase text-muted mb-3">
                Feature Premiums <span class="caption-hint fw-normal">(% of listed price)</span>
              </p>

              <div class="row g-3 mb-3">
                @foreach($featurePremiumKeys as $pk)
                  <div class="col-md-4">
                    <div class="border rounded-3 p-3 bg-label-secondary h-100">
                      <label class="form-label fw-semibold mb-2">{{ $pk['label'] }}</label>
                      <div class="input-group">
                        <input type="number" name="{{ $pk['key'] }}" class="form-control" step="0.01" min="0" max="100"
                               placeholder="%" value="{{ $ownershipPlan->{$pk['key']} }}">
                        <span class="input-group-text">%</span>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            @endif

            <div class="d-flex justify-content-end gap-2 pt-3">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">
                <i class="ti tabler-device-floppy me-1"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endif

{{-- Add Ownership Installment Modal --}}
@if($unCompletePayments && ! $installment && ! $isTransferPlan && Auth()->user()->can('installment_plan_add'))
  <div class="modal fade" id="addOwnershipInstallmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-simple modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Add Installment Plan</h4>
            <p class="text-muted mb-0">Pick an installment plan to attach to this committed plan.</p>
          </div>

          @if($addInstallmentOptions->count())
            <form action="{{ route('ownership_payment_plans.installment.store', $ownershipPlan) }}" method="POST">
              @csrf

              <div class="mb-3">
                <label class="form-label fw-semibold">
                  Installment Plan
                  @if($addInstallmentOptions->contains('applied', true))
                    <span
                      class="text-muted fw-normal">(Applied = already attached to this property's payment plan)</span>
                  @endif
                </label>
                <select id="addOwnershipInstallmentSelect" name="installment_source"
                        class="form-select installment-template-select"
                        data-preview="addOwnershipInstallmentPreview" required>
                  <option value="">Select an installment plan</option>

                  @foreach($addInstallmentOptions as $opt)
                    <option value="{{ $opt['source'] }}"
                            data-name="{{ $opt['name'] }}"
                            data-number-of-installments="{{ $opt['num'] }}"
                            data-frequency="{{ $opt['frequency'] }}"
                            data-additional="{{ $opt['additional'] }}"
                            data-additional-in-per="{{ $opt['additional_in_per'] }}"
                            data-balloon="{{ $opt['balloon'] }}"
                            data-balloon-in-per="{{ $opt['balloon_in_per'] }}"
                            data-balloon-frequency="{{ $opt['balloon_frequency'] }}">
                      {{ $opt['name'] }} — {{ $opt['info'] }}{{ $opt['applied'] ? ' (Applied)' : '' }}
                    </option>
                  @endforeach
                </select>

                <div id="addOwnershipInstallmentPreview" class="mt-3 d-none"
                     data-price-base="{{ $grossAmount }}"
                     data-included-total="{{ $includedTotal }}"
                     data-excluded-total="{{ $excludedTotal }}">
                  <div class="it-preview-list"></div>
                </div>

                <small class="text-muted d-block mt-2">
                  Shows all available installment plans. <b>Applied</b> plans are already attached to this
                  property's payment plan (including custom ones); the rest are category-matched templates you
                  can apply on top.
                </small>
              </div>

              <div class="d-flex justify-content-end gap-2 pt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                  <i class="ti tabler-plus me-1"></i> Attach Plan
                </button>
              </div>
            </form>
          @else
            <div class="border rounded-3 p-3 bg-label-secondary">
              <small class="text-muted">No installment plans or templates available for this category.</small>
            </div>
            <div class="d-flex justify-content-end gap-2 pt-3">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endif

{{-- Edit Ownership Installment Modal --}}
@if($unCompletePayments && $installment && Auth()->user()->can('installment_plan_edit'))
  <div class="modal fade" id="editOwnershipInstallmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-simple  modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Edit Installment Plan</h4>
            <p class="text-muted mb-0">Update the installment values for this committed plan.</p>
          </div>

          <form action="{{ route('ownership_payment_plans.installment.update', $ownershipPlan) }}" method="POST">
            @csrf
            @method('PUT')

            {{-- Prefill from template --}}
            <div class="mb-3">
              <label class="form-label fw-semibold">Load from Template <span
                  class="text-muted">(optional)</span></label>
              <select id="ownEditInstallmentTemplate" class="form-select">
                <option value="">Keep current values</option>
                @foreach($allInstallmentTemplates as $tpl)
                  <option value="{{ $tpl->id }}"
                          data-name="{{ $tpl->name }}"
                          data-number-of-installments="{{ $tpl->number_of_installments }}"
                          data-installment-frequency="{{ $tpl->installment_frequency }}"
                          data-additional-installment-amount="{{ $tpl->additional_installment_amount }}"
                          data-additional-installment-amount-in-per="{{ $tpl->additional_installment_amount_in_per ? 1 : 0 }}"
                          data-balloon-payment="{{ $tpl->balloon_payment }}"
                          data-balloon-payment-in-per="{{ $tpl->balloon_payment_in_per ? 1 : 0 }}"
                          data-balloon-payment-frequency="{{ $tpl->balloon_payment_frequency }}">
                    {{ $tpl->name }}
                  </option>
                @endforeach
              </select>
              <small class="text-muted">Selecting a template will overwrite the fields below with that template's
                values.</small>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Plan Name</label>
              <input type="text" name="name" id="ownEiName" class="form-control"
                     value="{{ $ownershipPlan->name }}">
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Number of Installments</label>
                <input type="number" name="number_of_installments" id="ownEiNumberOfInstallments" class="form-control"
                       min="1" value="{{ $ownershipPlan->number_of_installments }}">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Frequency</label>
                <select name="installment_frequency" id="ownEiFrequency" class="form-select">
                  @foreach(['monthly', 'quarterly', 'biannually', 'annually'] as $f)
                    <option
                      value="{{ $f }}" @selected($ownershipPlan->installment_frequency === $f)>{{ ucfirst($f) }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold">Additional Installment Amount</label>
                <input type="number" name="additional_installment_amount" id="ownEiAdditionalAmount"
                       class="form-control" step="0.01" min="0"
                       value="{{ $ownershipPlan->additional_installment_amount }}">
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch">
                  <input type="checkbox" name="additional_installment_amount_in_per" id="ownEiAdditionalInPer"
                         class="form-check-input" value="1"
                    {{ $ownershipPlan->additional_installment_amount_in_per ? 'checked' : '' }}>
                  <label class="form-check-label small">% of gross</label>
                </div>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold">Balloon Payment</label>
                <input type="number" name="balloon_payment" id="ownEiBalloonPayment" class="form-control"
                       step="0.01" min="0" value="{{ $ownershipPlan->balloon_payment }}">
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch">
                  <input type="checkbox" name="balloon_payment_in_per" id="ownEiBalloonInPer" class="form-check-input"
                         value="1" {{ $ownershipPlan->balloon_payment_in_per ? 'checked' : '' }}>
                  <label class="form-check-label small">% of gross</label>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Balloon Payment Frequency</label>
              <select name="balloon_payment_frequency" id="ownEiBalloonFrequency" class="form-select">
                <option value="" @selected(! $ownershipPlan->balloon_payment_frequency)>None</option>
                @foreach(['quarterly', 'biannually', 'annually'] as $f)
                  <option
                    value="{{ $f }}" @selected($ownershipPlan->balloon_payment_frequency === $f)>{{ ucfirst($f) }}</option>
                @endforeach
              </select>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">
                <i class="ti tabler-device-floppy me-1"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endif

@include('components.property._planning-styles')

@include('components.property._cancel-ownership-modal', [
  'ownerableType' => $ownerableType,
  'ownerableId' => $ownerableId,
  'entityLabel' => $entityLabel,
  'canCancelReservation' => $unCompletePayments,
])

@push('scripts')
  <script>
    document.getElementById('ownEditInstallmentTemplate')?.addEventListener('change', function() {
      var opt = this.selectedOptions[0];
      if (!opt || !opt.value) return;
      var fields = {
        'ownEiName': opt.getAttribute('data-name'),
        'ownEiNumberOfInstallments': opt.getAttribute('data-number-of-installments'),
        'ownEiFrequency': opt.getAttribute('data-installment-frequency'),
        'ownEiAdditionalAmount': opt.getAttribute('data-additional-installment-amount'),
        'ownEiBalloonPayment': opt.getAttribute('data-balloon-payment'),
        'ownEiBalloonFrequency': opt.getAttribute('data-balloon-payment-frequency')
      };
      for (var id in fields) {
        var el = document.getElementById(id);
        if (el && fields[id] !== null) el.value = fields[id];
      }
      var addPer = document.getElementById('ownEiAdditionalInPer');
      if (addPer) addPer.checked = opt.getAttribute('data-additional-installment-amount-in-per') === '1';
      var balloonPer = document.getElementById('ownEiBalloonInPer');
      if (balloonPer) balloonPer.checked = opt.getAttribute('data-balloon-payment-in-per') === '1';
    });
  </script>
@endpush
