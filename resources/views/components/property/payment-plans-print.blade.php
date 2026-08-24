@extends('layouts/layoutMaster')

@section('title', $mode === 'brief' ? 'Payment Plan (Brief)' : 'Payment Plan (Print version)')

@section('page-style')
  <style>
    @page {
      size: A4 portrait;
      margin: 0;
    }

    @media print {
      html,
      body {
        height: auto !important;
        overflow: visible !important;
        background: #fff !important;
      }

      .navbar,
      .layout-navbar,
      .layout-menu,
      .main-menu,
      .footer,
      .layout-footer,
      .content-footer,
      .app-content-footer,
      .print-toolbar {
        display: none !important;
      }

      .layout-wrapper,
      .layout-page,
      .layout-content-wrapper,
      .content-wrapper,
      .container-fluid {
        position: static !important;
        margin: 0 !important;
        padding: 0 !important;
        min-height: 0 !important;
      }

      body * {
        visibility: hidden;
      }

      .invoice-print,
      .invoice-print * {
        visibility: visible;
      }

      .invoice-print {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        box-sizing: border-box;
        padding: 8mm;
        font-family: Arial, sans-serif;
        font-size: 10px !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .invoice-print .table-responsive {
        overflow: visible !important;
      }

      .invoice-print .text-nowrap {
        white-space: normal !important;
      }

      .invoice-print thead {
        display: table-header-group;
      }

      /* Compact receipt sizing (old design) — small, clear, fits A4 */
      .invoice-print .table {
        width: 100% !important;
        font-size: 0.68rem;
      }

      .invoice-print .table th,
      .invoice-print .table td {
        padding: 0.28rem 0.45rem;
      }

      .invoice-print .table th {
        font-size: 0.58rem;
      }

      .invoice-print .table-sm th,
      .invoice-print .table-sm td {
        padding: 0.14rem 0.3rem;
      }

      .invoice-print .table-sm {
        font-size: 0.56rem;
      }

      /* Gross / Remaining / Final rows share the table font size */
      .invoice-print .amount-summary {
        font-size: 0.68rem;
      }

      .invoice-print h5 {
        font-size: 1rem !important;
        margin-bottom: 0.2rem !important;
      }

      .invoice-print h6 {
        font-size: 0.8rem !important;
        margin-bottom: 0.2rem !important;
      }

      .invoice-print .app-brand-text {
        font-size: 1rem !important;
      }

      .invoice-print .mb-6,
      .invoice-print .mb-4,
      .invoice-print .mb-3,
      .invoice-print .mb-2,
      .invoice-print .mb-1 {
        margin-bottom: 0.1rem !important;
      }

      .invoice-print .mt-4 {
        margin-top: 0.4rem !important;
      }

      .invoice-print .mt-5 {
        margin-top: 0.9rem !important;
      }

      .invoice-print .py-2 {
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
      }

      .invoice-print .pt-2 {
        padding-top: 0.25rem !important;
      }

      .invoice-print .fs-5 {
        font-size: 0.95rem !important;
      }

      .invoice-print .rounded,
      .invoice-print .rounded-3 {
        border-radius: 0 !important;
      }

      .invoice-print .badge {
        font-size: 0.55rem;
        padding: 0.2em 0.4em;
      }

      .invoice-print .brief-card {
        border: 1px solid #d9dee3 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
        break-inside: avoid;
      }

      .invoice-print .brief-card .px-3 {
        padding-left: 0.4rem !important;
        padding-right: 0.4rem !important;
      }

      .invoice-print .brief-card .py-2 {
        padding-top: 0.12rem !important;
        padding-bottom: 0.12rem !important;
      }

      .invoice-print .brief-card .table-sm th,
      .invoice-print .brief-card .table-sm td {
        padding: 0.08rem 0.25rem;
      }

      .invoice-print .brief-card .table {
        line-height: 1.1;
      }

      .invoice-print .brief-card .text-muted.small {
        padding-top: 0.2rem !important;
        padding-bottom: 0.03rem !important;
        line-height: 1.1;
        font-size: 0.55rem !important;
      }

      .invoice-print .brief-card .table-responsive {
        margin-top: 0rem;
      }

      /* Keep the header/property/charges block together, and keep the
         closing note on the same page instead of spilling to a new one */
      .invoice-print > div:first-of-type,
      .invoice-print .mt-4.pt-2.border-top {
        page-break-inside: avoid;
        break-inside: avoid;
      }

      /* Three plans per row so everything stays compact and on one page */
      .brief-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        align-items: stretch;
      }

      .brief-row.one {
        grid-template-columns: 1fr;
      }

      .brief-row.two {
        grid-template-columns: repeat(2, 1fr);
      }

      .invoice-print .brief-card-total {
        padding: 0.28rem 0.5rem !important;
        font-size: 0.68rem !important;
      }

      .invoice-print .plan-sub-items {
        margin-left: 0.3rem;
        padding-left: 0.5rem;
      }

      .invoice-print .plan-sub-item {
        font-size: 0.56rem !important;
        padding-left: 0.6rem;
      }

      /* Proper page handling - all pages get same top margin */
      .invoice-print {
        margin-top: 0 !important;
        padding-top: 8mm !important;
      }

      /* Table page break handling - allow tables to break across pages */
      .table-responsive {
        page-break-inside: auto;
        break-inside: auto;
      }

      .table-responsive tr {
        page-break-inside: avoid;
        break-inside: avoid;
      }
    }

    .invoice-print {
      padding: 10mm;
      box-sizing: border-box;
      font-size: 12px;
    }

    .invoice-print h5 {
      font-size: 1rem;
      margin-bottom: 0.2rem;
    }

    .invoice-print h6 {
      font-size: 0.85rem;
      margin-bottom: 0.25rem;
    }

    .invoice-print .app-brand-text {
      font-size: 1rem;
    }

    .invoice-print .mb-4,
    .invoice-print .mb-3,
    .invoice-print .mb-2,
    .invoice-print .mb-1 {
      margin-bottom: 0.35rem !important;
    }

    .invoice-print .mt-4,
    .invoice-print .mt-2,
    .invoice-print .mt-1 {
      margin-top: 0.35rem !important;
    }

    .invoice-print .table {
      font-size: 0.7rem;
    }

    .invoice-print .table th,
    .invoice-print .table td {
      padding: 0.1rem 0.5rem;
    }

    .invoice-print .table th {
      font-size: 0.6rem;
    }

    .invoice-print .table-sm th,
    .invoice-print .table-sm td {
      padding: 0.2rem 0.4rem;
    }

    .invoice-print .table-sm {
      font-size: 0.62rem;
    }

    /* Gross / Remaining / Final rows share the table font size */
    .invoice-print .amount-summary {
      font-size: 0.7rem;
    }

    .col-seq-sm {
      width: 30px;
    }

    .invoice-print .px-3 {
      padding-left: 0.5rem !important;
      padding-right: 0.5rem !important;
    }

    .invoice-print .py-2 {
      padding-top: 0.3rem !important;
      padding-bottom: 0.3rem !important;
    }

    .invoice-print .pt-2 {
      padding-top: 0.25rem !important;
    }

    .invoice-print .pb-1 {
      padding-bottom: 0.15rem !important;
    }

    .invoice-print .fs-5 {
      font-size: 0.95rem !important;
    }

    .brief-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-top: 8px;
      align-items: stretch;
    }

    .brief-row.one {
      grid-template-columns: 1fr;
    }

    .brief-row.two {
      grid-template-columns: repeat(2, 1fr);
    }

    @media (max-width: 575.98px) {
      .brief-row,
      .brief-row.one,
      .brief-row.two {
        grid-template-columns: 1fr;
      }
    }

    /* Card layout: header + subtext + schedule grow naturally, Total
       Payable is pushed to the bottom of the card with margin-top: auto
       so every card in a row ends on the same line regardless of how
       many installment rows it has. */
    .brief-card {
      display: flex;
      flex-direction: column;
      height: 100%;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
      background: #fff;
    }

    .brief-card .table-responsive {
      flex: 0 0 auto;
    }

    .brief-card-total {
      margin-top: auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      padding: 0.2rem 0.75rem;
      background-color: rgba(105, 108, 255, 0.08);
      border-top: 1px solid #d9dee3;
      font-weight: 700;
    }

    .brief-card-total .label {
      color: #566a7f;
    }

    .brief-card-total .value {
      color: #696cff;
    }

    /* Plan sub-items (Total Balloon / Total Installment lines) */
    .plan-sub-item {
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.35rem;
      padding-left: 0.5rem !important;
      font-size: 0.62rem !important;
      line-height: 1.3;
      margin-bottom: 0.08rem;
      color: #566a7f;
    }

    .plan-sub-item i {
      flex-shrink: 0;
      font-size: 0.45rem;
      margin-right: 0.35rem;
    }

    .plan-sub-item::before {
      display: none;
    }

    /* Reduce font size for subtitle and extra charges to match other small text */
    .brief-card-subtitle {
      font-size: 0.62rem !important;
      line-height: 1.3;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .brief-card-extra {
      font-size: 0.62rem !important;
      line-height: 1.3;
      margin-bottom: 0 !important;
    }

    .brief-card-extra::before {
      content: '·';
      margin: 0 0.4rem;
    }

    .brief-card-grand-total::before {
      content: '·';
      margin: 0 0.4rem;
    }

  </style>
@endsection

@section('content')

  <div class="invoice-print">
    @php
      $data = App\Support\PaymentPlanPresenter::build($property, null, null, $selectedUuids ?: null, $includeInstallments ?? true);

      $allFeeRows = collect($data['fee_rows'])
          ->reject(fn($r) => ($r['label'] ?? '') === 'Listed Price')
          ->values();

      $additionalRows = $allFeeRows->where('note', 'Excluded')->values()->all();
      $onetimeRows = $allFeeRows->where('note', 'Included')->values()->all();

      $additionalTotal = $data['excluded_total'];
      $onetimeTotal = $data['included_total'];
      $grossAmount = $data['listing_price'] + $additionalTotal;
      $remainingAmount = $grossAmount - $onetimeTotal;
      $finalAmount = $data['listing_price'] + $additionalTotal;

      $installments = collect($data['installment_rows']);
      $totalBalloon = $installments->sum('balloon');
      $totalInstallment = $installments->sum(fn($ir) => $ir['per_installment'] * $ir['normal_count']);
    @endphp

    {{-- HEADER --}}
    <div
      class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2">
      <div class="d-flex align-items-center gap-2">
        <span
          class="app-brand-logo demo text-primary">@include('_partials.macros', ['height' => 18, 'width' => 32])</span>
        <span class="app-brand-text fw-bold fs-4 ms-50">{{ config('variables.templateName') }}</span>
      </div>
      <div class="text-sm-end">
        <h5 class="mb-2">PAYMENT PLAN — {{ $data['property_ref'] }}</h5>
        <p class="mb-0 text-heading"><span>Date Issued:</span>
          <span class="fw-medium">{{ now()->format('M d, Y') }}</span></p>
      </div>
    </div>

    {{-- PROPERTY DETAILS --}}
    <div class="mb-4">
      <h6 class="mb-1 fw-bold">Property Details:</h6>
      <p class="mb-0 fw-semibold text-heading">{{ ucwords($data['property_name']) }}</p>
      <p class="mb-0">Property No: #{{ ucfirst($data['property_no']) }}</p>
      <p class="mb-0">{{ ucwords($data['property_address']) }}</p>
      @if($printedByName || $printedByRole)
        <p class="mb-0 text-muted ">
          <span class="text-heading">Prepared By:</span>
          {{ $printedByName }}@if($printedByName && $printedByRole)
            ({{ $printedByRole }})
          @elseif(! $printedByName && $printedByRole)
            {{ $printedByRole }}
          @endif
        </p>
      @endif
    </div>

    {{-- LISTED PRICE --}}
    <div class="d-flex justify-content-between align-items-center pb-1 mb-1 mt-2">
      <span class="fw-semibold text-heading">Listed Price</span>
      <span
        class="fw-semibold bg-label-primary p-1 rounded-2">{{ number_format($data['listing_price'], 2) }} {{ $data['listing_unit'] }}</span>
    </div>

    {{-- ADDITIONAL CHARGES TABLE --}}
    <div class="table-responsive border rounded mb-2">
      <table class="table m-0">
        <thead>
        <tr>
          <th>Additional Charges</th>
          <th>Description</th>
          <th>Rate / Value</th>
          <th class="text-end">Amount</th>
        </tr>
        </thead>
        <tbody>
        @forelse($additionalRows as $row)
          <tr>
            <td class="text-nowrap text-heading">{{ $row['label'] }}</td>
            <td class="text-nowrap">Excluded <span class="text-muted">({{ $row['detail'] }})</span></td>
            <td class="text-nowrap">{{ $row['detail'] }}</td>
            <td class="text-end fw-semibold ">
              <span>{{ number_format($row['amount'], 2) }} {{ $data['listing_unit'] }}</span>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="text-center text-muted py-3">No additional charges.</td>
          </tr>
        @endforelse
        @if(count($additionalRows) > 0)
          <tr class="table-light fw-bold">
            <td colspan="3" class="text-heading">Total Additional Charges</td>
            <td class="text-end text-heading">{{ number_format($additionalTotal, 2) }} {{ $data['listing_unit'] }}</td>
          </tr>
        @endif
        </tbody>
      </table>
    </div>

    {{-- GROSS AMOUNT --}}
    <div
      class="amount-summary d-flex justify-content-between align-items-center border rounded-3 bg-light px-3 py-1 mb-2">
      <span class="fw-bold text-heading">Gross Amount</span>
      <span
        class="fw-bold  text-dark">{{ number_format($grossAmount, 2) }} {{ $data['listing_unit'] }}</span>
    </div>

    {{-- ONE-TIME CHARGES TABLE --}}
    <div class="table-responsive border rounded mb-2">
      <table class="table m-0">
        <thead>
        <tr>
          <th>One Time Charges</th>
          <th>Description</th>
          <th>Rate / Value</th>
          <th class="text-end">Amount</th>
        </tr>
        </thead>
        <tbody>
        @forelse($onetimeRows as $row)
          <tr>
            <td class="text-nowrap text-heading">{{ $row['label'] }}</td>
            <td class="text-nowrap">Included <span class="text-muted">({{ $row['detail'] }})</span></td>
            <td class="text-nowrap">{{ $row['detail'] }}</td>
            <td class="text-end fw-semibold">{{ number_format($row['amount'], 2) }} {{ $data['listing_unit'] }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="text-center text-muted py-3">No one-time charges.</td>
          </tr>
        @endforelse
        @if(count($onetimeRows) > 0)
          <tr class="table-light fw-bold">
            <td colspan="3" class="text-heading">Total One-Time Charges</td>
            <td class="text-end text-heading">{{ number_format($onetimeTotal, 2) }} {{ $data['listing_unit'] }}</td>
          </tr>
        @endif
        </tbody>
      </table>
    </div>

    {{-- REMAINING & FINAL AMOUNTS --}}
    <div class="amount-summary border rounded-3 bg-light px-3 py-1">
      <span class="d-flex justify-content-between align-items-center ">
        <span class="fw-bold text-heading">Remaining Amount</span>
        <span
          class="fw-bold text-dark">{{ number_format($remainingAmount, 2) }} {{ $data['listing_unit'] }}</span>
      </span>
      <span class="d-flex justify-content-between align-items-center ">
        <span class="fw-bold text-heading">Final Amount</span>
        <span
          class="fw-bold text-dark">{{ number_format($finalAmount, 2) }} {{ $data['listing_unit'] }}</span>
      </span>
    </div>

    @if(count($data['installment_rows']))
      @if($mode === 'simple')
        {{-- SIMPLE: installment plans summary --}}
        <div class="table-responsive mt-2">
          <h6 class="mb-2 fw-semibold">Installment Plans</h6>
          <table class="table m-0 table-sm">
            <thead class="table-light">
            <tr>
              <th class="ps-3 py-2 align-middle">Plans</th>
              <th class="ps-3 py-2 align-middle">Installments</th>
              <th class="ps-3 py-2 align-middle">Per Balloon</th>
              <th class="ps-3 py-2 align-middle">Per Installment</th>
              <th class="ps-3 text-end py-2 align-middle">Extra</th>
              <th class="ps-3 text-end py-2 align-middle">Grand Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['installment_rows'] as $ir)
              <tr>
                <td class="ps-3 text-heading fw-semibold">{{ $ir['name'] }}</td>
                <td class="ps-3">{{ $ir['num'] }}@if($ir['frequency'])
                    ({{ ucfirst($ir['frequency']) }})
                  @endif</td>
                <td class="ps-3">
                  @if($ir['balloon_count'] > 0)
                    {{ $ir['balloon_count'] }} × {{ number_format($ir['per_balloon'], 2) }} {{ $data['listing_unit'] }}
                  @else
                    —
                  @endif
                </td>
                <td class="ps-3">{{ $ir['normal_count'] }}
                  × {{ number_format($ir['per_installment'], 2) }} {{ $data['listing_unit'] }}</td>
                <td
                  class="ps-3 text-end">{{ number_format($ir['additional_amount'], 2) }} {{ $data['listing_unit'] }}</td>
                <td
                  class="ps-3 fw-semibold text-end">{{ number_format($ir['grand_total'], 2) }} {{ $data['listing_unit'] }}</td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
        {{-- BALLOON / INSTALLMENT TOTALS --}}
        @if($totalBalloon > 0 || $totalInstallment > 0)
          <div class="d-flex align-items-center justify-content-center gap-3 mt-3 mb-2">
            @if($totalBalloon > 0)
              <span class="fw-semibold text-heading">
                <i class="ti tabler-arrow-badge-right me-1 text-primary"></i>
                Total Balloon: {{ number_format($totalBalloon, 2) }} {{ $data['listing_unit'] }}
              </span>
            @endif
            @if($totalInstallment > 0)
              <span class="fw-semibold text-heading">
                <i class="ti tabler-arrow-badge-right me-1 text-primary"></i>
                Total Installment: {{ number_format($totalInstallment, 2) }} {{ $data['listing_unit'] }}
              </span>
            @endif
          </div>
        @endif
      @else
        {{-- BRIEF: one plan per row, schedule split into 3 columns --}}
        <div class="mt-5">
          <h6 class="mb-2 fw-semibold">Installment Details</h6>
          @foreach($data['installment_rows'] as $plan)
            @php
              $schedule = collect($plan['schedule']);
              $chunkSize = max(1, (int) ceil($schedule->count() / 3));
              $chunks = $schedule->chunk($chunkSize);
            @endphp
            <div class="brief-card border rounded-3 overflow-hidden mb-4">
              <div
                class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary bg-opacity-10">
                <span class="fw-semibold text-primary">
                  <i class="ti tabler-calendar-stats me-1"></i>{{ $plan['name'] }}
                </span>
                <span class="badge bg-label-primary flex-shrink-0">
                  {{ $plan['num'] }} {{ $plan['num'] > 1 ? 'installments' : 'installment' }}
                </span>
              </div>

              <!-- Subtitle and extra charges on same line -->
              <div class="brief-card-subtitle text-muted px-3 pt-2 mb-0">
                <span>
                  {{ ucfirst($plan['frequency']) }} installments
                  @if($plan['balloon_count'])
                    · {{ $plan['balloon_count'] }} balloon{{ $plan['balloon_count'] > 1 ? 's' : '' }}
                    @if($plan['balloon_frequency'])
                      ({{ ucfirst($plan['balloon_frequency']) }})
                    @endif
                  @endif
                </span>

                @if($plan['additional_amount'] > 0)
                  <span class="brief-card-extra">Extra in case of Installment:
                    <span
                      class="fw-semibold">{{ number_format($plan['additional_amount'], 2) }} {{ $data['listing_unit'] }}</span>
                  </span>
                @endif
                <span class="brief-card-grand-total fw-bold px-3  ">
                  Grand Total: {{ number_format($plan['grand_total'], 2) }} {{ $data['listing_unit'] }}
                </span>
              </div>


              <!-- Plan sub-items with down-right arrow icons -->
              <div class="plan-sub-items px-3 ">
                @if($plan['balloon'] > 0)
                  <div class="plan-sub-item text-muted">
                    <i class="ti tabler-corner-down-right"></i>
                    <span>Total Balloon: {{ number_format($plan['balloon'], 2) }} {{ $data['listing_unit'] }}</span>
                  </div>
                @endif
                <div class="plan-sub-item text-muted">
                  <i class="ti tabler-corner-down-right"></i>
                  <span>Total Installment: {{ number_format($plan['per_installment'] * $plan['normal_count'], 2) }} {{ $data['listing_unit'] }}</span>
                </div>
              </div>

              {{-- Schedule split into 3 columns --}}
              <div class="px-3 py-2">
                <div class="row g-2">
                  @foreach($chunks as $chunk)
                    <div class="col-md-4">
                      <table class="table table-sm mb-0">
                        <thead class="table-light">
                        <tr>
                          <th class="ps-3 col-seq-sm">#</th>
                          <th class="align-middle">Type</th>
                          <th class="align-middle text-end">Amount</th>
                          <th class="align-middle text-end">Cumulative</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($chunk as $inst)
                          <tr class="{{ $inst['type'] === 'balloon' ? 'bg-label-primary' : '' }}">
                            <td class="ps-3">{{ $inst['no'] }}</td>
                            <td
                              class="align-middle">{{ ucfirst($inst['type']) }}{{ $inst['type'] === 'balloon' ? ' #'.$inst['balloon_no'] : '' }}</td>
                            <td class="align-middle text-end">{{ number_format($inst['amount'], 0) }}</td>
                            <td class="align-middle text-end">{{ number_format($inst['cumulative'], 0) }}</td>
                          </tr>
                        @endforeach
                        </tbody>
                      </table>
                    </div>
                  @endforeach
                </div>
              </div>

              {{-- Total Payable (pinned to bottom) --}}
              <div class="brief-card-total">
                <span class="label">Total Payable</span>
                <span class="value">{{ number_format($plan['total'], 2) }} {{ $data['listing_unit'] }}</span>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    @endif

    {{-- NOTE / FOOTER --}}
    <div class="mt-4 pt-2 border-top">
      <p class="mb-0"><span class="me-2 fw-semibold text-heading">Plan Type:</span> {{ ucwords($data['type_display']) }}
      </p>
      <p class="mb-0">Thank you for choosing us.</p>
      <p class="mb-0 text-muted"><span class="fw-medium text-heading">Note:</span> This is a system generated payment
        plan
        based on the listed price and selected charges. It is subject to change until confirmed.</p>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    window.addEventListener('load', function() {
      window.print();
    });
  </script>
@endpush
