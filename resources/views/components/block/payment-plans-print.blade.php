@extends('layouts/layoutMaster')

@section('title', 'Block Payment Plan (Brief)')

@section('page-style')
  @vite(['resources/assets/css/print-plan.css'])
  <style>
    .plan-schedules-container {
      display: block;
    }

    /* Every plan page is its own complete sheet: it always starts at the
       top of a fresh page, never splits, keeps the 8mm top margin, and
       its card border stays closed. */
    .invoice-print .payment-plan-sheet {
      page-break-before: always;
      break-before: page;
      page-break-inside: avoid;
      break-inside: avoid;
      padding-top: 8mm !important;
      margin-top: 0 !important;
    }

    .invoice-print .brief-card {
      page-break-inside: avoid;
    }

    .invoice-print .col-md-4 {
      page-break-inside: auto;
    }

    .invoice-print .plan-chunk-table thead {
      display: table-header-group;
    }

    .invoice-print .plan-chunk-table tr {
      page-break-inside: avoid;
    }

    .invoice-print .brief-card-header {
      page-break-after: avoid;
    }

    .brief-card-total {
      page-break-before: avoid;
    }
  </style>
@endsection

@section('content')

  <div class="invoice-print">
    @php
      $allFeeRows = collect($data['fee_rows'])
          ->reject(fn($r) => ($r['label'] ?? '') === 'Listed Price')
          ->values();

      $additionalRows = $allFeeRows->where('note', 'Excluded')->values()->all();
      $onetimeRows = $allFeeRows->where('note', 'Included')->values()->all();

      $premiumRows = $data['premium_rows'] ?? [];
      $additionalTotal = $data['excluded_total'];
      $onetimeTotal = $data['included_total'];
      $grossAmount = $data['gross_amount'];
      $totalOneTime = $additionalTotal + $onetimeTotal;
      $remainingAmount = $grossAmount - $onetimeTotal;
      $finalAmount = $grossAmount + $additionalTotal;

      $installments = collect($data['installment_rows']);
      $totalBalloon = $installments->sum('balloon');
      $totalInstallment = $installments->sum(fn($ir) => $ir['per_installment'] * $ir['normal_count']);

      $sizeUnitLabel = $data['price_unit'] === 'square_yard' ? 'Sq. Yard' : ucfirst($data['price_unit']);
    @endphp

    {{-- SUMMARY SECTION (ONLY ON FIRST PAGE) --}}
    <div class="print-summary-block">
      {{-- HEADER --}}
      <div
        class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2">
        <div class="d-flex align-items-center gap-2">
        <span
          class="app-brand-logo demo text-primary">@include('_partials.macros', ['height' => 18, 'width' => 32])</span>
          <span class="app-brand-text fw-bold fs-4 ms-50">{{ config('variables.templateName') }}</span>
        </div>
        <div class="text-sm-end">
          <h5 class="mb-2">BLOCK-LEVEL PAYMENT PLAN — {{ strtoupper($data['block_name']) }}</h5>
          <p class="mb-0 text-heading"><span>Date Issued:</span>
            <span class="fw-medium">{{ now()->format('M d, Y') }}</span></p>
        </div>
      </div>

      {{-- BLOCK DETAILS --}}
      <div class="mb-4">
        <h6 class="mb-1 fw-bold">Block Details:</h6>
        <p class="mb-0 fw-semibold text-heading">{{ ucwords($data['block_name']) }}</p>
        <p class="mb-0">Block No: #{{ ucfirst($data['block_name']) }}</p>
        <p class="mb-0">{{ ucwords($data['block_address']) }}</p>
        <p class="mb-0">
          Category:
          <span class="badge {{ $data['is_commercial'] ? 'bg-label-success' : 'bg-label-primary' }}">
            {{ $data['is_commercial'] ? 'Commercial' : 'Non-Commercial' }}
          </span>
          <span class="badge {{ $data['is_constructed'] ? 'bg-label-dark' : 'bg-label-info' }}">
            {{ $data['is_constructed'] ? 'Constructed' : 'Non-Constructed' }}
          </span>
          @if($blockPlan->min_size !== null || $blockPlan->max_size !== null)
            <span class="badge bg-label-secondary">
              {{ $blockPlan->min_size ?? 0 }}–{{ $blockPlan->max_size ?? '∞' }} {{ ucfirst($blockPlan->size_unit) }}
            </span>
          @endif
          @if($data['payment_template_name'])
            <span class="badge bg-label-warning">{{ $data['payment_template_name'] }}</span>
          @endif
        </p>
        <p class="mb-0 fw-semibold text-heading">
          Property Size: {{ $size }} {{ $sizeUnitLabel }}
          <span class="text-muted fw-normal">(Rate: {{ number_format($data['rate'], 2) }} {{ $data['listing_unit'] }} / {{ $sizeUnitLabel }})</span>
        </p>
        @if($printedByName || $printedByRole)
          <p class="mb-0 text-muted ">
            <span class="text-heading">Prepared By:</span>
            {{ $printedByName }}
            @if($printedByName && $printedByRole)
              ({{ $printedByRole }})
            @elseif(! $printedByName && $printedByRole)
              {{ $printedByRole }}
            @endif
          </p>
        @endif
      </div>

      {{-- LISTED PRICE --}}
      <div class="d-flex justify-content-between align-items-center pb-1 mb-1 mt-2">
        <span class="fw-semibold text-heading">Listed Price ({{ $size }} {{ $sizeUnitLabel }})</span>
        <span
          class="fw-semibold bg-label-primary p-1 rounded-2">{{ number_format($data['listing_price'], 2) }} {{ $data['listing_unit'] }}</span>
      </div>

      {{-- FEATURE PREMIUMS --}}
      @if(count($premiumRows))
        <div class="table-responsive border rounded mb-2">
          <table class="table m-0">
            <thead>
            <tr>
              <th>Feature Premiums</th>
              <th>Description</th>
              <th>Rate / Value</th>
              <th class="text-end">Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($premiumRows as $row)
              <tr>
                <td class="text-nowrap text-heading">{{ $row['label'] }}</td>
                <td class="text-nowrap">Premium <span class="text-muted">({{ $row['detail'] }})</span></td>
                <td class="text-nowrap">{{ $row['detail'] }}</td>
                <td class="text-end fw-semibold">
                  <span>{{ number_format($row['amount'], 2) }} {{ $data['listing_unit'] }}</span>
                </td>
              </tr>
            @endforeach
            <tr class="table-light fw-bold">
              <td colspan="3" class="text-heading">Total Feature Premiums</td>
              <td
                class="text-end text-heading">{{ number_format($data['premiums_total'], 2) }} {{ $data['listing_unit'] }}</td>
            </tr>
            </tbody>
          </table>
        </div>
      @endif

      {{-- GROSS AMOUNT --}}
      <div
        class="amount-summary d-flex justify-content-between align-items-center border rounded-3 bg-light px-3 py-1 mb-2">
        <span class="fw-bold text-heading">Gross Amount</span>
        <span
          class="fw-bold  text-dark">{{ number_format($grossAmount, 2) }} {{ $data['listing_unit'] }}</span>
      </div>

      {{-- ONE-TIME CHARGES: EXCLUDED + INCLUDED --}}
      @if(count($additionalRows) || count($onetimeRows))
        <div class="mb-2 one-time-charges">
          <h6 class="fw-semibold mb-1">One Time Charges</h6>

          @if(count($additionalRows))
            {{-- EXCLUDED CHARGES --}}
            <span class="fw-semibold text-heading d-block mb-1">Excluded Charges <span class="text-muted fw-normal">(extra / billed on top of plan)</span></span>
            <div class="table-responsive border rounded mb-2">
              <table class="table m-0">
                <thead>
                <tr>
                  <th>Charge</th>
                  <th>Description</th>
                  <th>Rate / Value</th>
                  <th class="text-end">Amount</th>
                </tr>
                </thead>
                <tbody>
                @foreach($additionalRows as $row)
                  <tr>
                    <td class="text-nowrap text-heading">{{ $row['label'] }}</td>
                    <td class="text-nowrap">Excluded <span class="text-muted">({{ $row['detail'] }})</span></td>
                    <td class="text-nowrap">{{ $row['detail'] }}</td>
                    <td class="text-end fw-semibold">
                      <span>{{ number_format($row['amount'], 2) }} {{ $data['listing_unit'] }}</span>
                    </td>
                  </tr>
                @endforeach
                <tr class="table-light fw-bold">
                  <td colspan="3" class="text-heading">Total Excluded Charges</td>
                  <td
                    class="text-end text-heading">{{ number_format($additionalTotal, 2) }} {{ $data['listing_unit'] }}</td>
                </tr>
                </tbody>
              </table>
            </div>
          @endif

          {{-- INCLUDED CHARGES --}}
          <span class="fw-semibold text-heading d-block mb-1">Included Charges <span class="text-muted fw-normal">(in gross)</span></span>
          <div class="table-responsive border rounded">
            <table class="table m-0">
              <thead>
              <tr>
                <th>Charge</th>
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
                  <td
                    class="text-end fw-semibold">{{ number_format($row['amount'], 2) }} {{ $data['listing_unit'] }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center text-muted py-3">No included charges.</td>
                </tr>
              @endforelse
              @if(count($onetimeRows) > 0)
                <tr class="table-light fw-bold">
                  <td colspan="3" class="text-heading">Total Included Charges</td>
                  <td
                    class="text-end text-heading">{{ number_format($onetimeTotal, 2) }} {{ $data['listing_unit'] }}</td>
                </tr>
              @endif
              </tbody>
            </table>
          </div>
        </div>
      @endif

      {{-- REMAINING & FINAL AMOUNTS --}}
      <div class="amount-summary border rounded-3 bg-light px-3 py-1 mb-3" style="page-break-after: avoid;">
        <span class="d-flex justify-content-between align-items-center ">
          <span class="fw-bold text-heading">Remaining Amount</span>
          <span class="fw-bold text-dark">{{ number_format($remainingAmount, 2) }} {{ $data['listing_unit'] }}</span>
        </span>
        <span class="d-flex justify-content-between align-items-center ">
          <span class="fw-bold text-heading">Final Amount (Without Installments)</span>
          <span class="fw-bold text-dark">{{ number_format($finalAmount, 2) }} {{ $data['listing_unit'] }}</span>
        </span>
      </div>

      {{-- INSTALLMENT DETAILS HEADING --}}
      @if(count($data['installment_rows']))
        <h6 class="mb-3 fw-semibold installment-heading" style="page-break-after: avoid;">Installment Details</h6>
      @endif
    </div>

    {{-- INSTALLMENT PLANS --}}
    @if(count($data['installment_rows']))
      <div class="plan-schedules-container">
        @php
          // Same ordering as the property/unit payment plan print: the plans
          // with the most installments come first, shorter plans follow after.
          $sortedPlans = collect($data['installment_rows'])
              ->sortByDesc(fn($plan) => count($plan['schedule']))
              ->values()
              ->all();
        @endphp
        @foreach($sortedPlans as $plan)
          @php
            // Build the 3-column schedule: split the schedule into 3 equal chunks
            $schedule = collect($plan['schedule']);
            $chunkSize = max(1, (int) ceil($schedule->count() / 3));
            $chunks = $schedule->chunk($chunkSize);
          @endphp

          <div class="brief-card border rounded-3 overflow-hidden mb-4">
            <div
              class="brief-card-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary bg-opacity-10">
              <span class="fw-semibold text-primary">
                <i class="ti tabler-calendar-stats me-1"></i>{{ ucwords($plan['name']) }}
              </span>
              <span class="badge bg-label-primary flex-shrink-0">
                {{ $plan['num'] }} {{ $plan['num'] > 1 ? 'Installments' : 'Installment' }}
              </span>
            </div>

            {{-- PLAN INFO --}}
            <div class="brief-card-subtitle text-muted px-3 pt-2 mb-0">
              <span>
                {{ $plan['num'] }} installments ({{ $plan['frequency'] }})
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
            </div>

            {{-- PLAN SUB-ITEMS --}}
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

            {{-- SCHEDULE: 3 columns, flows across pages, header repeats --}}
            <div class="px-3 py-2">
              <div class="row g-2">
                @foreach($chunks as $chunk)
                  <div class="col-md-4">
                    <table class="table table-sm mb-0 plan-chunk-table">
                      {{-- The column headings live in <thead> so they
                           repeat at the top of every page the column spans. --}}
                      <thead>
                      <tr class="table-light">
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
                          <td class="align-middle">
                            @if($inst['type'] === 'balloon')
                              Balloon {{ $inst['balloon_no'] }}
                            @else
                              Installment {{ $inst['inst_no'] }}
                            @endif
                          </td>
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

            {{-- Total Payable (at the end of the whole schedule) --}}
            <div class="brief-card-total">
              <span class="label">Final Amount (With Installments)</span>
              <span
                class="value">{{ number_format($data['gross_amount'] + $plan['additional_amount'] + $data['excluded_total'], 2) }} {{ $data['listing_unit'] }}</span>
            </div>
          </div>
        @endforeach
      </div>
    @endif

    {{-- OTHER FEATURE PREMIUMS NOTE (before the closing footer) --}}
    @php
      $notAppliedPremiums = collect($data['premium_options'] ?? [])
          ->where('selected', false)
          ->values()
          ->all();
    @endphp

    @if(count($notAppliedPremiums))
      <div class="mt-3 pt-2 border-top">
        <p class="mb-1 fw-semibold text-heading">Other Feature Premiums (not included in this plan):</p>
        @foreach($notAppliedPremiums as $prem)
          <p class="text-heading mb-0">{{ $prem['label'] }} — {{ $prem['percent'] }}% of listed price
            ({{ number_format($prem['amount'], 2) }} {{ $data['listing_unit'] }})</p>
        @endforeach
        <p class="text-muted mt-1 mb-0">Including these premiums will increase the Gross Amount along with all other
          charges @if(count($data['installment_rows']))
            and installments of this plan
          @endif .</p>
      </div>
    @endif

    {{-- FOOTER NOTE (always shown) --}}
    <div class="mt-4 pt-2 border-top">
      <p class="mb-0">Thank you for choosing us.</p>
      <p class="mb-0 text-muted"><span class="fw-medium text-heading">Note:</span> This is a system generated payment
        plan based on the listed price and selected charges. It is subject to change until confirmed.</p>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    window.addEventListener('load', function() {
      // Inside the app's hidden print frame the parent triggers printing.
      if (window.self === window.top) window.print();
    });
  </script>
@endpush
