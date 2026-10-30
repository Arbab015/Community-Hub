@props([
    'summary',
    'isUnit' => false,
    'entityLabel' => null,
    'listingUnit' => null,
])

@php
  $listingPrice = $summary['listingPrice'] ?? 0;
  $unit = $listingUnit ?? ($summary['listingUnit'] ?? 'PKR');
  $premiumCharges = collect($summary['premiumCharges'] ?? []);
  $premiumsTotal = $summary['premiumsTotal'] ?? 0;
  $grossAmount = $summary['grossAmount'] ?? 0;
  $excludedCharges = collect($summary['excludedCharges'] ?? []);
  $includedCharges = collect($summary['includedCharges'] ?? []);
  $excludedTotal = $summary['excludedTotal'] ?? 0;
  $includedTotal = $summary['includedTotal'] ?? 0;
  $totalOneTime = $summary['totalOneTime'] ?? ($excludedTotal + $includedTotal);
  $remainingAmount = $summary['remainingAmount'] ?? ($grossAmount - $includedTotal);
  $hasDefaultPlan = $summary['hasDefaultPlan'] ?? false;
  $extraInstallment = $summary['extraInstallment'] ?? ($summary['add_amount'] ?? 0);
  $balloonAmount = $summary['balloonAmount'] ?? ($summary['balloon_amount'] ?? 0);
  $installmentRemaining = $summary['installmentRemaining'] ?? ($summary['installment_remaining'] ?? $remainingAmount);
  $perBalloon = $summary['perBalloon'] ?? ($summary['per_balloon'] ?? 0);
  $perInstallment = $summary['perInstallment'] ?? ($summary['per_installment'] ?? 0);
  $installmentCount = $summary['installmentCount'] ?? ($summary['number_of_installments'] ?? null);
  $total_for_extra = $remainingAmount + $extraInstallment;
  $finalAmount = $summary['finalAmount'] ?? ($totalOneTime + ($hasDefaultPlan ? $total_for_extra : $remainingAmount));
  $transferFee = $summary['transferFee'] ?? null;
  $isTransferPlan = (bool) ($summary['isTransferPlan'] ?? false);
@endphp

