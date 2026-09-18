@php
  $isUnit = $property instanceof \App\Models\PropertyUnit;
  $entityLabel = $isUnit ? 'Unit' : 'Property';
  $planableType = $isUnit ? 'unit' : 'property';

  // Owner-creation/link context for this property/unit (reuses create_member_owner modal).
  $ownerableType = $property instanceof \App\Models\PropertyUnit ? 'App\\Models\\PropertyUnit' : 'App\\Models\\Property';
  $ownerableId = $property->id;
  $propertySocietyId = $isUnit
      ? optional($property->floor?->property?->block)->society_id
      : optional($property->block)->society_id;
  $loginUser = auth()->user();
  $loginScope = $loginUser ? \App\Helpers\SocietyAccessResolver::resolver($loginUser) : ['ownedSocietyIds' => collect()];
  $ownedSocietyIds = $loginScope['ownedSocietyIds'] ?? collect();
  $memberSocieties = $ownedSocietyIds->count()
      ? \App\Models\Society::whereIn('id', $ownedSocietyIds)->get(['id', 'name'])
      : collect();
  $currentOwner = $property->owner;
  $societyMembers = $propertySocietyId
      ? \App\Models\Society::find($propertySocietyId)?->members()->with('attachment')->get()
      : collect();

  // The committed-plan snapshot for the current (open) ownership.
  $ownershipPlan = $property->ownershipPaymentPlans()
      ->whereHas('propertyOwner', fn ($q) => $q->whereNull('end_date'))
      ->latest('id')
      ->first();
  $isSoldOut = $ownershipPlan && $ownershipPlan->status === 'completed';
  $canUnlinkOwner = $ownershipPlan && $ownershipPlan->status !== 'completed';

  $plan = $property->paymentPlan;
  $paymentTemplate = $plan?->paymentTemplate;

  $listingPrice = $plan?->listed_price ?? optional($property->listing)->price ?? 0;
  $listingUnit = $plan?->listed_unit ?? optional($property->listing)->unit ?? 'PKR';

  $paymentTemplateOptions = $paymentsPlans ?? collect();

  // Templates matching this property's category...
  $allInstallmentTemplates = collect($installmentTemplates ?? collect())
      ->filter(fn ($template) => (bool) $template->is_commercial === (($isUnit
          ? optional($property->floor?->property)->category
          : optional($property)->category) === 'commercial'))
      ->values();

  // ...minus the ones already attached to this payment plan.
  $attachedTemplateIds = collect($plan?->installmentAttachments ?? [])
      ->pluck('installment_template_id')
      ->filter()
      ->all();

  $installmentTemplateOptions = $allInstallmentTemplates
      ->reject(fn ($template) => in_array($template->id, $attachedTemplateIds))
      ->values();

  $feeKeys = [
      ['key' => 'booking_fee',      'label' => 'Booking Fee',      'icon' => 'ti tabler-key'],
      ['key' => 'development_fee',  'label' => 'Development Fee',  'icon' => 'ti tabler-building-community'],
      ['key' => 'down_payment',     'label' => 'Down Payment',     'icon' => 'ti tabler-cash-banknote'],
      ['key' => 'transfer_fee',     'label' => 'Transfer Fee',     'icon' => 'ti tabler-transfer-in'],
      ['key' => 'confirmation_fee', 'label' => 'Confirmation Fee', 'icon' => 'ti tabler-file-check'],
      ['key' => 'possession_fee',   'label' => 'Possession Fee',   'icon' => 'ti tabler-home-check'],
  ];

  $premiumKeys = [
      ['key' => 'corner_premium',           'label' => 'Corner Premium',          'icon' => 'ti tabler-corner-down-right-double'],
      ['key' => 'park_facing_premium',      'label' => 'Park Facing Premium',     'icon' => 'ti tabler-trees'],
      ['key' => 'main_boulevard_premium',   'label' => 'Main Boulevard Premium',  'icon' => 'ti tabler-road'],
  ];

  $surplusKeys = ['development_fee', 'transfer_fee', 'possession_fee'];
  $feeValue = fn ($key) => $plan?->{$key} ?? $paymentTemplate?->{$key};
  $feeIsPer = fn ($key) => (bool) ($plan?->{$key} !== null
      ? $plan->{$key.'_in_per'}
      : ($paymentTemplate?->{$key.'_in_per'} ?? false));
  $feeIsSurplus = fn ($key) => ! in_array($key, $surplusKeys)
      ? false
      : (bool) ($plan?->{$key} !== null
          ? $plan->{$key.'_surplus'}
          : ($paymentTemplate?->{$key.'_surplus'} ?? false));

  // ── Step 1: Compute premiums (Premiums) — % of listed price ──
  $premiumFeatureFlags = [
      'corner_premium'         => 'is_corner',
      'park_facing_premium'    => 'is_park_facing',
      'main_boulevard_premium' => 'is_main_boulevard_facing',
  ];
  $premiumSource = $isUnit ? ($property->floor?->property ?? null) : $property;

  $premiumCharges = [];
  $premiumsTotal = 0;

  foreach ($premiumKeys as $pk) {
      $featureField = $premiumFeatureFlags[$pk['key']] ?? null;
      if ($featureField && ! (bool) ($premiumSource->{$featureField} ?? false)) {
          continue;
      }
      $raw = (float) ($feeValue($pk['key']) ?? 0);
      if ($raw <= 0) continue;
      $amount = $listingPrice * $raw / 100;
      $premiumCharges[] = $pk + ['raw' => $raw, 'amount' => $amount];
      $premiumsTotal += $amount;
  }

  // ── Step 2: Gross Amount = Listed + Premiums ──
  $grossAmount = $listingPrice + $premiumsTotal;

  // ── Step 3: Template fees — excluded (% of gross), included (% of gross+excluded) ──
  $charges = [];
  $includedCharges = [];
  $excludedCharges = [];
  $includedTotal = 0;
  $excludedTotal = 0;

  foreach ($feeKeys as $fk) {
      $raw = (float) ($feeValue($fk['key']) ?? 0);
      if ($raw <= 0) continue;
      $isPer = $feeIsPer($fk['key']);
      $surplus = $feeIsSurplus($fk['key']);
      if (! $surplus) continue;

      $amount = $isPer ? $grossAmount * $raw / 100 : $raw;
      $item = $fk + ['raw' => $raw, 'amount' => $amount, 'is_per' => $isPer, 'surplus' => true];
      $charges[] = $item;
      $excludedCharges[] = $item;
      $excludedTotal += $amount;
  }

  $includedBase = $grossAmount + $excludedTotal;
  foreach ($feeKeys as $fk) {
      $raw = (float) ($feeValue($fk['key']) ?? 0);
      if ($raw <= 0) continue;
      $isPer = $feeIsPer($fk['key']);
      $surplus = $feeIsSurplus($fk['key']);
      if ($surplus) continue;

      $amount = $isPer ? $includedBase * $raw / 100 : $raw;
      $item = $fk + ['raw' => $raw, 'amount' => $amount, 'is_per' => $isPer, 'surplus' => false];
      $charges[] = $item;
      $includedCharges[] = $item;
      $includedTotal += $amount;
  }

  // ── Installment attachments via InstallmentPlanCalculator ──
  $attachments = $plan?->installmentAttachments ?? collect();

  $attValue = function ($att, $field) {
      $value = $att->{$field} ?? null;
      if ($value !== null) return $value;
      return $att->installmentTemplate?->{$field} ?? null;
  };
  $attName = fn ($att) => (($att->name ?? '') ?: '') ?: ($att->installmentTemplate?->name ?: 'Installment Plan');

  $installmentPlans = [];
  foreach ($attachments as $idx => $att) {
      $num = (int) $attValue($att, 'number_of_installments');
      $frequency = $attValue($att, 'installment_frequency');

      $addPer = (bool) $attValue($att, 'additional_installment_amount_in_per');
      $addRaw = (float) ($attValue($att, 'additional_installment_amount') ?? 0);
      $addAmount = $addPer ? $grossAmount * $addRaw / 100 : $addRaw;

      $balloonPer = (bool) $attValue($att, 'balloon_payment_in_per');
      $balloonRaw = (float) ($attValue($att, 'balloon_payment') ?? 0);
      $balloonFrequency = $attValue($att, 'balloon_payment_frequency');

      $calc = \App\Support\InstallmentPlanCalculator::calculate([
          'listing_price' => $grossAmount,
          'included_charges_total' => $includedTotal,
          'excluded_charges_total' => $excludedTotal,
          'additional_amount' => $addAmount,
          'number_of_installments' => $num,
          'installment_frequency' => $frequency,
          'balloon_payment' => $balloonRaw,
          'balloon_payment_in_per' => $balloonPer,
          'balloon_payment_frequency' => $balloonFrequency,
      ]);

      $installmentPlans[] = [
          'id'                     => $att->id,
          'att'                    => $att,
          'name'                   => $attName($att),
          'from_template'          => $att->installment_template_id !== null,
          'template_name'          => $att->installmentTemplate?->name,
          'sequence'               => $idx + 1,
          'number_of_installments' => $num,
          'installment_frequency'  => $frequency,
          'add_amount'             => $addAmount,
          'add_raw'                => $addRaw,
          'add_in_per'             => $addPer,
          'balloon_amount'         => $calc['total_balloon'],
          'balloon_raw'            => $balloonRaw,
          'balloon_in_per'         => $balloonPer,
          'balloon_frequency'      => $balloonFrequency,
          'balloon_count'          => $calc['balloon_count'],
          'per_balloon'            => $calc['per_balloon'],
          'normal_count'           => $calc['normal_count'],
          'grand_total'            => $calc['grand_total'],
          'installment_remaining'  => $calc['installment_remaining'],
          'per_installment'        => $calc['per_installment'],
          'schedule'               => $calc['schedule'],
          'total_years'            => $calc['total_years'],
          'edit_payload'           => json_encode([
              'name' => $attName($att),
              'number_of_installments' => $num,
              'installment_frequency' => $frequency,
              'additional_installment_amount' => $addRaw,
              'additional_installment_amount_in_per' => (int) $addPer,
              'balloon_payment' => $balloonRaw,
              'balloon_payment_in_per' => (int) $balloonPer,
              'balloon_payment_frequency' => $balloonFrequency,
          ]),
      ];
  }

  $defaultPlan = $installmentPlans[0] ?? null;

  // ── Summary calculations ──
  $totalOneTime = $excludedTotal + $includedTotal;
  $remainingAmount = $grossAmount + $excludedTotal - $includedTotal;
  $extraInstallment = $defaultPlan ? $defaultPlan['add_amount'] : 0;
  $summaryPerBalloon = $defaultPlan ? $defaultPlan['per_balloon'] : 0;
  $summaryPerInstallment = $defaultPlan ? $defaultPlan['per_installment'] : 0;

