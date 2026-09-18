@extends('layouts/layoutMaster')

@section('title', $mode === 'brief' ? 'Owner Payment Plan (Brief)' : 'Owner Payment Plan (Print version)')

@section('page-style')
  @vite(['resources/assets/css/print-plan.css'])
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
      $remainingAmount = $data['total_price'] - $onetimeTotal;

      $installments = collect($data['installment_rows']);
      $totalBalloon = $installments->sum('balloon');
      $totalInstallment = $installments->sum(fn($ir) => $ir['per_installment'] * $ir['normal_count']);

      $buyerName = $owner ? trim($owner->first_name.' '.$owner->last_name) : null;
    @endphp

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
          <h5 class="mb-2">OWNERSHIP PAYMENT PLAN — {{ $data['property_ref'] }}</h5>
          <p class="mb-0 text-heading"><span>Date Issued:</span>
            <span class="fw-medium">{{ now()->format('M d, Y') }}</span></p>
        </div>
      </div>

      {{-- PROPERTY + BUYER DETAILS --}}
      <div class="mb-4">
        <h6 class="mb-1 fw-bold">Property Details:</h6>
        <p class="mb-0 fw-semibold text-heading">{{ ucwords($data['property_name']) }}</p>
        <p class="mb-0">Property No: #{{ ucfirst($data['property_no']) }}</p>
        <p class="mb-0">{{ ucwords($data['property_address']) }}</p>

        @if($buyerName)
          <div class="border-top mt-2 pt-2">
            <h6 class="mb-1 fw-bold">Buyer / Owner:</h6>
            <p class="mb-0 fw-semibold text-heading">{{ ucwords($buyerName) }}</p>
            @if($owner?->cnic_passport)
              <p class="mb-0">CNIC: {{ $owner->cnic_passport }}</p>
            @endif
            @if($owner?->contact)
              <p class="mb-0">Mobile: {{ $owner->contact }}</p>
            @endif
          </div>
        @endif

        @if($printedByName || $printedByRole)
          <p class="mb-0 text-muted mt-1">
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
        <span class="fw-semibold text-heading">Listed Price</span>
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
          @if(count($additionalRows))
            <span class="fw-semibold text-heading d-block mb-1 mt-1">One Time Excluded Charges <span
                class="text-muted fw-normal">(on top of gross)</span></span>
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

          <span class="fw-semibold text-heading d-block mb-2 mt-1">One Time Included Charges <span
              class="text-muted fw-normal">(in gross)</span></span>
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
                  <td class="text-end fw-semibold">{{ number_format($row['amount'], 2) }} {{ $data['listing_unit'] }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center text-muted py-3">No included charges.</td>
                </tr>
              @endforelse
              @if(count($onetimeRows) > 0)
                <tr class="table-light fw-bold">
                  <td colspan="3" class="text-heading">Total Included Charges</td>
                  <td class="text-end text-heading">{{ number_format($onetimeTotal, 2) }} {{ $data['listing_unit'] }}</td>
                </tr>
              @endif
              </tbody>
            </table>
          </div>
        </div>
      @endif

      {{-- REMAINING AMOUNT --}}
      <div class="amount-summary border rounded-3 bg-light px-3 py-1">
      <span class="d-flex justify-content-between align-items-center ">
        <span class="fw-bold text-heading">Remaining Amount</span>
        <span
          class="fw-bold text-dark">{{ number_format($remainingAmount, 2) }} {{ $data['listing_unit'] }}</span>
      </span>
      </div>
    </div>

    @if(count($data['installment_rows']))
      {{-- BRIEF: one plan per row, schedule split into 3 columns --}}
      @php
        $sortedPlans = collect($data['installment_rows'])
            ->sortByDesc(fn($plan) => count($plan['schedule']))
            ->values()
            ->all();

        $pages = [];
        $currentPagePlans = [];
        $currentPageRows = 0;
        $maxRowsPerPage = 75;

        foreach ($sortedPlans as $plan) {
          $scheduleRows = count($plan['schedule']);
          if ($currentPageRows + $scheduleRows > $maxRowsPerPage && !empty($currentPagePlans)) {
            $pages[] = $currentPagePlans;
            $currentPagePlans = [];
            $currentPageRows = 0;
          }
          $currentPagePlans[] = $plan;
          $currentPageRows += $scheduleRows;
        }
        if (!empty($currentPagePlans)) {
          $pages[] = $currentPagePlans;
        }
        if (empty($pages)) {
          $pages = [[]];
        }
      @endphp

        @foreach($pages as $pageIndex => $plansOnPage)
          <div class="installment-page {{ $pageIndex === 0 ? 'first' : 'mt-5' }}">
            <h6 class="mb-2 fw-semibold installment-heading">
              {{ $pageIndex === 0 ? 'Installment Details' : 'Installment Details (Continued)' }}
            </h6>
            @foreach($plansOnPage as $plan)
              @php
                $schedule = collect($plan['schedule']);
                $chunkSize = max(1, (int) ceil($schedule->count() / 3));
                $chunks = $schedule->chunk($chunkSize);
              @endphp
              <div class="brief-card border rounded-3 overflow-hidden mb-4">
                <div class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary bg-opacity-10">
                  <span class="fw-semibold text-primary">
                    <i class="ti tabler-calendar-stats me-1"></i>{{ ucwords($plan['name']) }}
                  </span>
                  <span class="badge bg-label-primary flex-shrink-0">
                    {{ $plan['num'] }} {{ $plan['num'] > 1 ? 'Installments' : 'Installment' }}
                  </span>
                </div>

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

                <div class="plan-sub-items px-3">
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

                <div class="brief-card-total">
                  <span class="label">Total Payable</span>
                  <span class="value">{{ number_format($data['final_price'], 2) }} {{ $data['listing_unit'] }}</span>
                </div>
              </div>
            @endforeach
          </div>
        @endforeach
      @endif

    {{-- NOTE / FOOTER --}}
    <div class="mt-4 pt-2 border-top">
      <p class="mb-0">Thank you for choosing us.</p>
      <p class="mb-0 text-muted"><span class="fw-medium text-heading">Note:</span> This is the committed payment plan
        for the owner of the above property/unit and was issued at the time of ownership assignment.</p>
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