@pushOnce('styles', 'plan-structure-summary-styles')
  <style>
    .summary-sticky {
      top: 1.5rem;
    }

    .var-badge {
      font-size: 0.7rem;
      display: inline-block;
      background: rgba(255, 255, 255, 0.12);
      color: #ffff;
      font-weight: 700;
      font-variant-numeric: tabular-nums;
      padding: 1px 3px;
      border-radius: 6px;
      white-space: nowrap;
    }

    @media (min-width: 992px) {
      .summary-sticky {
        position: sticky;
      }
    }

    .tracking-wide {
      letter-spacing: 0.05em;
    }

    .plan-summary-card {
      font-size: 0.95rem;
    }

    .plan-summary-title {
      font-size: 0.82rem;
      letter-spacing: 0.05em;
      opacity: 0.85;
    }

    .summary-list {
      font-size: 0.78rem;
      opacity: 0.9;
    }

    .summary-final-row {
      background: rgba(255, 255, 255, 0.12);
      border-radius: 0.35rem;
      padding: 0.35rem 0.5rem;
    }

    .bg-gradient {
      background: linear-gradient(160deg, #7367f0 0%, #4f4f82 45%, #7367f0 100%) !important;
      box-shadow: 0 8px 24px rgba(13, 110, 253, 0.2);
    }
  </style>
@endPushOnce

<div class="col-lg-4 order-1 order-lg-2">
  <div class="summary-sticky">
    <div class="card border-0 shadow-sm rounded-3 p-4 mb-3 bg-light">
      <p class="text-uppercase small text-dark mb-2 fw-bold tracking-wide">Listed Value</p>
      <div class="d-flex align-items-baseline gap-2">
        <h3 class="fw-bold mb-0">{{ number_format($listingPrice, 0) }}</h3>
        <span class="text-muted fs-6">{{ $unit }}</span>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 p-4 bg-gradient text-white plan-summary-card">
      <p class="text-uppercase small mb-3 fw-bold plan-summary-title">Plan Summary</p>

      <div class="d-flex justify-content-between mb-2">
        <span class="small">Listed Price</span>
        <span class="fw-semibold fs-6" id="summary_listed">{{ number_format($listingPrice, 0) }}</span>
      </div>

      @if($transferFee)
        <div class="d-flex justify-content-between fw-semibold small mb-1" id="summary_transfer_fee">
          <span class="small">{{ $transferFee['label'] ?? 'Transfer Fee' }}</span>
          <span>{{ number_format($transferFee['amount'] ?? 0, 0) }}</span>
        </div>
        @if(($transferFee['percent'] ?? null) !== null)
          <div class="text-end small opacity-75 mb-1" id="summary_transfer_fee_percent">
            {{ rtrim(rtrim(number_format((float) $transferFee['percent'], 2, '.', ''), '0'), '.') }}% of listed price
          </div>
        @endif
      @endif

      @if($premiumsTotal > 0)
        <span class="small d-block">Feature Premiums <span class="opacity-75">(% of listed)</span></span>
        <ul class="list-unstyled mb-1 ps-0 summary-list">
          @foreach($premiumCharges as $pc)
            <li class="d-flex justify-content-between">
              <span>{{ $pc['label'] ?? ($pc['name'] ?? '') }}</span>
              <span>{{ number_format(($pc['amount'] ?? 0), 0) }}</span>
            </li>
          @endforeach
        </ul>
        <div class="d-flex justify-content-between fw-semibold small mb-1" id="summary_premiums">
          <span>Total Premiums</span>
          <span>{{ number_format($premiumsTotal, 0) }}</span>
        </div>
      @endif

      <div class="d-flex justify-content-between fw-semibold small">
        <span class="small">Gross Amount <span class="var-badge">(G.A)</span> </span>
        <span id="summary_gross"
              class="mb-2 ">{{ number_format($grossAmount, 0) }}</span>
      </div>

      <hr class="opacity-25">

      <span class="small d-block mb-1">One Time Charges <span class="var-badge">(O.T)</span></span>

      <div class="ps-2 border-start border-2 opacity-90 mb-2">
        <span class="small d-block mb-1 mt-2">Included Charges <span class="opacity-75">(In Gross)</span></span>
        <ul class="list-unstyled mb-0 ps-0 summary-list">
          @forelse($includedCharges as $c)
            <li class="d-flex justify-content-between">
              <span>{{ $c['label'] ?? '' }}</span>
              <span>{{ number_format(($c['amount'] ?? 0), 0) }}</span>
            </li>
          @empty
            <li class="opacity-50">None</li>
          @endforelse
        </ul>
        <div class="d-flex justify-content-between fw-semibold small mt-1 mb-1" id="summary_included">
          <span>Total Included  <span class="var-badge">(T.I) </span></span>
          <span>{{ number_format($includedTotal, 0) }}</span>
        </div>
        <span class="small d-block mb-1">Excluded Charges <span
            class="opacity-75">(Extra / On top of Gross)</span></span>
        <ul class="list-unstyled mb-0 ps-0 summary-list">
          @forelse($excludedCharges as $c)
            <li class="d-flex justify-content-between">
              <span>{{ $c['label'] ?? '' }}</span>
              <span>{{ number_format(($c['amount'] ?? 0), 0) }}</span>
            </li>
          @empty
            <li class="opacity-50">None</li>
          @endforelse
        </ul>
        <div class="d-flex justify-content-between fw-semibold small mt-1" id="summary_excluded">
          <span>Total Excluded <span class="var-badge">(T.E)  </span></span>
          <span>{{ number_format($excludedTotal, 0) }}</span>
        </div>
      </div>

      <div class="d-flex justify-content-between fw-semibold small">
        <span>Total One-time  </span>
        <span id="summary_one_time"
              class="bg-label-secondary px-2 rounded text-dark">{{ number_format($totalOneTime, 0) }}</span>
      </div>

      <div class="d-flex justify-content-between fw-bold small mt-2">
        <span class="small">Remaining Amount <span class="var-badge">(R.A = G.A - T.I)  </span></span>
        <span id="summary_remaining"
              @if(!$hasDefaultPlan) class="bg-label-secondary px-2 rounded text-dark" @endif>{{ number_format($remainingAmount, 0) }}</span>
      </div>

      <hr class="opacity-25">

      @if($hasDefaultPlan)
        <div class="d-flex justify-content-between">
          <span class="small">Extra in Case of Installment  <span class="var-badge">(E)</span> <span class="opacity-75"> (From Gross) </span> </span>
          <span class="fw-semibold fs-6 "
                id="summary_extra">{{ number_format($extraInstallment, 0) }}</span>
        </div>

        <div class="d-flex justify-content-between">
          <span class="small">Total for Installments <span class="var-badge">(T.F.I = R.A + E)  </span> </span>
          <span
            class="fw-semibold fs-6 px-2 rounded bg-label-secondary rounded text-dark">{{ number_format($total_for_extra, 0) }}</span>
        </div>


        <div class="d-flex justify-content-between">
          <span class="small">Total Balloon <span class="var-badge">(T.B)  </span> <span
              class="opacity-75"> (From T.F.I) </span></span>
          <span class="fw-semibold fs-6" id="summary_total_balloon">{{ number_format($balloonAmount, 0) }}</span>
        </div>

        <div class="d-flex justify-content-between mb-2">
          <span class="small">Remaining for Installment <span class="var-badge">(T.F.I - T.B)  </span></span>
          <span class="fw-semibold fs-6"
                id="summary_installment_remaining">{{ number_format($installmentRemaining, 0) }}</span>
        </div>
      @else
        <div class="small d-flex align-items-center gap-1 mb-2 opacity-75">
          <i class="ti tabler-info-circle"></i>
          @if($isTransferPlan)
            This is a transfer plan - the society transfer fee is the only charge of this plan.
          @else
            No installment plan attached - showing the payment plan only.
          @endif
        </div>
      @endif

      <div class="d-flex justify-content-between fs-5 fw-bold mb-2 summary-final-row">
        <span>Final Amount </span>
        <span id="summary_final_amount"
              class="bg-label-secondary px-2 rounded text-dark">{{ number_format($finalAmount, 0) }}</span>
      </div>

      @if($hasDefaultPlan)
        <div class="small d-block opacity-75" id="summary_per_installment">
          @if($perBalloon > 0)
            Per balloon: {{ number_format($perBalloon, 2) }} {{ $unit }}
            · Per installment: {{ number_format($perInstallment, 2) }} {{ $unit }}
          @else
            Per installment: {{ number_format($perInstallment, 2) }} {{ $unit }}
          @endif
          @if($installmentCount)
            ({{ $installmentCount }} installments)
          @endif
        </div>
      @endif
    </div>
  </div>
</div>