@endphp

@if($ownershipPlan && $ownershipPlan->status !== 'completed')
  @include('components.property.ownership-payment-plans', ['property' => $property])
@else
  <div class="planner">
    <div class="row g-4">

      {{-- Main column --}}
      <div class="col-lg-8 order-2 order-lg-1">

        {{-- Owner / Member Section --}}

        @if($currentOwner || $property->paymentPlan)
          <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
              <div class="d-flex align-items-center gap-2">
                <i class="ti tabler-user text-primary fs-5"></i>
                <h5 class="fw-bold mb-0">Owner / Member</h5>
              </div>
              @if($ownershipPlan)
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <a href="{{ route('property_owners.plan.print', $ownershipPlan) }}?mode=brief" target="_blank"
                     class="btn btn-sm btn-outline-primary" title="Print the committed payment plan for this owner">
                    <i class="ti tabler-printer me-1"></i> Print Owner Plan
                  </a>
                </div>
              @endif
            </div>

            @if($currentOwner)
              <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                  <i class="ti tabler-user-check"></i>
                  <span><b>Current Owner:</b> {{ $currentOwner->first_name }} {{ $currentOwner->last_name }} ({{ $currentOwner->email }})</span>
                </div>
                @if($canUnlinkOwner && Auth()->user()->can('property_assign_owner'))
                  <form action="{{ route('property_owners.unlink') }}" method="POST"
                        onsubmit="return confirmDelete(event, null)">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="ownerable_type" value="{{ $ownerableType }}">
                    <input type="hidden" name="ownerable_id" value="{{ $ownerableId }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            title="End this ownership and relist for sale">
                      <i class="ti tabler-user-off me-1"></i> Unlink Owner
                    </button>
                  </form>
                @endif
              </div>
            @elseif(Auth()->user()->can('property_assign_owner'))
              <div class="alert alert-secondary d-flex align-items-center gap-2 mb-3">
                <i class="ti tabler-info-circle text-muted"></i>
                <span class="small">No current owner linked yet. Use "Assign Owner" to pick an existing member or create a new one; the property's payment plan and owner are set together.</span>
              </div>

              <div
                class="border rounded-3 p-3 d-flex flex-column flex-md-row align-items-md-center gap-2 justify-content-between">
                <div>
                  <div class="fw-semibold mb-1">Assign an owner</div>
                  <div class="text-muted small">Pick an existing member or create a new one. The property's own payment
                    plan (and optional single installment) is used, then the member is set as the current owner.
                  </div>
                </div>
                <div class="flex-shrink-0">
                  <button type="button" class="btn btn-sm btn-primary assign-member-btn"
                          data-ownerable-type="{{ $ownerableType }}"
                          data-ownerable-id="{{ $ownerableId }}">
                    <i class="ti tabler-user-check me-1"></i> Assign Owner
                  </button>
                </div>
              </div>
            @else
              <div class="alert alert-primary d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                  <i class="ti tabler-user-check"></i>
                  <span><b>Current Owner:</b> No Owner for this property yet</span>
                </div>
              </div>
            @endif
          </div>
        @endif

        {{-- Charges Section --}}
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div class="d-flex align-items-center gap-2">
              <i class="ti tabler-receipt text-primary fs-5"></i>
              <h5 class="fw-bold mb-0">Charges</h5>
            </div>
            @if($plan)
              <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="badge bg-label-success d-inline-flex align-items-center gap-1">
                <i class="ti tabler-circle-check-filled"></i> Payment Plan Applied
              </span>
                @can('payment_plan_edit')
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                          data-bs-target="#editPlanModal">
                    <i class="ti tabler-edit me-1"></i> Edit Values
                  </button>
                @endcan
                @can('payment_plan_print')
                  @if(count($installmentPlans))
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                            data-bs-target="#printPlanModal">
                      <i class="ti tabler-printer me-1"></i> Print Plan
                    </button>
                  @else
                    <a href="{{ route('payment_plan.print', $property->uuid) }}?include_installments=0" target="_blank"
                       class="btn btn-sm btn-outline-primary">
                      <i class="ti tabler-printer me-1"></i> Print Plan
                    </a>
                  @endif
                @endcan
                @can('payment_plan_delete')
                  <form action="{{ route('payment_plan.destroy', $plan) }}" method="POST"
                        onsubmit="return confirmDelete(event, null)">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="ti tabler-trash me-1"></i> Remove Plan
                    </button>
                  </form>
                @endcan
              </div>
            @else
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-label-secondary">No Plan Applied</span>
                @can('payment_plan_apply')
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                          data-bs-target="#applyPlanModal">
                    <i class="ti tabler-plus me-1"></i> Apply Plan
                  </button>
                @endcan
              </div>
            @endif
          </div>

          {{-- Applied templates info --}}
          @if($plan)
            <div class="border rounded-3 p-3 mb-3 bg-label-secondary">
              <div class="row g-3">
                <div class="col-md-4">
                  <small class="micro-label text-muted text-uppercase d-block mb-1">Payment Template</small>
                  @if($paymentTemplate)
                    <div class="fw-semibold small">
                      <i class="ti tabler-file-invoice text-primary me-1"></i>{{ $paymentTemplate->name }}
                    </div>
                  @else
                    <div class="fw-semibold small">
                      <i class="ti tabler-pencil text-info me-1"></i>Custom values
                    </div>
                  @endif
                </div>
                <div class="col-md-8">
                  <small class="micro-label text-muted text-uppercase d-block mb-1">Installment Templates</small>
                  @if(count($installmentPlans))
                    <div class="d-flex flex-wrap gap-2">
                      @foreach($installmentPlans as $planData)
                        <span class="badge {{ $planData['from_template'] ? 'bg-label-primary' : 'bg-label-info' }}">
                        @if($planData['from_template'])
                            <i class="ti tabler-file-invoice me-1"></i>{{ $planData['template_name'] }}
                          @else
                            <i class="ti tabler-pencil me-1"></i>{{ $planData['name'] }} (Custom)
                          @endif
                      </span>
                      @endforeach
                    </div>
                  @else
                    <span class="text-muted small">No installment plans attached</span>
                  @endif
                </div>
              </div>
            </div>
          @else
            <div class="alert alert-secondary py-2 px-3 d-flex align-items-center gap-2 mb-3">
              <i class="ti tabler-info-circle text-muted"></i>
              <span class="small">No payment plan applied yet. Click <b>Apply Plan</b> to choose a payment template and/or
              installment templates, then customize the values.</span>
            </div>
          @endif

          @if(count($charges) || count($premiumCharges))
            <div class="row g-3">
              @foreach($charges as $fee)
                <div class="col-md-6">
                  <div
                    class="border rounded-3 p-3 h-100 {{ $fee['surplus'] ? 'border-success border-opacity-25 bg-success bg-opacity-10' : 'bg-label-secondary' }}">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                      <div class="d-flex align-items-center gap-2 min-w-0">
                      <span class="rounded bg-primary bg-opacity-10 p-2 d-inline-flex flex-shrink-0">
                        <i class="ti {{ $fee['icon'] }} text-primary"></i>
                      </span>
                        <div class="min-w-0">
                          <div class="fw-semibold small">{{ $fee['label'] }}</div>
                          <div class="text-muted text-tiny">
                            @if($fee['is_per'])
                              {{ rtrim(rtrim(number_format($fee['raw'], 2, '.', ''), '0'), '.') }}
                              % {{ $fee['surplus'] ? 'of gross' : 'of (gross + excluded)' }}
                            @else
                              {{ number_format($fee['raw'], 2) }} {{ $listingUnit }}
                            @endif
                          </div>
                        </div>
                      </div>
                      <div class="text-end flex-shrink-0">
                        <div class="fw-bold">{{ number_format($fee['amount'], 2) }} {{ $listingUnit }}</div>
                        <span class="badge badge-xs {{ $fee['surplus'] ? 'bg-danger' : 'bg-primary' }}">
                        {{ $fee['surplus'] ? 'Excluded' : 'Included' }}
                      </span>
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach

              @foreach($premiumCharges as $pc)
                <div class="col-md-6">
                  <div class="border rounded-3 p-3 h-100 border-success border-opacity-25 bg-success bg-opacity-10">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                      <div class="d-flex align-items-center gap-2 min-w-0">
                      <span class="rounded bg-primary bg-opacity-10 p-2 d-inline-flex flex-shrink-0">
                        <i class="ti {{ $pc['icon'] }} text-primary"></i>
                      </span>
                        <div class="min-w-0">
                          <div class="fw-semibold small">{{ $pc['label'] }}</div>
                          <div class="text-muted text-tiny">
                            {{ rtrim(rtrim(number_format($pc['raw'], 2, '.', ''), '0'), '.') }}% of listed price
                          </div>
                        </div>
                      </div>
                      <div class="text-end flex-shrink-0">
                        <div class="fw-bold">{{ number_format($pc['amount'], 2) }} {{ $listingUnit }}</div>
                        <span class="badge badge-xs bg-danger">Premium</span>
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

            <div class="mt-3 border rounded-3 p-3 bg-label-secondary small text-muted">
              <div class="d-flex align-items-center gap-2 mb-1">
                <i class="ti tabler-info-circle text-primary"></i>
                <span class="fw-semibold text-dark">How the charges are applied</span>
              </div>
              <ul class="mb-0 ps-3">
                <li><b class="text-danger">Premium</b> — an additional amount calculated based on the listed price and
                  added to the listed price to determine the gross amount.
                </li>

                <li><b class="text-danger">Excluded</b> — an additional amount calculated based on the gross amount and
                  added to it to determine the amount on which included charges are calculated.
                </li>

                <li><b class="text-primary">Included</b> — one-time charges calculated based on the gross amount,
                  including the applicable excluded amount. These charges are already incorporated into the gross amount
                  plus the excluded amount and do not increase the amount on which they are calculated.
                </li>

              </ul>
            </div>

          @else
            <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
              <i class="ti tabler-calendar-x fs-2 opacity-50 mb-2"></i>
              <small>No charges are configured for this {{ strtolower($entityLabel) }} yet.</small>
            </div>

          @endif
        </div>

        {{-- Installments Section --}}
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div class="d-flex align-items-center gap-2">
              <i class="ti tabler-calendar-repeat text-primary fs-5"></i>
              <h5 class="fw-bold mb-0">Installment Plans</h5>
            </div>
            @if($plan && !count($installmentPlans))
              @can('installment_plan_add')
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                        data-bs-target="#addInstallmentModal">
                  <i class="ti tabler-plus me-1"></i> Add Installment Plan
                </button>
              @endcan
            @endif
            @if(count($installmentPlans))
              <span
                class="badge bg-label-primary">{{ count($installmentPlans) }} {{ count($installmentPlans) > 1 ? 'plans' : 'plan' }} attached</span>
            @endif
          </div>

          @if(count($installmentPlans))

            {{-- Plan tabs --}}
            <div class="d-flex flex-wrap gap-2 mb-4">
              @foreach($installmentPlans as $planData)
                <button type="button"
                        class="btn btn-sm installment-tab {{ $planData['sequence'] === 1 ? 'btn-primary' : 'btn-outline-primary' }}"
                        data-sequence="{{ $planData['sequence'] }}">

                  {{ $planData['name'] }}
                </button>
              @endforeach
              @if($plan)
                @can('installment_plan_add')
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                          data-bs-target="#addInstallmentModal">
                    <i class="ti tabler-plus me-1"></i> New Plan
                  </button>
                @endcan
              @endif
            </div>

            {{-- Plan panes --}}
            @foreach($installmentPlans as $planData)
              <div class="installment-pane {{ $planData['sequence'] === 1 ? '' : 'd-none' }}"
                   data-sequence="{{ $planData['sequence'] }}"
                   data-name="{{ $planData['name'] }}"
                   data-extra="{{ $planData['add_amount'] }}"
                   data-balloon-amount="{{ $planData['balloon_amount'] }}"
                   data-installment-remaining="{{ $planData['installment_remaining'] }}"
                   data-grand-total="{{ $planData['grand_total'] }}"
                   data-per-balloon="{{ $planData['per_balloon'] }}"
                   data-per-installment="{{ $planData['per_installment'] }}"
                   data-balloon-count="{{ $planData['balloon_count'] }}"
                   data-normal-count="{{ $planData['normal_count'] }}"
                   data-num="{{ $planData['number_of_installments'] }}">

                {{-- Pane header: name, source, edit, remove --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="fw-semibold text-heading">
                      <i class="ti tabler-calendar-stats text-primary me-1"></i> {{ $planData['name'] }}
                    </div>
                    @if($planData['from_template'])
                      <span class="badge bg-label-primary">
                      <i class="ti tabler-file-invoice me-1"></i>{{ $planData['template_name'] }}
                    </span>
                    @else
                      <span class="badge bg-label-info">
                      <i class="ti tabler-pencil me-1"></i>Custom
                    </span>
                    @endif
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    @can('installment_plan_edit')
                      <button type="button"
                              class="btn btn-sm btn-outline-primary edit-installment-btn"
                              data-action="{{ route('payment_plan.update-installment', [$plan, $planData['att']]) }}"
                              data-installment="{{ $planData['edit_payload'] }}">
                        <i class="ti tabler-edit me-1"></i> Edit
                      </button>
                    @endcan
                    @can('installment_plan_delete')
                      <form action="{{ route('payment_plan.detach-installment', [$plan, $planData['att']]) }}"
                            method="POST"
                            onsubmit="return confirmDelete(event, null)">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                          <i class="ti tabler-trash me-1"></i> Remove
                        </button>
                      </form>
                    @endcan
                  </div>
                </div>

                {{-- Plan details chips --}}
                <div class="row g-2 mb-3">
                  <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                      <small class="micro-label text-muted text-uppercase d-block mb-1">Installments</small>
                      <div class="fw-semibold">{{ $planData['number_of_installments'] }}
                        @if($planData['installment_frequency'])
                          <span class="text-muted fw-normal">· {{ ucfirst($planData['installment_frequency']) }}</span>
                        @endif
                      </div>
                    </div>
                  </div>
                  <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                      <small class="micro-label text-muted text-uppercase d-block mb-1">Per Balloon</small>
                      <div class="fw-semibold">
                        @if($planData['per_balloon'] > 0)
                          {{ number_format($planData['per_balloon'], 2) }} {{ $listingUnit }}
                          @if($planData['balloon_count'] > 0)
                            <span class="text-muted fw-normal">({{ $planData['balloon_count'] }}x)</span>
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
                      <div class="fw-semibold">{{ number_format($planData['per_installment'], 2) }} {{ $listingUnit }}
                        @if($planData['normal_count'] > 0)
                          <span class="text-muted fw-normal">({{ $planData['normal_count'] }}x)</span>
                        @endif
                      </div>
                    </div>
                  </div>
                  <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                      <small class="micro-label text-muted text-uppercase d-block mb-1">Total Amount</small>
                      <div
                        class="fw-semibold">{{ number_format($grossAmount + $excludedTotal + $planData['add_amount'], 2) }} {{ $listingUnit }}</div>
                    </div>
                  </div>
                </div>

                {{-- Installment schedule --}}
                <div class="border rounded-3 overflow-hidden">
                  <div class="px-3 py-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold small text-primary">Installment Schedule</span>
                    <span
                      class="small text-muted">{{ $planData['number_of_installments'] }} {{ $planData['number_of_installments'] > 1 ? 'installments' : 'installment' }} · {{ ucfirst($planData['installment_frequency'] ?? '—') }}@if($planData['balloon_count'] > 0)
                        · {{ $planData['balloon_count'] }} balloon{{ $planData['balloon_count'] > 1 ? 's' : '' }}
                      @endif</span>
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
                      @foreach($planData['schedule'] as $seq => $row)
                        <tr class="{{ $row['type'] === 'balloon' ? 'bg-label-primary' : '' }}">
                          <td class="ps-3">{{ $seq + 1 }}</td>
                          <td class="ps-3 text-heading">
                            @if($row['type'] === 'balloon')
                              Balloon
                              #{{ $row['balloon_no'] }}
                              @if($planData['balloon_frequency'])
                                <span
                                  class="text-muted fw-normal">({{ ucfirst($planData['balloon_frequency']) }})</span>
                              @endif
                            @else
                              Installment #{{ $row['no'] }}
                            @endif
                          </td>
                          <td class="ps-3 fw-semibold">{{ number_format($row['amount'], 2) }} {{ $listingUnit }}</td>
                          <td class="ps-3 text-muted">{{ number_format($row['cumulative'], 2) }} {{ $listingUnit }}</td>
                        </tr>
                      @endforeach
                      <tr class="table-light">
                        <td class="ps-3 text-heading fw-bold" colspan="2">Total Payable <span
                            class="text-muted fw-normal">(Final Amount + Installment Extra)</span></td>
                        <td class="ps-3 fw-bold text-primary">
                          {{ number_format($grossAmount + $excludedTotal + $planData['add_amount'], 2) }} {{ $listingUnit }}
                        </td>
                        <td class="ps-3 text-muted">—</td>
                      </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            @endforeach

          @else
            <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
              <i class="ti tabler-calendar-x fs-2 opacity-50 mb-2"></i>

              <small class="text-center">
                No installment plans are attached to this {{ strtolower($entityLabel) }} yet.
                @if($plan && Auth()->user()->can('property_assign_owner'))
                  <br>Click <b>Add Installment Plan</b> above to attach one.
                @endif
              </small>
            </div>
          @endif
        </div>
      </div>

      {{-- Summary sidebar (MODIFIED) --}}
      <div class="col-lg-4 order-1 order-lg-2">
        <div class="summary-sticky">
          <div class="card border-0 shadow-sm rounded-3 p-4 mb-3 bg-light">
            <p class="text-uppercase small text-dark mb-2 fw-bold tracking-wide">Listed Value</p>
            <div class="d-flex align-items-baseline gap-2">
              <h3 class="fw-bold mb-0">{{ number_format($listingPrice, 0) }}</h3>
              <span class="text-muted fs-6">{{ $listingUnit }}</span>
            </div>
          </div>

          <div class="card border-0 shadow-sm rounded-3 p-4 bg-gradient text-white plan-summary-card">
            <p class="text-uppercase small mb-3 fw-bold plan-summary-title">Plan Summary</p>

            <div class="d-flex justify-content-between mb-2">
              <span class="small">Listed Price</span>
              <span class="fw-semibold fs-6" id="summary_listed">{{ number_format($listingPrice, 0) }}</span>
            </div>

            @if($premiumsTotal > 0)
              <span class="small d-block">Feature Premiums <span class="opacity-75">(% of listed)</span></span>
              <ul class="list-unstyled mb-1 ps-0 summary-list">
                @foreach($premiumCharges as $pc)
                  <li class="d-flex justify-content-between">
                    <span>{{ $pc['label'] }}</span>
                    <span>{{ number_format($pc['amount'], 0) }}</span>
                  </li>
                @endforeach
              </ul>
              <div class="d-flex justify-content-between fw-semibold small mb-1" id="summary_premiums">
                <span>Total Premiums</span>
                <span>{{ number_format($premiumsTotal, 0) }}</span>
              </div>
            @endif

            <div class="d-flex justify-content-between fw-semibold small">
              <span class="small">Gross Amount</span>
              <span id="summary_gross"
                    class="mb-2 bg-label-secondary px-2 rounded text-dark">{{ number_format($grossAmount, 0) }}</span>
            </div>

            <hr class="opacity-25">

            {{-- One Time Charges heading (same style as Feature Premiums) --}}
            <span class="small d-block mb-1">One Time Charges <span
                class="opacity-75">(excluded + included)</span></span>

            {{-- Excluded and Included as sub-sections (NESTED under One Time Charges) --}}
            <div class="ps-2 border-start border-2 opacity-90 mb-2">
              <span class="small d-block mb-1">Excluded Charges <span class="opacity-75">(on top of gross)</span></span>
              <ul class="list-unstyled mb-0 ps-0 summary-list">
                @forelse($excludedCharges as $c)
                  <li class="d-flex justify-content-between">
                    <span>{{ $c['label'] }}</span>
                    <span>{{ number_format($c['amount'], 0) }}</span>
                  </li>
                @empty
                  <li class="opacity-50">None</li>
                @endforelse
              </ul>
              <div class="d-flex justify-content-between fw-semibold small mt-1" id="summary_excluded">
                <span>Total Excluded</span>
                <span class="bg-label-secondary px-2 rounded text-dark">{{ number_format($excludedTotal, 0) }}</span>
              </div>

              <span class="small d-block mb-1 mt-2">Included Charges <span class="opacity-75">(in gross)</span></span>
              <ul class="list-unstyled mb-0 ps-0 summary-list">
                @forelse($includedCharges as $c)
                  <li class="d-flex justify-content-between">
                    <span>{{ $c['label'] }}</span>
                    <span>{{ number_format($c['amount'], 0) }}</span>
                  </li>
                @empty
                  <li class="opacity-50">None</li>
                @endforelse
              </ul>
              <div class="d-flex justify-content-between fw-semibold small mt-1" id="summary_included">
                <span>Total Included</span>
                <span>{{ number_format($includedTotal, 0) }}</span>
              </div>
            </div>

            {{-- Total one-time (excluded + included) before Remaining --}}
            <div class="d-flex justify-content-between fw-semibold small">
              <span>Total One-time</span>
              <span id="summary_one_time">{{ number_format($totalOneTime, 0) }}</span>
            </div>

            <div class="d-flex justify-content-between fw-bold small mt-2">
              <span class="small">Remaining Amount</span>
              <span id="summary_remaining">{{ number_format($remainingAmount, 0) }}</span>
            </div>

            <hr class="opacity-25">

            @if($defaultPlan)
              <div class="d-flex justify-content-between ">
                <span class="small">Extra in Case of Installment</span>
                <span class="fw-semibold fs-6 bg-label-secondary px-2 rounded text-dark"
                      id="summary_extra">{{ number_format($extraInstallment, 0) }}</span>
              </div>

              <div class="d-flex justify-content-between">
                <span class="small">Total Balloon</span>
                <span class="fw-semibold fs-6"
                      id="summary_total_balloon">{{ number_format($defaultPlan['balloon_amount'], 0) }}</span>
              </div>

              <div class="d-flex justify-content-between mb-2">
                <span class="small">Remaining for Installment</span>
                <span class="fw-semibold fs-6"
                      id="summary_installment_remaining">{{ number_format($defaultPlan['installment_remaining'], 0) }}</span>
              </div>

              <div class="d-flex justify-content-between fs-5 fw-bold mb-2 summary-final-row">
                <span>Final Amount</span>
                <span id="summary_final_amount"
                      class="bg-label-secondary px-2 rounded text-dark">{{ number_format($grossAmount + $excludedTotal + $extraInstallment, 0) }}</span>
              </div>
            @elseif($plan)
              <div class="small d-flex align-items-center gap-1 mb-2 opacity-75">
                <i class="ti tabler-info-circle"></i> No installment plan attached — showing the payment plan only.
              </div>
            @endif

            @if($defaultPlan)
              <div class="small d-block opacity-75" id="summary_per_installment">
                @if($summaryPerBalloon > 0)
                  Per balloon: {{ number_format($summaryPerBalloon, 2) }} {{ $listingUnit }}
                  · Per installment: {{ number_format($summaryPerInstallment, 2) }} {{ $listingUnit }}
                @else
                  Per installment: {{ number_format($summaryPerInstallment, 2) }} {{ $listingUnit }}
                @endif
                ({{ $defaultPlan['number_of_installments'] }} installments)
              </div>
            @elseif($plan)
              <div class="small d-block opacity-75" id="summary_per_installment">
                No installment plan attached.
              </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 summary-divider">
              <span class="small">Viewing</span>
              @if($plan)
                <select id="summary_view_select"
                        class="form-select form-select-sm border-0 bg-white text-dark fw-semibold d-inline-block summary-select">
                  <option value="plan_only" @selected(!$defaultPlan)>Payment plan only</option>
                  @foreach($installmentPlans as $p)
                    <option
                      value="{{ $p['sequence'] }}" @selected($defaultPlan && $p['sequence'] === 1)>{{ $p['name'] }}</option>
                  @endforeach
                </select>
              @else
                <span class="fw-semibold">—</span>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endif

@include('_partials._modals.create_member_owner', ['ownerableType' => $ownerableType, 'ownerableId' => $ownerableId, 'propertySocietyId' => $propertySocietyId, 'memberSocieties' => $societyMembers, 'paymentPlan' => $plan, 'planInstallments' => collect($plan?->installmentAttachments ?? [])])

{{-- Apply payment plan modal (shown when no plan is applied) --}}
@if(!$plan)
  <div class="modal fade" id="applyPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-simple modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder" id="modalTitle">Apply Payment Plan </h4>
            <p class="text-muted mb-2"> Choose a payment template and/or installment templates, or enter custom charge
              values directly for a full payment plan</p>
          </div>

          <form action="{{ route('payment_plan.apply_single') }}" method="POST">
            @csrf
            <input type="hidden" name="planable_type" value="{{ $planableType }}">
            <input type="hidden" name="planable_uuid" value="{{ $property->uuid }}">

            <input type="hidden" name="template_modified" id="template_modified" value="0">

            <div class="mb-3">
              <label class="form-label fw-semibold">Payment Template</label>
              <select name="payment_template_id" id="apply_payment_template" class="form-select">
                <option value="">None (custom values)</option>
                @foreach($paymentTemplateOptions as $pt)
                  <option value="{{ $pt->id }}"
                          data-booking-fee="{{ $pt->booking_fee }}"
                          data-booking-fee-in-per="{{ $pt->booking_fee_in_per ? 1 : 0 }}"
                          data-development-fee="{{ $pt->development_fee }}"
                          data-development-fee-in-per="{{ $pt->development_fee_in_per ? 1 : 0 }}"
                          data-development-fee-surplus="{{ $pt->development_fee_surplus ? 1 : 0 }}"
                          data-down-payment="{{ $pt->down_payment }}"
                          data-down-payment-in-per="{{ $pt->down_payment_in_per ? 1 : 0 }}"
                          data-transfer-fee="{{ $pt->transfer_fee }}"
                          data-transfer-fee-in-per="{{ $pt->transfer_fee_in_per ? 1 : 0 }}"
                          data-transfer-fee-surplus="{{ $pt->transfer_fee_surplus ? 1 : 0 }}"
                          data-confirmation-fee="{{ $pt->confirmation_fee }}"
                          data-confirmation-fee-in-per="{{ $pt->confirmation_fee_in_per ? 1 : 0 }}"
                          data-possession-fee="{{ $pt->possession_fee }}"
                          data-possession-fee-in-per="{{ $pt->possession_fee_in_per ? 1 : 0 }}"
                          data-possession-fee-surplus="{{ $pt->possession_fee_surplus ? 1 : 0 }}"
                          data-corner-premium="{{ $pt->corner_premium }}"
                          data-park-facing-premium="{{ $pt->park_facing_premium }}"
                          data-main-boulevard-premium="{{ $pt->main_boulevard_premium }}">
                    {{ $pt->name }}
                  </option>
                @endforeach
              </select>
              @if(!count($paymentTemplateOptions))
                <small class="text-muted">No payment templates available. <a
                    href="{{ route('payment-templates.index') }}">Create one</a>.</small>
              @endif
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Installment Templates <span
                  class="text-muted">(optional)</span></label>
              @if(count($installmentTemplateOptions))
                <div class="select2-primary">
                  <select id="select2Primary" name="installment_template_ids[]"
                          class="select2 form-select" multiple>
                    @foreach($installmentTemplateOptions as $it)
                      <option value="{{ $it->id }}">{{ $it->name }}</option>
                    @endforeach
                  </select>
                </div>
              @else
                <div class="border rounded-3 p-3 bg-label-secondary">
                  <small class="text-muted">No installment templates available. <a
                      href="{{ route('installment-templates.index') }}">Create one</a>.</small>
                </div>
              @endif
            </div>

            <div class="col-12">
              <hr class="my-1">
              <p class="section-caption fw-semibold small text-uppercase text-muted mb-3">
                Custom Charge Values
                <span
                  class="caption-hint fw-normal">(optional — leave blank to use the selected template's values)</span>
              </p>
            </div>

            <div class="row g-3">
              @foreach($feeKeys as $fk)
                @php $key = $fk['key']; @endphp
                <div class="col-md-6">
                  <div class="border rounded-3 p-3 bg-label-secondary h-100">
                    <label class="form-label fw-semibold mb-2">{{ $fk['label'] }}</label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <input type="number" name="{{ $key }}" class="form-control apply-charge-field" step="0.01" min="0"
                             placeholder="{{ $listingUnit }} or %">
                      <div class="form-check form-switch mb-0 flex-shrink-0">
                        <input type="checkbox" name="{{ $key }}_in_per" id="in_per_apply_{{ $key }}"
                               class="form-check-input apply-charge-field" value="1">
                        <label class="form-check-label small text-nowrap" for="in_per_apply_{{ $key }}">%</label>
                      </div>
                    </div>
                    @if(in_array($key, $surplusKeys))
                      <div class="form-check">
                        <input type="checkbox" name="{{ $key }}_surplus" id="surplus_apply_{{ $key }}"
                               class="form-check-input apply-charge-field" value="1">
                        <label class="form-check-label small" for="surplus_apply_{{ $key }}">Additional (on top of
                          listed)</label>
                      </div>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>

            <div class="col-12">
              <hr class="my-1">
              <p class="section-caption fw-semibold small text-uppercase text-muted mb-2">
                Feature Premiums (always % of listed price — extra, on top)
              </p>
            </div>

            <div class="row g-3">
              @foreach($premiumKeys as $pk)
                <div class="col-md-4">
                  <div class="border rounded-3 p-3 bg-label-secondary h-100">
                    <label class="form-label fw-semibold mb-2">{{ $pk['label'] }}</label>
                    <div class="input-group">
                      <input type="number" name="{{ $pk['key'] }}" class="form-control apply-charge-field" step="0.01"
                             min="0" max="100"
                             placeholder="%">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Apply Plan</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>

@endif

@if($plan)
  {{-- Add installment plan modal (available whenever a payment plan exists) --}}
  <div class="modal fade" id="addInstallmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-simple modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Add Installment Plan</h4>
            <p class="text-muted mb-0">Attach installment template(s) to the existing payment plan for this
              {{ strtolower($entityLabel) }}.</p>
          </div>

          <form action="{{ route('payment_plan.attach-installment', $plan) }}" method="POST">
            @csrf

            @if(count($installmentTemplateOptions))
              <div class="mb-3">
                <div class="select2-primary">
                  <select id="addInstallmentSelect" name="installment_template_ids[]"
                          class="select2 form-select installment-template-select" multiple
                          data-preview="addInstallmentPreview">
                    @foreach($installmentTemplateOptions as $it)
                      <option value="{{ $it->id }}"
                              data-name="{{ $it->name }}"
                              data-number-of-installments="{{ $it->number_of_installments }}"
                              data-frequency="{{ $it->installment_frequency }}"
                              data-additional="{{ $it->additional_installment_amount }}"
                              data-additional-in-per="{{ $it->additional_installment_amount_in_per ? 1 : 0 }}"
                              data-balloon="{{ $it->balloon_payment }}"
                              data-balloon-in-per="{{ $it->balloon_payment_in_per ? 1 : 0 }}"
                              data-balloon-frequency="{{ $it->balloon_payment_frequency }}">
                        {{ $it->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div id="addInstallmentPreview" class="mt-3 d-none"
                     data-price-base="{{ $listingPrice }}"
                     data-included-total="{{ $includedTotal }}"
                     data-excluded-total="{{ $excludedTotal }}">
                  <div class="it-preview-list"></div>
                </div>
              </div>
            @else
              <div class="border rounded-3 p-3 bg-label-secondary">
                <small class="text-muted">
                  @if(count($allInstallmentTemplates))
                    All available installment templates are already applied to this {{ strtolower($entityLabel) }}.
                  @else
                    No installment templates available. <a href="{{ route('installment-templates.index') }}">Create
                      one</a>.
                  @endif
                </small>
              </div>
            @endif

            <div class="d-flex justify-content-end gap-2 mt-4">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Add Installment Plan</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>


  {{-- Edit payment plan values modal --}}
  <div class="modal fade" id="editPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Edit Plan Values</h4>
            <p class="text-muted mb-0">Values are saved on this {{ strtolower($entityLabel) }} only — the source
              template
              is not changed.</p>
          </div>

          <form id="editPlanForm" action="{{ route('payment_plan.update', $plan) }}" method="POST">
            @csrf

            <div class="mb-3">
              <label class="form-label fw-semibold">Payment Template <span class="text-muted">(optional)</span></label>
              <select name="payment_template_id" id="edit_payment_template" class="form-select">
                <option value="">None (custom values)</option>
                @foreach($paymentTemplateOptions as $pt)
                  <option value="{{ $pt->id }}" @selected($plan->payment_template_id === $pt->id)
                  data-booking-fee="{{ $pt->booking_fee }}"
                          data-booking-fee-in-per="{{ $pt->booking_fee_in_per ? 1 : 0 }}"
                          data-development-fee="{{ $pt->development_fee }}"
                          data-development-fee-in-per="{{ $pt->development_fee_in_per ? 1 : 0 }}"
                          data-development-fee-surplus="{{ $pt->development_fee_surplus ? 1 : 0 }}"
                          data-down-payment="{{ $pt->down_payment }}"
                          data-down-payment-in-per="{{ $pt->down_payment_in_per ? 1 : 0 }}"
                          data-transfer-fee="{{ $pt->transfer_fee }}"
                          data-transfer-fee-in-per="{{ $pt->transfer_fee_in_per ? 1 : 0 }}"
                          data-transfer-fee-surplus="{{ $pt->transfer_fee_surplus ? 1 : 0 }}"
                          data-confirmation-fee="{{ $pt->confirmation_fee }}"
                          data-confirmation-fee-in-per="{{ $pt->confirmation_fee_in_per ? 1 : 0 }}"
                          data-possession-fee="{{ $pt->possession_fee }}"
                          data-possession-fee-in-per="{{ $pt->possession_fee_in_per ? 1 : 0 }}"
                          data-possession-fee-surplus="{{ $pt->possession_fee_surplus ? 1 : 0 }}"
                          data-corner-premium="{{ $pt->corner_premium }}"
                          data-park-facing-premium="{{ $pt->park_facing_premium }}"
                          data-main-boulevard-premium="{{ $pt->main_boulevard_premium }}">
                    {{ $pt->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="row g-3">
              @foreach($feeKeys as $fk)
                @php
                  $key = $fk['key'];
                  $isSurplusKey = in_array($key, $surplusKeys);
                  $currentValue = $feeValue($key);
                @endphp
                <div class="col-md-6">
                  <div class="border rounded-3 p-3 bg-label-secondary h-100">
                    <label class="form-label fw-semibold mb-2">{{ $fk['label'] }}</label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <input type="number" name="{{ $key }}" id="{{ $key }}" class="form-control" step="0.01" min="0"
                             value="{{ $currentValue !== null ? $currentValue : '' }}"
                             placeholder="{{ $listingUnit }} or %">
                      <div class="form-check form-switch mb-0 flex-shrink-0">
                        <input type="checkbox" name="{{ $key }}_in_per" id="in_per_{{ $key }}"
                               class="form-check-input" value="1" {{ $feeIsPer($key) ? 'checked' : '' }}>
                        <label class="form-check-label small text-nowrap" for="in_per_{{ $key }}">%</label>
                      </div>
                    </div>
                    <div class="invalid-feedback d-block" id="{{ $key }}_error"></div>
                    @if($isSurplusKey)
                      <div class="form-check">
                        <input type="checkbox" name="{{ $key }}_surplus" id="surplus_{{ $key }}"
                               class="form-check-input" value="1" {{ $feeIsSurplus($key) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="surplus_{{ $key }}">Additional (on top of
                          listed)</label>
                      </div>
                    @endif
                  </div>
                </div>
              @endforeach

              <div class="col-12">
                <hr class="my-1">
                <p class="section-caption fw-semibold small text-uppercase text-muted mb-2">
                  Feature Premiums (always % of listed price — extra, on top)
                </p>
              </div>

              @foreach($premiumKeys as $pk)
                @php $currentPremium = $feeValue($pk['key']); @endphp
                <div class="col-md-4">
                  <div class="border rounded-3 p-3 bg-label-secondary h-100">
                    <label class="form-label fw-semibold mb-2">{{ $pk['label'] }}</label>
                    <div class="input-group">
                      <input type="number" name="{{ $pk['key'] }}" id="{{ $pk['key'] }}" class="form-control"
                             step="0.01" min="0" max="100"
                             value="{{ $currentPremium !== null ? $currentPremium : '' }}" placeholder="%">
                      <span class="input-group-text">%</span>
                    </div>
                    <div class="invalid-feedback d-block" id="{{ $pk['key'] }}_error"></div>
                  </div>
                </div>
              @endforeach
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Values</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Edit installment plan modal --}}
  @if(count($installmentPlans))

    <div class="modal fade" id="editInstallmentModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body p-0">
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

            <div class="text-center mb-4">
              <h4 class="mb-2 fw-bolder">Edit Installment Plan</h4>
              <p class="text-muted mb-0">Values are saved on this {{ strtolower($entityLabel) }} only — the
                source
                template is not changed.</p>
            </div>

            <form id="editInstallmentForm" method="POST">
              @csrf

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Name</label>
                  <input type="text" name="name" id="ei_name" class="form-control">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Number of Installments</label>
                  <input type="number" name="number_of_installments" id="ei_number_of_installments"
                         class="form-control"
                         min="1" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Frequency</label>
                  <select name="installment_frequency" id="ei_installment_frequency" class="form-select"
                          required>
                    <option value="">Select</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="biannually">Biannually</option>
                    <option value="annually">Annually</option>
                  </select>
                </div>

                <div class="col-md-7">
                  <label class="form-label fw-semibold">Additional Installment Amount</label>
                  <input type="number" name="additional_installment_amount"
                         id="ei_additional_installment_amount"
                         class="form-control" step="0.01" min="0" required>
                  <div class="invalid-feedback d-block" id="ei_additional_installment_amount_error"></div>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                  <div class="form-check form-switch mb-2">
                    <input type="hidden" name="additional_installment_amount_in_per" value="0">
                    <input type="checkbox" id="ei_additional_installment_amount_in_per" class="form-check-input"
                           onchange="document.querySelector('input[name=additional_installment_amount_in_per]').value = this.checked ? 1 : 0">
                    <label class="form-check-label small" for="ei_additional_installment_amount_in_per">As
                      Percentage
                      (%)</label>
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold">Balloon Payment</label>
                  <input type="number" name="balloon_payment" id="ei_balloon_payment" class="form-control"
                         step="0.01"
                         min="0">
                  <div class="invalid-feedback d-block" id="ei_balloon_payment_error"></div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                  <div class="form-check form-switch mb-2">
                    <input type="hidden" name="balloon_payment_in_per" value="0">
                    <input type="checkbox" id="ei_balloon_payment_in_per" class="form-check-input"
                           onchange="document.querySelector('input[name=balloon_payment_in_per]').value = this.checked ? 1 : 0">
                    <label class="form-check-label small" for="ei_balloon_payment_in_per">As Percentage
                      (%)</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Balloon Frequency</label>
                  <select name="balloon_payment_frequency" id="ei_balloon_payment_frequency"
                          class="form-select">
                    <option value="">Not applicable</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="biannually">Biannually</option>
                    <option value="annually">Annually</option>
                  </select>
                  <div class="invalid-feedback d-block" id="ei_balloon_payment_frequency_error"></div>
                </div>
              </div>

              <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  @endif
@endif

@if($plan && count($installmentPlans))
  {{-- Print Plan options modal --}}
  <div class="modal fade" id="printPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-simple modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Print Payment Plan</h4>
            <p class="text-muted mb-0">Choose how you'd like to print this payment plan.</p>
          </div>


          {{-- Include installment plan toggle --}}
          <div class="mb-3">
            <label class="form-label fw-semibold">Include Installment Plan?</label>
            <div class="form-check form-switch">
              <input type="checkbox" class="form-check-input" id="print_include_installments" checked>
              <label class="form-check-label small" for="print_include_installments">Yes, include installment
                details</label>
            </div>
          </div>

          {{-- Mode selection (shown only when installments included) --}}
          <div id="print_mode_section" class="mb-3">
            <label class="form-label fw-semibold">Print Mode</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="print_mode" id="print_mode_simple" value="simple"
                       checked>
                <label class="form-check-label" for="print_mode_simple">
                  <i class="ti tabler-file-text text-primary me-1"></i> Simple
                  <div class="text-muted small text-tiny">Summary table format</div>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="print_mode" id="print_mode_brief" value="brief">
                <label class="form-check-label" for="print_mode_brief">
                  <i class="ti tabler-layout-list text-primary me-1"></i> Brief
                  <div class="text-muted small text-tiny">Detailed schedule cards</div>
                </label>
              </div>
            </div>
          </div>

          {{-- Installment plan selection --}}
          <div id="print_plans_section" class="mb-3">
            <label class="form-label fw-semibold">Select Plans to Print</label>
            <div class="border rounded-3 p-3 bg-label-secondary">
              @foreach($installmentPlans as $p)
                <div class="form-check mb-1">
                  <input class="form-check-input print-plan-checkbox" type="checkbox"
                         value="{{ $p['att']->uuid }}" id="print_plan_{{ $p['att']->uuid }}" checked>
                  <label class="form-check-label small" for="print_plan_{{ $p['att']->uuid }}">
                    {{ $p['name'] }}
                    <span
                      class="text-muted">({{ $p['number_of_installments'] }} {{ ucfirst($p['installment_frequency'] ?? '') }} installments)</span>
                  </label>
                </div>
              @endforeach
              <div class="form-check mt-2 pt-2 border-top">
                <input class="form-check-input" type="checkbox" id="print_select_all_plans" checked>
                <label class="form-check-label fw-semibold small" for="print_select_all_plans">Select All</label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="printPlanBtn" onclick="openPrintPlan()">
              <i class="ti tabler-printer me-1"></i> Print
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
@endif

@push('styles')
  <style>
    .planner {
      --radius: 0.75rem;
    }

    .micro-label {
      font-size: 0.62rem;
      font-weight: 600;
    }

    .text-tiny {
      font-size: 0.7rem;
    }

    .badge-xs {
      font-size: 0.6rem;
    }

    .tracking-wide {
      letter-spacing: 0.05em;
    }

    .section-caption {
      font-size: 0.7rem;
      letter-spacing: 0.04em;
    }

    .caption-hint {
      text-transform: none;
    }

    .schedule-scroll {
      max-height: 360px;
      overflow-y: auto;
    }

    .col-seq {
      width: 50px;
    }

    .summary-sticky {
      top: 1.5rem;
    }

    @media (min-width: 992px) {
      .summary-sticky {
        position: sticky;
      }
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

    .summary-divider {
      border-top: 1px solid rgba(255, 255, 255, 0.2);
    }

    .summary-select {
      width: auto;
      max-width: 180px;
    }

    .bg-gradient {
      background: linear-gradient(160deg, #7367f0 0%, #4f4f82 45%, #7367f0 100%) !important;
      box-shadow: 0 8px 24px rgba(13, 110, 253, 0.2);
    }
  </style>
@endpush

@push('scripts')
  <script>
    function openPrintPlan() {
      var propertyUuid = '{{ $property->uuid ?? "" }}';
      var includeInstallments = document.getElementById('print_include_installments').checked;

      var mode = 'simple';
      var selectedPlans = [];

      if (includeInstallments) {
        mode = document.querySelector('input[name="print_mode"]:checked').value;
        document.querySelectorAll('.print-plan-checkbox:checked').forEach(function(cb) {
          selectedPlans.push(cb.value);
        });
        if (selectedPlans.length === 0) {
          notify('Please select at least one installment plan.', 'warning');
          return;
        }
      }

      var url = '{{ route("payment_plan.print", ":uuid") }}'.replace(':uuid', propertyUuid);
      var params = new URLSearchParams();
      params.set('mode', includeInstallments ? mode : 'simple');
      params.set('include_installments', includeInstallments ? '1' : '0');

      if (includeInstallments) {
        params.set('plans', selectedPlans.join(','));
      }

      window.open(url + '?' + params.toString(), '_blank');
    }

    document.addEventListener('DOMContentLoaded', function() {
      var grossAmount = {{ $grossAmount }};
      var excludedTotal = {{ $excludedTotal }};
      var remainingAmount = {{ $remainingAmount }};

      // ── Print plan modal ──
      var includeToggle = document.getElementById('print_include_installments');
      var modeSection = document.getElementById('print_mode_section');
      var plansSection = document.getElementById('print_plans_section');
      var selectAll = document.getElementById('print_select_all_plans');

      if (includeToggle) {
        includeToggle.addEventListener('change', function() {
          var show = this.checked;
          if (modeSection) modeSection.style.display = show ? '' : 'none';
          if (plansSection) plansSection.style.display = show ? '' : 'none';
        });
      }

      if (selectAll) {
        selectAll.addEventListener('change', function() {
          var checked = this.checked;
          document.querySelectorAll('.print-plan-checkbox').forEach(function(cb) {
            cb.checked = checked;
          });
        });
      }

      // ── Summary sidebar ──
      function applySummary(view) {
        var viewSelect = document.getElementById('summary_view_select');
        if (viewSelect) viewSelect.value = String(view);

        var extraEl = document.getElementById('summary_extra');
        var totalBalloonEl = document.getElementById('summary_total_balloon');
        var installmentRemainingEl = document.getElementById('summary_installment_remaining');
        var finalAmountEl = document.getElementById('summary_final_amount');
        var perEl = document.getElementById('summary_per_installment');

        if (view === 'plan_only') {
          if (extraEl) extraEl.textContent = '0';
          if (totalBalloonEl) totalBalloonEl.textContent = '0';
          if (installmentRemainingEl) installmentRemainingEl.textContent = Number(remainingAmount).toLocaleString(undefined, { maximumFractionDigits: 0 });
          if (finalAmountEl) finalAmountEl.textContent = Number(grossAmount + excludedTotal).toLocaleString(undefined, { maximumFractionDigits: 0 });
          if (perEl) perEl.textContent = 'No installment plan selected.';
          return;
        }

        var pane = document.querySelector('.installment-pane[data-sequence="' + view + '"]');
        if (!pane) return;

        var extra = Number(pane.dataset.extra || 0);
        var totalBalloon = Number(pane.dataset.balloonAmount || 0);
        var installmentRemaining = Number(pane.dataset.installmentRemaining || 0);
        var perBalloon = Number(pane.dataset.perBalloon || 0);
        var perInstallment = Number(pane.dataset.perInstallment || 0);
        var num = pane.dataset.num || 0;

        if (extraEl) extraEl.textContent = extra.toLocaleString(undefined, { maximumFractionDigits: 0 });
        if (totalBalloonEl) totalBalloonEl.textContent = totalBalloon.toLocaleString(undefined, { maximumFractionDigits: 0 });
        if (installmentRemainingEl) installmentRemainingEl.textContent = installmentRemaining.toLocaleString(undefined, { maximumFractionDigits: 0 });
        if (finalAmountEl) finalAmountEl.textContent = (grossAmount + excludedTotal + extra).toLocaleString(undefined, { maximumFractionDigits: 0 });

        var perText = '';
        if (perBalloon > 0) {
          perText = 'Per balloon: ' + perBalloon.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' · ';
        }
        perText += 'Per installment: ' + perInstallment.toLocaleString(undefined, { maximumFractionDigits: 2 });
        perText += ' (' + num + ' installments)';
        if (perEl) perEl.textContent = perText;
      }

      function activateInstallmentPane(sequence) {
        const panes = document.querySelectorAll('.installment-pane');
        if (!panes.length) return;

        panes.forEach(pane => {
          pane.classList.toggle('d-none', pane.dataset.sequence !== String(sequence));
        });

        document.querySelectorAll('.installment-tab').forEach(tab => {
          const isActive = tab.dataset.sequence === String(sequence);
          tab.classList.toggle('btn-primary', isActive);
          tab.classList.toggle('btn-outline-primary', !isActive);
        });

        applySummary(sequence);
      }

      document.querySelectorAll('.installment-tab').forEach(tab => {
        tab.addEventListener('click', function() {
          activateInstallmentPane(this.dataset.sequence);
        });
      });

      var viewSelect = document.getElementById('summary_view_select');
      if (viewSelect) {
        viewSelect.addEventListener('change', function() {
          if (this.value === 'plan_only') {
            applySummary('plan_only');
          } else {
            activateInstallmentPane(this.value);
          }
        });
      }

      // ── Fill the edit-installment modal from the clicked button ──
      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.edit-installment-btn');
        if (!btn) return;

        let data;
        try {
          data = JSON.parse(btn.getAttribute('data-installment'));
        } catch (err) {
          console.error('Invalid installment data', err);
          return;
        }

        document.getElementById('editInstallmentForm').action = btn.getAttribute('data-action');
        document.getElementById('ei_name').value = data.name || '';
        document.getElementById('ei_number_of_installments').value = data.number_of_installments;
        document.getElementById('ei_installment_frequency').value = data.installment_frequency || '';
        document.getElementById('ei_additional_installment_amount').value = data.additional_installment_amount;
        document.getElementById('ei_additional_installment_amount_in_per').checked = Boolean(data.additional_installment_amount_in_per);
        document.querySelector('input[name=additional_installment_amount_in_per]').value = Boolean(data.additional_installment_amount_in_per) ? 1 : 0;
        document.getElementById('ei_balloon_payment').value = data.balloon_payment || '';
        document.getElementById('ei_balloon_payment_in_per').checked = Boolean(data.balloon_payment_in_per);
        document.querySelector('input[name=balloon_payment_in_per]').value = Boolean(data.balloon_payment_in_per) ? 1 : 0;
        document.getElementById('ei_balloon_payment_frequency').value = data.balloon_payment_frequency || '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('editInstallmentModal')).show();
      });

      // ── Template auto-fill ──
      var applyFeeFields = [
        { key: 'booking_fee', per: true, surplus: false },
        { key: 'development_fee', per: true, surplus: true },
        { key: 'down_payment', per: true, surplus: false },
        { key: 'transfer_fee', per: true, surplus: true },
        { key: 'confirmation_fee', per: true, surplus: false },
        { key: 'possession_fee', per: true, surplus: true }
      ];
      var applyPremiumFields = ['corner_premium', 'park_facing_premium', 'main_boulevard_premium'];
      var isFillingFromTemplate = false;

      function fillChargesFromTemplate(opt, prefix) {
        function dataAttr(key) {
          return opt ? opt.getAttribute('data-' + key.replace(/_/g, '-')) : null;
        }

        isFillingFromTemplate = true;

        applyFeeFields.forEach(function(f) {
          var valueInput = document.querySelector(prefix + ' input[name="' + f.key + '"]');
          if (valueInput) {
            valueInput.value = dataAttr(f.key) !== null ? dataAttr(f.key) : '';
          }
          if (f.per) {
            var perInput = document.querySelector(prefix + ' input[name="' + f.key + '_in_per"]');
            if (perInput) perInput.checked = dataAttr(f.key + '-in-per') === '1';
          }
          if (f.surplus) {
            var surplusInput = document.querySelector(prefix + ' input[name="' + f.key + '_surplus"]');
            if (surplusInput) surplusInput.checked = dataAttr(f.key + '-surplus') === '1';
          }
        });

        applyPremiumFields.forEach(function(key) {
          var valueInput = document.querySelector(prefix + ' input[name="' + key + '"]');
          if (valueInput) {
            valueInput.value = dataAttr(key) !== null ? dataAttr(key) : '';
          }
        });

        var modifiedInput = document.getElementById('template_modified');
        if (modifiedInput) modifiedInput.value = '0';

        isFillingFromTemplate = false;
      }

      document.addEventListener('change', function(e) {
        if (e.target.id === 'apply_payment_template') {
          fillChargesFromTemplate(e.target.selectedOptions[0], '#applyPlanModal');
          return;
        }

        if (e.target.id === 'edit_payment_template') {
          fillChargesFromTemplate(e.target.selectedOptions[0], '#editPlanModal');
          return;
        }

        if (!isFillingFromTemplate && e.target.classList.contains('apply-charge-field')) {
          var modifiedInput = document.getElementById('template_modified');
          if (modifiedInput) modifiedInput.value = '1';
        }
      });

      document.addEventListener('input', function(e) {
        if (!isFillingFromTemplate && e.target.classList.contains('apply-charge-field')) {
          var modifiedInput = document.getElementById('template_modified');
          if (modifiedInput) modifiedInput.value = '1';
        }
      });

      // ── Form validation ──
      var editPlanForm = document.getElementById('editPlanForm');
      var editInstallmentForm = document.getElementById('editInstallmentForm');

      function showError(form, id, msg) {
        var input = form.querySelector('#' + id);
        if (!input) return;
        input.classList.add('is-invalid');
        var err = form.querySelector('#' + id + '_error');
        if (err) {
          err.textContent = msg;
          err.style.display = 'block';
        }
      }

      function clearError(form, id) {
        var input = form.querySelector('#' + id);
        if (input) input.classList.remove('is-invalid');
        var err = form.querySelector('#' + id + '_error');
        if (err) {
          err.textContent = '';
          err.style.display = 'none';
        }
      }

      function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function(el) {
          el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.invalid-feedback').forEach(function(el) {
          el.textContent = '';
          el.style.display = 'none';
        });
      }

      function validatePercent(form, id, isPercent) {
        var input = form.querySelector('#' + id);
        if (!input || !input.value || input.value.trim() === '') {
          clearError(form, id);
          return true;
        }
        var val = parseFloat(input.value);
        if (isPercent && val > 100) {
          showError(form, id, 'Percentage must be between 0-100');
          return false;
        }
        clearError(form, id);
        return true;
      }

      if (editInstallmentForm) {
        var eiAddInput = document.getElementById('ei_additional_installment_amount');
        var eiAddToggle = document.getElementById('ei_additional_installment_amount_in_per');
        var eiBallInput = document.getElementById('ei_balloon_payment');
        var eiBallToggle = document.getElementById('ei_balloon_payment_in_per');

        var eiValidateAdd = function() {
          return validatePercent(editInstallmentForm, 'ei_additional_installment_amount', eiAddToggle && eiAddToggle.checked);
        };
        var eiValidateBall = function() {
          return validatePercent(editInstallmentForm, 'ei_balloon_payment', eiBallToggle && eiBallToggle.checked);
        };

        if (eiAddInput) {
          eiAddInput.addEventListener('input', function() {
            if (eiAddToggle && eiAddToggle.checked) eiValidateAdd();
          });
        }
        if (eiAddToggle) eiAddToggle.addEventListener('change', eiValidateAdd);
        if (eiBallInput) {
          eiBallInput.addEventListener('input', function() {
            if (eiBallToggle && eiBallToggle.checked) eiValidateBall();
          });
        }
        if (eiBallToggle) eiBallToggle.addEventListener('change', eiValidateBall);

        editInstallmentForm.addEventListener('submit', function(e) {
          clearErrors(editInstallmentForm);

          var balloonValue = eiBallInput ? eiBallInput.value.trim() : '';
          var balloonFreq = document.getElementById('ei_balloon_payment_frequency');
          if (balloonValue && (!balloonFreq || !balloonFreq.value)) {
            showError(editInstallmentForm, 'ei_balloon_payment_frequency', 'Required when balloon payment is set');
            e.preventDefault();
            return false;
          }

          var ok = eiValidateAdd();
          if (!eiValidateBall()) ok = false;
          if (!ok) {
            e.preventDefault();
            return false;
          }
        });
      }

      if (editPlanForm) {
        var planFeeKeys = ['booking_fee', 'development_fee', 'down_payment', 'transfer_fee', 'confirmation_fee', 'possession_fee'];
        var planPremiumKeys = ['corner_premium', 'park_facing_premium', 'main_boulevard_premium'];

        var planValidateFee = function(key) {
          var toggle = editPlanForm.querySelector('#in_per_' + key);
          return validatePercent(editPlanForm, key, !!toggle && toggle.checked);
        };
        var planValidatePremium = function(key) {
          return validatePercent(editPlanForm, key, true);
        };

        planFeeKeys.forEach(function(key) {
          var input = document.getElementById(key);
          if (!input) return;
          input.addEventListener('input', function() {
            var toggle = document.getElementById('in_per_' + key);
            if (toggle && toggle.checked) planValidateFee(key);
          });
        });

        planPremiumKeys.forEach(function(key) {
          var input = document.getElementById(key);
          if (!input) return;
          input.addEventListener('input', function() {
            planValidatePremium(key);
          });
        });

        editPlanForm.querySelectorAll('input[type=checkbox]').forEach(function(cb) {
          cb.addEventListener('change', function() {
            var m = cb.id.match(/^in_per_(.+)$/);
            if (m) planValidateFee(m[1]);
          });
        });

        editPlanForm.addEventListener('submit', function(e) {
          clearErrors(editPlanForm);
          var ok = true;
          planFeeKeys.forEach(function(key) {
            if (!planValidateFee(key)) ok = false;
          });
          planPremiumKeys.forEach(function(key) {
            if (!planValidatePremium(key)) ok = false;
          });
          if (!ok) {
            e.preventDefault();
            return false;
          }
        });
      }
    });
  </script>
@endpush
