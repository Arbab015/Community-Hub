@props([
  'property' => null,
  'planPropertyName' => null,
  'planPropertyAddress' => null,
  'tablePrefix' => '',
  'shadow' => false,
  'selectedApplicant' => null,
  'printedByName' => null,
  'printedByRole' => null,
])

@php
  $isUnit = $property instanceof \App\Models\PropertyUnit;
  $listing = $listing ?? $property->listing;
  $plan = $plan ?? $property->plan;
  $listingPrice = $listing?->price ?? 0;
  $listingUnit = $listing?->unit ?? 'PKR';

  if (! isset($planPropertyName)) {
    $planPropertyName = $isUnit
        ? ($property->unit_name ?: 'Unit')
        : ($property->name ?: ucwords($property->type));
  }
  if (! isset($planPropertyAddress)) {
    $block = $isUnit ? ($property->floor?->property?->block ?? null) : ($property->block ?? null);
    $society = $block?->society;
    $addr = [];
    if ($isUnit) {
      if ($property->floor?->floor_type) $addr[] = 'Floor '.$property->floor->floor_type;
      $parent = $property->floor?->property;
      if ($parent) {
        if ($parent->name) $addr[] = $parent->name;
        if ($parent->property_no) $addr[] = 'Plot #'.$parent->property_no;
      }
    } else {
      if ($property->street) $addr[] = $property->street;
      if ($property->landmark) $addr[] = $property->landmark;
    }
    if ($block?->name) $addr[] = $block->name;
    if ($society?->name) $addr[] = $society->name;
    if ($society?->city) $addr[] = ucfirst($society->city);
    if ($society?->country) $addr[] = $society->country;
    $planPropertyAddress = implode(', ', array_filter($addr));
  }

  $selectedApplicant = $selectedApplicant ?? null;
  $paymentTemplate = $plan?->paymentTemplate;

  $propertyRef = $isUnit
      ? ($property->unit_name ?: 'Unit')
      : ($property->property_no ?: ($property->name ?: 'Property'));

  $feeKeys = [
      ['key' => 'booking_fee',      'label' => 'Booking Fee'],
      ['key' => 'development_fee',  'label' => 'Development Fee'],
      ['key' => 'down_payment',     'label' => 'Down Payment'],
      ['key' => 'transfer_fee',     'label' => 'Transfer Fee'],
      ['key' => 'confirmation_fee', 'label' => 'Confirmation Fee'],
      ['key' => 'possession_fee',   'label' => 'Possession Fee'],
  ];
  $premiumKeys = [
      ['key' => 'corner_premium',           'label' => 'Corner Premium'],
      ['key' => 'park_facing_premium',      'label' => 'Park Facing Premium'],
      ['key' => 'main_boulevard_premium',   'label' => 'Main Boulevard Premium'],
  ];
  $surplusKeys = ['development_fee', 'transfer_fee', 'possession_fee'];

  $feeValue = fn ($key) => $plan?->{$key} ?? $paymentTemplate?->{$key};
  $feeIsPer = function ($key) use ($plan, $paymentTemplate, $feeValue) {
    if ($plan && $plan->{$key} !== null) {
      return (bool) $plan->{$key.'_in_per'};
    }
    return (bool) ($paymentTemplate?->{$key.'_in_per'} ?? false);
  };
  $feeIsSurplus = function ($key) use ($plan, $paymentTemplate, $surplusKeys) {
    if (! in_array($key, $surplusKeys)) return false;
    if ($plan && $plan->{$key} !== null) {
      return (bool) $plan->{$key.'_surplus'};
    }
    return (bool) ($paymentTemplate?->{$key.'_surplus'} ?? false);
  };

  $fmtRaw = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

  $rows = [];
  $excludedTotal = 0;

  foreach ($feeKeys as $fk) {
    $raw = (float) ($feeValue($fk['key']) ?? 0);
    if ($raw <= 0) continue;

    $isPer = $feeIsPer($fk['key']);
    $amount = $isPer ? $listingPrice * $raw / 100 : $raw;
    $surplus = $feeIsSurplus($fk['key']);

    $rows[] = [
      'label'  => $fk['label'],
      'detail' => $isPer ? $fmtRaw($raw).'% of listed price' : number_format($raw, 2).' '.$listingUnit,
      'note'   => $surplus ? 'Excluded' : 'Included',
      'amount' => $amount,
    ];
    if ($surplus) $excludedTotal += $amount;
  }

  $premiumFeatureFlags = [
    'corner_premium'         => 'is_corner',
    'park_facing_premium'    => 'is_park_facing',
    'main_boulevard_premium' => 'is_main_boulevard_facing',
  ];
  $premiumSource = $isUnit ? ($property->floor?->property ?? null) : $property;

  foreach ($premiumKeys as $pk) {
    $featureField = $premiumFeatureFlags[$pk['key']] ?? null;
    if ($featureField && ! (bool) ($premiumSource->{$featureField} ?? false)) continue;

    $raw = (float) ($feeValue($pk['key']) ?? 0);
    if ($raw <= 0) continue;

    $amount = $listingPrice * $raw / 100;
    $rows[] = [
      'label'  => $pk['label'],
      'detail' => $fmtRaw($raw).'% of listed price',
      'note'   => 'Excluded',
      'amount' => $amount,
    ];
    $excludedTotal += $amount;
  }

  array_unshift($rows, [
    'label'  => 'Listed Price',
    'detail' => 'Base price',
    'note'   => '',
    'amount' => $listingPrice,
  ]);

  $totalPrice = $listingPrice + $excludedTotal;

  $installmentRows = [];
  foreach ($plan?->installmentAttachments ?? [] as $att) {
    $tpl = $att->installmentTemplate;
    $name = ($att->name ?: '') ?: ($tpl?->name ?: 'Installment Plan');
    $num = (int) ($att->number_of_installments ?? $tpl?->number_of_installments ?? 0);
    $frequency = $att->installment_frequency ?? $tpl?->installment_frequency;

    $addPer = (bool) ($att->additional_installment_amount_in_per ?? $tpl?->additional_installment_amount_in_per ?? false);
    $addRaw = (float) ($att->additional_installment_amount ?? $tpl?->additional_installment_amount ?? 0);
    $addAmount = $addPer ? $listingPrice * $addRaw / 100 : $addRaw;

    $balloonPer = (bool) ($att->balloon_payment_in_per ?? $tpl?->balloon_payment_in_per ?? false);
    $balloonRaw = (float) ($att->balloon_payment ?? $tpl?->balloon_payment ?? 0);
    $planBase = $listingPrice + $excludedTotal + $addAmount;
    $balloonAmount = $balloonPer ? $planBase * $balloonRaw / 100 : $balloonRaw;

    $installmentRows[] = [
      'name'           => $name,
      'num'            => $num,
      'frequency'      => $frequency,
      'add'            => $addAmount,
      'add_raw'        => $addRaw,
      'add_in_per'     => $addPer,
      'balloon'        => $balloonAmount,
      'per_installment'=> $num > 0 ? ($planBase - $balloonAmount) / $num : 0,
      'total'          => $planBase,
    ];
  }

  $typeDisplay = $plan ? ($paymentTemplate?->name ?: 'Custom Plan') : '-';
  $applicantName = $selectedApplicant?->name ?: 'Not selected';
