@php
  $currency = $reversal->currency ?: 'PKR';
  $returnAmount = (float) $reversal->return_amount;
  $returnTax = (float) $reversal->return_tax;
  $returnTotal = (float) $reversal->return_total;
  $commissionAmount = (float) $reversal->commission_amount;
  $commissionBase = (float) $reversal->commission_base;
  $commissionValue = (float) $reversal->commission_value;
  $commissionType = $reversal->commission_type;
  $isUnit = $ownerable instanceof \App\Models\PropertyUnit;
  $entityLabel = $isUnit ? 'Unit' : 'Property';

  // Amount returned to owner = paid total (charges + tax) - reduction.
  // It is negative when the reduction is more than what was paid (e.g. nothing paid).
  $netReturn = round($returnTotal - $commissionAmount, 2);
  $netReturnText = ($netReturn < 0 ? '- ' : '').number_format(abs($netReturn), 2);
  $owner = $plan?->propertyOwner?->owner;

  $planData = [];
  if ($plan) {
      try {
          $planData = \App\Support\PaymentPlanPresenter::buildFromSnapshot($plan);
      } catch (\Throwable $e) {
          $planData = [];
      }
  }

  $logoSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="22" viewBox="0 0 32 22" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z" fill="#7367f0"/><path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616"/><path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616"/><path fill-rule="evenodd" clip-rule="evenodd" d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z" fill="#7367f0"/></svg>';
  $logoSrc = 'data:image/svg+xml;base64,'.base64_encode($logoSvg);
@endphp
  <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Ownership Cancellation Receipt</title>
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

    table.totals tr.grand.negative td.value {
      color: #b00000;
    }

    table.totals tr.commission td.value {
      color: #b00000;
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
      <div class="doc-title">OWNERSHIP CANCELLATION - RETURN &amp; COMMISSION RECEIPT</div>
      <div class="field"><span>Date:</span>
        <b>{{ ($reversal->reversed_at ?? $reversal->created_at)?->format('M d, Y') }}</b></div>
    </td>
  </tr>
</table>

{{-- DETAILS --}}
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
      <div class="row-line"><span>Owner (returned to):</span>
        <b>{{ $owner?->full_name }}</b>

      </div>

      @if($owner?->cnic_passport)
        <div class="row-line"> CNIC/Passport:
          <b>{{ $owner->cnic_passport }} </b>
        </div>
      @endif
    </td>
    <td class="right">
      <div class="sec-title">Settlement Details:</div>
      <div class="row-line"><span>Cancelled By:</span> <b>{{ $reversal->reversedBy?->full_name ?? '—' }}</b></div>
      <div class="row-line"><span>Payments Returned:</span> <b>{{ $reversal->payment_count }}</b></div>
    </td>
  </tr>
</table>

{{-- RETURNED AMOUNT --}}
<div class="sec-title">Amount Returned to Owner</div>
<table class="items">
  <thead>
  <tr>
    <th style="width: 6%;">#</th>
    <th>DESCRIPTION</th>
    <th class="amount" style="width: 22%;">AMOUNT</th>
  </tr>
  </thead>
  <tbody>
  <tr>
    <td>1</td>
    <td class="cname">Paid charges returned</td>
    <td class="amount">{{ number_format($returnAmount, 2) }}</td>
  </tr>
  <tr>
    <td>2</td>
    <td class="cname">FBR tax returned</td>
    <td class="amount">{{ number_format($returnTax, 2) }}</td>
  </tr>
  </tbody>
</table>

{{-- TOTALS --}}
<table class="totals">
  <tbody>
  <tr>
    <td class="label">Amount Returned</td>
    <td class="value">{{ number_format($returnAmount, 2) }} {{ $currency }}</td>
  </tr>
  <tr>
    <td class="label">Tax Returned</td>
    <td class="value">{{ number_format($returnTax, 2) }} {{ $currency }}</td>
  </tr>
  @if($commissionAmount > 0)
    <tr class="commission">
      <td class="label">
        Commission / Reduction
        @if($commissionType === 'percentage')
          <span>({{ rtrim(rtrim(number_format($commissionValue, 2, '.', ''), '0'), '.') }}% of {{ number_format($commissionBase, 2) }})</span>
        @endif
      </td>
      <td class="value">- {{ number_format($commissionAmount, 2) }} {{ $currency }}</td>
    </tr>
  @endif
  <tr class="grand {{ $netReturn < 0 ? 'negative' : '' }}">
    <td>Total Returned to Owner</td>
    <td class="value">{{ $netReturnText }} {{ $currency }}</td>
  </tr>
  </tbody>
</table>

{{-- FOOTER --}}
<div class="footer-line"></div>
<p class="note">
  Ownership reservation has been cancelled. The amount and FBR tax the owner had paid against this
  property have been returned, and the property is relisted for sale.
  @if($commissionAmount > 0)
    A commission / reduction of <b>{{ number_format($commissionAmount, 2) }} {{ $currency }}</b>
    has been applied on the property.
  @endif
  The owner's payment records are retained for the society's history and are marked as returned.
</p>
@if($reversal->remarks)
  <p class="note"><b>Remarks:</b> {{ $reversal->remarks }}</p>
@endif

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
