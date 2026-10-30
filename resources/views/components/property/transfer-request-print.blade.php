@php
  $isUnit = $ownerable instanceof \App\Models\PropertyUnit;
  $entityLabel = $isUnit ? 'Unit' : 'Property';

  $currency = $transfer->currency ?: 'PKR';
  $feeAmount = (float) $transfer->feeAmount();
  $feeBase = (float) $transfer->fee_base;
  $isPercentFee = (bool) $transfer->transfer_fee_in_per;
  $feeRate = rtrim(rtrim(number_format((float) $transfer->transfer_fee, 2, '.', ''), '0'), '.');

  $fromOwner = $fromOwner ?: $transfer->fromOwnership?->owner;
  $recipient = $recipient ?: $transfer->recipient;
  $ownership = $transfer->fromOwnership;

  $statusLabel = match ($transfer->status) {
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      default => 'Pending Approval',
  };

  $planData = $planData ?? [];

  // What the outgoing ownership was worth, and what the incoming one owes.
  $outgoingPlanPrice = (float) ($plan?->final_price ?? 0);
  $outgoingPlanStatus = $plan?->status ?? '—';
  $newPlanPrice = (float) ($newPlan?->final_price ?? 0);
  $newPlanStatus = $newPlan?->status ?? null;
  $newPlanPaid = $newPlan
      ? (float) $newPlan->paymentTransactions()->where('status', 'paid')->sum('amount_paid')
      : 0.0;

  $logoSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="22" viewBox="0 0 32 22" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z" fill="#7367f0"/><path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616"/><path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616"/><path fill-rule="evenodd" clip-rule="evenodd" d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z" fill="#7367f0"/></svg>';
  $logoSrc = 'data:image/svg+xml;base64,'.base64_encode($logoSvg);
@endphp
  <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Property Transfer Slip {{ $transfer->reference_no }}</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 8mm 0 0 0;
    }

    body {
      font-family: Helvetica, Arial, sans-serif;
      font-size: 9px;
      color: #4b4b4b;
      margin: 0;
      padding: 8mm;
    }

    table {
      border-collapse: collapse;
    }

    p {
      margin: 0;
      line-height: 1.5;
    }

    .ink {
      color: #2f2b3d;
    }

    .muted {
      color: #8a8a8a;
    }

    .text-center {
      text-align: center;
    }

    .sec {
      margin-bottom: 14px;
    }

    .app {
      font-size: 16px;
      font-weight: bold;
      color: #2f2b3d;
    }

    table.head {
      width: 100%;
    }

    table.head td.right {
      text-align: right;
      vertical-align: top;
    }

    .doc-title {
      font-size: 10px;
      font-weight: bold;
      color: #2f2b3d;
      margin-bottom: 6px;
    }

    table.head .field {
      color: #2f2b3d;
      line-height: 1.5;
    }

    table.details {
      width: 100%;
    }

    table.details td.left {
      vertical-align: top;
      padding-right: 12px;
    }

    table.details td.right {
      vertical-align: top;
      text-align: right;
    }

    .sec-title {
      font-size: 9px;
      font-weight: bold;
      color: #2f2b3d;
      margin-bottom: 4px;
    }

    table.details .prop-name {
      font-weight: bold;
      color: #2f2b3d;
      line-height: 1.5;
    }

    table.details .row-line {
      line-height: 1.5;
    }

    table.items {
      width: 100%;
      margin: 10px 0 4px;
    }

    table.items th {
      font-size: 7.5px;
      text-transform: uppercase;
      text-align: left;
      color: #2f2b3d;
      padding: 4px 6px;
      border-bottom: 1px solid #d9dee3;
    }

    table.items th.amount, table.items td.amount {
      text-align: right;
    }

    table.items td {
      padding: 4px 6px;
      border-bottom: 0.5px solid #ececec;
      vertical-align: top;
    }

    table.items td.cname {
      color: #2f2b3d;
    }

    table.totals {
      width: 260px;
      margin-left: auto;
      margin-top: 4px;
    }

    table.totals td {
      padding: 3px 6px;
      border-bottom: 0.5px solid #ececec;
    }

    table.totals td.label {
      color: #8a8a8a;
    }

    table.totals td.value {
      text-align: right;
      color: #2f2b3d;
    }

    table.totals tr.grand td {
      border-top: 1px solid #4b4b4b;
      border-bottom: none;
      font-weight: bold;
      color: #2f2b3d;
    }

    .footer-line {
      border-top: 1px solid #d9d9d9;
      margin-top: 18px;
    }

    .note {
      color: #8a8a8a;
      font-size: 9px;
      line-height: 1.5;
      margin-top: 6px;
    }

    .pill {
      display: inline-block;
      padding: 1px 6px;
      border: 1px solid #d9dee3;
      border-radius: 3px;
      color: #2f2b3d;
    }

    .pill.ok {
      border-color: #8ec9a0;
      color: #2c7a4b;
    }

    .pill.no {
      border-color: #e0a2a2;
      color: #b00000;
    }

    .pill.wait {
      border-color: #e6c78a;
      color: #96690a;
    }
  </style>
