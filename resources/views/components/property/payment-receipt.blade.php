@php
  $chargeSubtotal = round((float) collect($lines)->sum(fn ($line) => (float) ($line['amount'] ?? 0)), 2);
  $taxAmount = round((float) collect($lines)->sum(fn ($line) => (float) ($line['tax'] ?? 0)), 2);
  $lateFeeAmount = (float) $transaction->late_fee_amount;
  $lateFeesByLedger = $transaction->lateFees->groupBy('property_payment_ledger_id');
  $hasLateFees = $lateFeesByLedger->isNotEmpty();
  $taxRate = (float) $transaction->tax_rate_percent;
  $grandTotal = round($chargeSubtotal + $taxAmount + $lateFeeAmount, 2);
  $taxRateLabel = rtrim(rtrim(number_format($taxRate, 2, '.', ''), '0'), '.');

  // Full app logo - the same mark the payments report header uses - embedded
  // as a data URI because DomPDF cannot execute the layout's SVG macro.
  $logoSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="22" viewBox="0 0 32 22" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z" fill="#7367f0"/><path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616"/><path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd" d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616"/><path fill-rule="evenodd" clip-rule="evenodd" d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z" fill="#7367f0"/></svg>';
  $logoSrc = 'data:image/svg+xml;base64,'.base64_encode($logoSvg);
