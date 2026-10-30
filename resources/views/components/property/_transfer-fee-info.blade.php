@php
  $tfService = app(\App\Services\Property\PropertyTransferService::class);
  $tfElig = $tfService->eligibility($ownerable);
  $tfPending = $tfElig['pending'];
  $tfTransfer = $tfElig['plan']?->isTransfer() ? $tfElig['plan']->propertyOwner?->incomingTransfer : null;
  $tfUser = auth()->user();
  $tfIsOwner = $tfUser->id === $tfElig['owner']?->id;

  if (! ($tfUser->hasRole('Super Admin')
      || $tfIsOwner
      || $tfUser->id === \App\Models\Society::find($tfElig['quote']['society_id'])?->owner_id
      || in_array($tfUser->id, [$tfPending?->requested_by_user_id, $tfPending?->recipient_user_id], true))) {
      return;
  }

  // Approved transfer => frozen values; otherwise today's quote.
  $tfFee = $tfTransfer ? [
      'is_percentage' => (bool) $tfTransfer->transfer_fee_in_per,
      'percent' => (float) $tfTransfer->transfer_fee,
      'base' => (float) $tfTransfer->fee_base,
      'amount' => (float) $tfTransfer->fee_amount,
      'currency' => $tfTransfer->currency,
      'matched' => true,
  ] : $tfElig['quote'];

  $tfLabel = ! $tfFee['matched']
      ? 'No transfer fee configured for this society — 0.00 '.$tfFee['currency']
      : ($tfFee['is_percentage'] ? floatval($tfFee['percent']).'% of listed price = ' : '')
        .number_format($tfFee['amount'], 2).' '.$tfFee['currency'];
@endphp

<div class="card border-0 shadow-sm rounded-3 p-4 mb-4 transfer-fee-info-card">
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
          This fee was charged to you when transfer <b>{{ $tfTransfer->reference_no }}</b> was approved — it is payable to the society now. Record it from the Payments tab.
        @else
          Transfer <b>{{ $tfTransfer->reference_no }}</b> was approved — this fee is payable to the society by the incoming owner.
        @endif
      </span>
    </div>
  @elseif($tfPending)
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0">
      <i class="ti tabler-hourglass-high"></i>
      <span class="small"><b>{{ $tfPending->reference_no }}</b> is pending — waiting for the society owner's approval.</span>
    </div>
  @elseif(! $tfElig['can_transfer'])
    <div class="alert alert-secondary d-flex align-items-center gap-2 mb-0">
      <i class="ti tabler-info-circle"></i>
      <span class="small">{{ $tfService->eligibilityMessage($tfElig['reasons']) }}</span>
    </div>
  @else
    <div class="alert alert-success d-flex align-items-center gap-2 mb-0">
      <i class="ti tabler-circle-check-filled"></i>
      <span class="small">This property is ready to transfer — the fee above is what the society charges.</span>
    </div>
  @endif
</div>