@endphp

<div class="card invoice-preview-card p-sm-12 p-6 {{ $shadow ? 'shadow-none border' : '' }}">
  <!-- HEADER SECTION -->
  <div class="card-body invoice-preview-header p-0 mb-6">
    <div class="row align-items-start gy-3">
      <!-- LEFT: Logo -->
      <div class="col-12 col-md-6 d-flex svg-illustration gap-2 align-items-center">
        <span
          class="app-brand-logo demo text-primary">@include('_partials.macros', ['height' => 18, 'width' => 32])</span>
        <span class="app-brand-text fw-bold fs-4 ms-50">{{ config('variables.templateName') }}</span>
      </div>

      <!-- RIGHT: Payment Plan Title -->
      <div class="col-12 col-md-6 col-lg-5 ms-lg-auto">
        <h5 class="mb-2">PAYMENT PLAN — {{ $propertyRef }}</h5>
        <p class="mb-0 text-heading"><span>Date Issued:</span> <span
            class="fw-medium">{{ now()->format('M d, Y') }}</span></p>
      </div>
    </div>
  </div>

  <!-- PROPERTY & APPLICANT SECTION -->
  <div class="row gy-4 mb-4">
    <!-- LEFT: Property Details -->
    <div class="col-12 col-md-7">
      <h6 class="mb-1 fw-bold">Property Details:</h6>
      <p class="mb-1 fw-semibold text-heading">{{ ucwords($planPropertyName) }}</p>
      <p class="mb-1">{{ ucwords($planPropertyAddress) }}</p>
      <p class="mb-0">Property No: #{{ ucfirst($isUnit
      ? $property->floor->property->property_no
      : $property->property_no)  }}</p>
    </div>

    <!-- RIGHT: Applicant -->
    <div class="col-12 col-md-5">
      <h6 class="mb-1">Applicant:</h6>
      <p class="mb-1">{{ $applicantName }}</p>
      @if($printedByName || $printedByRole)
        <p class="mb-0 text-muted">
          <span class="text-heading">Prepared By:</span>
          {{ $printedByName }}@if($printedByName && $printedByRole)
            ({{ $printedByRole }})
          @elseif(! $printedByName && $printedByRole)
            {{ $printedByRole }}
          @endif
        </p>
      @endif
    </div>
  </div>

  <!-- FEES TABLE -->
  <div class="table-responsive border rounded mb-2">
    <table class="table m-0">
      <thead>
      <tr>
        <th>Item</th>
        <th>Description</th>
        <th>Rate / Value</th>
        <th>Amount</th>
      </tr>
      </thead>
      <tbody>
      @forelse($rows as $row)
        <tr>
          <td class="text-nowrap text-heading">{{ $row['label'] }}</td>
          <td class="text-nowrap">
            @if($row['note'] !== '')
              {{ $row['note'] }} <span class="text-muted">({{ $row['detail'] }})</span>
            @else
              {{ $row['detail'] }}
            @endif
          </td>
          <td class="text-nowrap">{{ $row['detail'] }}</td>
          <td class="fw-semibold">{{ number_format($row['amount'], 2) }} {{ $listingUnit }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="text-center text-muted py-3">No charges are configured for this plan.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <!-- INSTALLMENT PLANS -->
  @if(count($installmentRows))
    <div class="table-responsive mt-4">
      <h6 class="mb-2 fw-semibold">Installment Plans</h6>
      <table class="table m-0 table-sm">
        <thead class="table-light">
        <tr>
          <th class="ps-3">Plans</th>
          <th class="ps-3">Installments</th>
          <th class="ps-3">Additional Amount</th>
          <th class="ps-3">Balloon</th>
          <th class="ps-3">Per Installment</th>
          <th class="ps-3">Total Payable</th>
        </tr>
        </thead>
        <tbody>
        @foreach($installmentRows as $ir)
          <tr>
            <td class="ps-3 text-heading fw-semibold">{{ $ir['name'] }}</td>
            <td class="ps-3">{{ $ir['num'] }} @if($ir['frequency'])
                ({{ ucfirst($ir['frequency']) }})
              @endif</td>
            <td class="ps-3">
              @if($ir['add'] > 0)
                +{{ number_format($ir['add'], 2) }} {{ $listingUnit }}
                @if($ir['add_in_per'])
                  <span class="text-muted">({{ $fmtRaw($ir['add_raw']) }}%)</span>
                @endif
              @else
                —
              @endif
            </td>
            <td class="ps-3">{{ $ir['balloon'] > 0 ? number_format($ir['balloon'], 2).' '.$listingUnit : '—' }}</td>
            <td class="ps-3">{{ number_format($ir['per_installment'], 2) }} {{ $listingUnit }}</td>
            <td class="ps-3 fw-semibold">{{ number_format($ir['total'], 2) }} {{ $listingUnit }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <!-- PRICE SUMMARY -->
  <div class="mt-6">
    <table class="table m-0 table-borderless">
      <tbody>
      <tr>
        <td class="ps-0 py-3 text-body">
          <p class="mb-1"><span class="me-2 h6">Plan Type:</span> <span>{{ ucwords($typeDisplay) }}</span></p>
          <span>Thank you for choosing us.</span>
        </td>
        <td class="px-0 py-3" style="width: 220px;">
          <table class="table table-borderless m-0">
            <tr>
              <td class="p-0 pb-1">Listed Price:</td>
              <td
                class="p-0 pb-1 text-end fw-medium text-heading">{{ number_format($listingPrice, 2) }} {{ $listingUnit }}</td>
            </tr>
            <tr class="border-bottom">
              <td class="p-0 pb-1">Additional Charges:</td>
              <td
                class="p-0 pb-1 text-end fw-medium text-heading">{{ number_format($excludedTotal, 2) }} {{ $listingUnit }}</td>
            </tr>
            <tr>
              <td class="p-0 pt-1">Total:</td>
              <td
                class="p-0 pt-1 text-end fw-medium text-heading">{{ number_format($totalPrice, 2) }} {{ $listingUnit }}</td>
            </tr>
          </table>
        </td>
      </tr>
      </tbody>
    </table>
  </div>

  <hr class="mt-0 mb-4" />
  <p class="mb-0"><span class="fw-medium text-heading">Note:</span> This is a system generated payment plan based on the
    listed price and selected charges. It is subject to change until confirmed.</p>
</div>