@endphp
  <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Payment Receipt #{{ str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT) }}</title>
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

    /* HEADER */
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

    table.head .field b {
      color: #2f2b3d;
    }

    .badge {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 3px;
      font-size: 9px;
      line-height: 1.4;
      vertical-align: baseline;
    }

    .bg-label-primary {
      background-color: #ede9ff;
      color: #7367f0;
    }

    .bg-label-primary b {
      color: #7367f0;
    }

    /* PROPERTY / PAYMENT DETAILS */
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

    table.details b {
      color: #2f2b3d;
    }

    /* CHARGES */
    table.charges {
      width: 100%;
      margin: 10px 0 4px;
    }

    table.charges th {
      font-size: 7.5px;
      text-transform: uppercase;
      text-align: left;
      color: #2f2b3d;
      padding: 4px 6px;
      border: none;
      border-bottom: 1px solid #d9dee3;
    }

    table.charges th.amount,
    table.charges td.amount {
      text-align: right;
    }

    table.charges td {
      padding: 4px 6px;
      border: none;
      border-bottom: 0.5px solid #ececec;
      vertical-align: top;
    }

    table.charges td.col-num {
      color: #4b4b4b;
    }

    table.charges td.cname {
      color: #2f2b3d;
    }

    /* TOTALS */
    table.totals {
      width: 250px;
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

    /* FOOTER */
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
      <div class="doc-title">PAYMENT RECEIPT</div>
      <div class="field"><span>Receipt No:</span>
        <span
          class="badge bg-label-primary"><b>#{{ str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT) }}</b></span>
      </div>
      <div class="field"><span>Payment Date:</span>
        <span class="badge bg-label-primary"><b>{{ $transaction->paid_at?->format('M d, Y') }}</b></span>
      </div>
    </td>
  </tr>
</table>

{{-- PROPERTY / PAYMENT DETAILS --}}
<table class="details sec">
  <tr>
    <td class="left">
      <div class="sec-title">Property Details:</div>
      @if(! empty($planData['property_name']))
        <div class="prop-name">{{ ucwords($planData['property_name']) }}</div>
        <div class="muted">Property No: #{{ ucfirst($planData['property_no'] ?? '') }}</div>
        <div class="muted">{{ ucwords($planData['property_address'] ?? '') }}</div>
      @else
        <div class="muted">Property details unavailable.</div>
      @endif
      <div class="row-line"><span>Buyer Name:</span>
        <b>{{ $owner?->full_name }}</b>
      </div>

      @if($owner?->cnic_passport)
        <div class="row-line"><span>CNIC/Passport:</span>
          <b>{{ $owner->cnic_passport }}</b>
        </div>
      @endif

      <div class="row-line"><span>Filer Status:</span>
        <b>{{ $owner?->is_filer ? 'Filer' : 'Non-Filer' }}</b>
      </div>
    </td>
    <td class="right">
      <div class="sec-title">Payment Details:</div>
      <div class="row-line"><span>Method:</span>
        <b>{{ strtoupper(str_replace('_', ' ', (string) $transaction->payment_method)) }}</b>
      </div>
      <div class="row-line"><span>Reference:</span>
        <b>{{ $transaction->reference_no ?: '—' }}</b>
      </div>
      <div class="row-line"><span>Received By:</span>
        <b>{{ $transaction->recordedBy?->full_name ?? '—' }}</b>
      </div>
      <div class="row-line"><span>Status:</span>
        <b>{{ ucfirst($transaction->status) }}</b>
      </div>
    </td>
  </tr>
</table>

{{-- CHARGES SETTLED --}}
<table class="charges">
  <thead>
  <tr>
    <th style="width: 6%;">#</th>
    <th>CHARGE</th>
    <th class="amount" style="width: 17%;">AMOUNT</th>
    <th class="amount" style="width: 15%;">FBR TAX</th>
    @if($hasLateFees)
      <th class="amount" style="width: 15%;">LATE FEE</th>
    @endif
    <th class="amount" style="width: 18%;">TOTAL AMOUNT</th>
  </tr>
  </thead>
  <tbody>
  @forelse($lines as $i => $line)
    @php
      $lineAmount = (float) ($line['amount'] ?? 0);
      $lineTax = (float) ($line['tax'] ?? 0);
      $lineLateFee = (float) $lateFeesByLedger->get($line['ledger_id'] ?? null, collect())
        ->sum(fn ($lateFee) => (float) $lateFee->fee_amount);
    @endphp
    <tr>
      <td class="col-num">{{ $i + 1 }}</td>
      <td class="cname">{{ $line['name'] ?? 'Charge' }}
        @if(! empty($line['charge_type']))
          <span class="muted">({{ ucwords(str_replace('_', ' ', (string) $line['charge_type'])) }})</span>
        @endif
      </td>
      <td class="amount">{{ number_format($lineAmount, 2) }}</td>
      <td class="amount">{{ number_format($lineTax, 2) }}</td>
      @if($hasLateFees)
        <td class="amount">{{ number_format($lineLateFee, 2) }}</td>
      @endif
      <td class="amount">{{ number_format($lineAmount + $lineTax + $lineLateFee, 2) }}</td>
    </tr>
  @empty
    <tr>
      <td colspan="{{ $hasLateFees ? 6 : 5 }}" class="text-center muted">No charges were allocated to this payment.</td>
    </tr>
  @endforelse
  </tbody>
</table>

{{-- TOTALS (right-aligned) --}}
<table class="totals">
  <tbody>
  <tr>
    <td class="label">Subtotal</td>
    <td class="value">{{ number_format($chargeSubtotal, 2) }} {{ $currency }}</td>
  </tr>
  <tr>
    <td class="label">
      FBR Tax
      @if($taxRate > 0)
        <span>({{ $taxRateLabel }}%)</span>
      @endif
    </td>
    <td class="value">{{ number_format($taxAmount, 2) }} {{ $currency }}</td>
  </tr>
  @if($lateFeeAmount > 0)
    <tr>
      <td class="label">Late Fee</td>
      <td class="value">{{ number_format($lateFeeAmount, 2) }} {{ $currency }}</td>
    </tr>
  @endif
  <tr class="grand">
    <td>Total Paid</td>
    <td class="value">{{ number_format($grandTotal, 2) }} {{ $currency }}</td>
  </tr>
  </tbody>
</table>

{{-- FOOTER --}}
<div class="footer-line"></div>
<p class="note">
  This receipt is computer generated and valid without signature. It records only the charges
  settled by this payment. Tax has been computed at the FBR rate applicable to the buyer at the
  time the payment plan was committed
  @if($taxRate > 0)
    ({{ $taxRateLabel }}%)
  @endif .
</p>

{{-- Auto-print only when the receipt was opened with ?print=1: the markers and
     the script are absent otherwise, so a plain receipt never pops a dialog. --}}
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