</head>
<body>

{{-- HEADER --}}
<table class="head sec">
  <tr>
    <td>
      <img src="{{ $logoSrc }}" width="32" height="22" style="vertical-align: middle;" alt="">
      <span class="app" style="vertical-align: middle;">&nbsp;{{ config('variables.templateName') }}</span>
    </td>
    <td class="right">
      <div class="doc-title">PROPERTY TRANSFER SLIP</div>
      <div class="field"><span>Transfer Ref:</span> <b>{{ $transfer->reference_no }}</b></div>
      <div class="field"><span>Date:</span>
        <b>{{ $transfer->created_at?->format('M d, Y') }}</b>
        &nbsp;<span
          class="pill {{ $transfer->status === 'approved' ? 'ok' : ($transfer->status === 'rejected' ? 'no' : 'wait') }}">{{ $statusLabel }}</span>
      </div>
    </td>
  </tr>
</table>

{{-- PROPERTY + PARTIES --}}
<table class="details sec">
  <tr>
    <td class="left">
      <div class="sec-title">{{ $entityLabel }} Details:</div>
      @if(! empty($planData['property_name']))
        <div class="prop-name">{{ ucwords($planData['property_name']) }}</div>
        <div class="muted">{{ $entityLabel }} No: #{{ ucfirst($planData['property_no'] ?? '') }}</div>
        <div class="muted">{{ ucwords($planData['property_address'] ?? '') }}</div>
      @else
        <div class="muted">{{ $entityLabel }} reference unavailable.</div>
      @endif
      @if($society)
        <div class="row-line"><span>Society:</span> <b>{{ $society->name }}</b></div>
      @endif
      @if($ownership)
        <div class="row-line"><span>Ownership Period:</span>
          <b>{{ $ownership->started_at?->format('d M Y') }} &rarr; {{ $ownership->ended_at?->format('d M Y') ?? 'ongoing' }}</b>
        </div>
      @endif
    </td>
    <td class="right">
      <div class="sec-title">Transfer Details:</div>
      <div class="row-line"><span>Outgoing Owner:</span>
        <b>{{ $fromOwner?->full_name ?? '—' }}</b>
        @if($fromOwner?->cnic_passport)
          <span class="muted">(CNIC/Passport: {{ $fromOwner->cnic_passport }})</span>
        @endif
      </div>
      <div class="row-line"><span>Incoming Owner:</span>
        <b>{{ $recipient?->full_name ?? 'Going to Society'}}</b>
        @if($recipient?->cnic_passport)
          <span class="muted">(CNIC/Passport: {{ $recipient->cnic_passport }})</span>
        @endif
      </div>
      <div class="row-line"><span>Requested By:</span>
        <b>{{ $transfer->requestedBy?->full_name ?? '—' }}</b>
        @if($transfer->requestedBy?->contact)
          <span class="muted">({{ $transfer->requestedBy->contact }})</span>
        @endif
      </div>
    </td>
  </tr>
</table>

{{-- PLAN SUMMARY --}}
<div class="sec-title">Payment Plan Summary</div>
<table class="items" style="margin-bottom: 18px;">
  <thead>
  <tr>
    <th>DESCRIPTION</th>
    <th class="amount" style="width: 22%;">AMOUNT</th>
    <th style="width: 22%;">STATUS</th>
  </tr>
  </thead>
  <tbody>
  <tr>
    <td class="cname">Fee Base</td>
    <td class="amount">{{ number_format($feeBase, 2) }} {{ $currency }}</td>
    <td>—</td>
  </tr>
  <tr>
    <td class="cname">
      Society Transfer Fee
      @if($isPercentFee)
        <span class="muted">({{ $feeRate }}% of the fee base)</span>
      @endif
    </td>
    <td class="amount">{{ number_format($feeAmount, 2) }} {{ $currency }}</td>
    <td>—</td>
  </tr>
  <tr>
    <td class="cname"><b>Total Transfer Fee</b></td>
    <td class="amount"><b>{{ number_format($feeAmount, 2) }} {{ $currency }}</b></td>
    <td>—</td>
  </tr>
  <tr>
    <td class="cname">Outgoing owner's committed plan</td>
    <td class="amount">{{ number_format($outgoingPlanPrice, 2) }} {{ $currency }}</td>
    <td>{{ ucfirst(str_replace('_', ' ', (string) $outgoingPlanStatus)) }}</td>
  </tr>
  @if($newPlan)
    <tr>
      <td class="cname">Incoming owner's transfer-fee plan</td>
      <td class="amount">{{ number_format($newPlanPrice, 2) }} {{ $currency }}</td>
      <td>{{ ucfirst(str_replace('_', ' ', (string) $newPlanStatus)) }}</td>
    </tr>
    <tr>
      <td class="cname muted">Transfer fee received</td>
      <td class="amount muted">{{ number_format($newPlanPaid, 2) }} {{ $currency }}</td>
      <td class="muted">
        {{ $newPlanPaid >= $newPlanPrice && $newPlanPrice > 0 ? 'Paid' : 'Outstanding' }}
      </td>
    </tr>
  @endif
  </tbody>
</table>

{{-- APPROVAL --}}
<div class="sec-title">Approval</div>
<table class="items">
  <tbody>
  <tr>
    <td class="cname" style="width: 30%;">Requested</td>
    <td>{{ $transfer->created_at?->format('d M Y H:i') }} by {{ $transfer->requestedBy?->full_name ?? '—' }}</td>
  </tr>
  @if($transfer->acted_at)
    <tr>
      <td class="cname">{{ $transfer->status === 'approved' ? 'Approved' : 'Rejected' }}</td>
      <td>{{ $transfer->acted_at->format('d M Y H:i') }} by {{ $transfer->actedBy?->full_name ?? '—' }}</td>
    </tr>
  @endif
  @if($transfer->remarks)
    <tr>
      <td class="cname">Remarks</td>
      <td>{{ $transfer->remarks }}</td>
    </tr>
  @endif
  </tbody>
</table>

{{-- FOOTER --}}
<div class="footer-line"></div>
<p class="note">
  This slip records the transfer of the above {{ strtolower($entityLabel) }} from the outgoing owner to the
  incoming owner. The society transfer fee shown above is the fee frozen on the transfer request; it is
  payable by the incoming owner against their own transfer-fee plan.
  @if($transfer->status === 'approved')
    Ownership of the {{ strtolower($entityLabel) }} has been transferred with effect from the approval date.
  @elseif($transfer->status === 'rejected')
    The transfer was rejected and the ownership of the {{ strtolower($entityLabel) }} remains unchanged.
  @else
    The transfer is pending the society owner's approval — the ownership is unchanged until then.
  @endif
  This is a computer generated document.
</p>

@if($autoPrint ?? false)
  <div class="muted" data-receipt-auto-print="1" data-auto-print="1"></div>
  <script>
    window.addEventListener('load', function() {
      // Inside the app's hidden print frame the parent triggers printing.
      if (window.self === window.top) window.print();
    });
  </script>
@endif

</body>
</html>
