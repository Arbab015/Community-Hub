@php
  $isUnit = $property instanceof \App\Models\PropertyUnit;
  $entityLabel = $isUnit ? 'Unit' : 'Property';
  $ownerableType = $isUnit ? 'App\\Models\\PropertyUnit' : 'App\\Models\\Property';
  $ownerableId = $property->id;

  $ownershipPlan = $property->ownershipPaymentPlans()
      ->whereHas('propertyOwner', fn ($q) => $q->whereNull('end_date'))
      ->latest('id')
      ->first();

  if (! $ownershipPlan) return;

  $data = \App\Support\PaymentPlanPresenter::buildFromSnapshot($ownershipPlan);
  $unit = $data['listing_unit'];

  $allFeeRows = collect($data['fee_rows'] ?? [])->reject(fn ($r) => ($r['label'] ?? '') === 'Listed Price')->values();
  $excludedFees = $allFeeRows->where('note', 'Excluded')->values();
  $includedFees = $allFeeRows->where('note', 'Included')->values();
  $premiumRows = $data['premium_rows'] ?? [];
  $grossAmount = $data['gross_amount'];
  $excludedTotal = $data['excluded_total'];
  $includedTotal = $data['included_total'];
  $remainingAmount = $data['total_price'] - $includedTotal;

  $installment = $data['installment_rows'][0] ?? null;
  $extraAmount = $installment['additional_amount'] ?? 0;
  $totalBalloon = $installment['balloon'] ?? 0;
  $installmentRemaining = $installment['installment_remaining'] ?? $remainingAmount;

  $currentOwner = $property->owner;

  // Installment templates matching this property's category (used by the edit
  // modal prefiller). Also the base set for the "Add" dropdown below.
  $allInstallmentTemplates = collect($installmentTemplates ?? collect())
      ->filter(fn ($tpl) => (bool) $tpl->is_commercial === (($isUnit
          ? optional($property->floor?->property)->category
          : optional($property)->category) === 'commercial'))
      ->values();

  // Installment-plan sources for the "Add Installment Plan" modal.
  // 1) Plans already attached to this property's payment plan
  //    (property_installment_plans) — including custom ones (template id null).
  // 2) Installment templates matching this property's category, minus the ones
  //    already attached so the same template/plan name never repeats.
  $sourcePlan = $property->paymentPlan;
  $planAttachments = collect($sourcePlan?->installmentAttachments ?? []);

  $attachedTemplateIds = $planAttachments->pluck('installment_template_id')->filter()->all();
  $attachedNames = $planAttachments
      ->map(fn ($att) => mb_strtolower(trim((string) (($att->name ?? '') ?: ($att->installmentTemplate?->name ?? '')))))
      ->filter()
      ->all();

  $addInstallmentTemplateOptions = $allInstallmentTemplates
      ->reject(fn ($tpl) => in_array($tpl->id, $attachedTemplateIds))
      ->reject(fn ($tpl) => in_array(mb_strtolower(trim((string) $tpl->name)), $attachedNames))
      ->values();

  // Read a snapshot/template field, falling back to the linked template.
  $needInstallmentValue = function ($att, $field) {
      $value = $att->{$field} ?? null;
      if ($value !== null && $value !== '') {
          return $value;
      }

      return $att->installmentTemplate?->{$field} ?? null;
  };

  // Compact useful summary: "12x Monthly · +2 Balloon".
  $installmentSummary = function ($num, $freq, $hasBalloon) {
      $parts = [];
      if ((int) $num > 0) {
          $parts[] = (int) $num.'x '.ucfirst($freq ?: 'installments');
      }
      if ($hasBalloon) {
          $parts[] = '+ Balloon';
      }

      return implode(' · ', $parts) ?: 'Installment Plan';
  };

  $addInstallmentOptions = collect();

  // Attached plans first — marked as "applied" to the property's payment plan.
  foreach ($planAttachments as $att) {
      $attInfo = $installmentSummary(
          $needInstallmentValue($att, 'number_of_installments'),
          $needInstallmentValue($att, 'installment_frequency'),
          (float) ($needInstallmentValue($att, 'balloon_payment') ?? 0) > 0
      );
      $addInstallmentOptions->push([
          'source' => 'attachment:'.$att->id,
          'name' => trim((string) (($att->name ?? '') ?: ($att->installmentTemplate?->name ?? ''))) ?: 'Installment Plan',
          'info' => $attInfo,
          'applied' => true,
          'num' => (int) ($needInstallmentValue($att, 'number_of_installments') ?? 0),
          'frequency' => $needInstallmentValue($att, 'installment_frequency') ?? '',
          'additional' => $needInstallmentValue($att, 'additional_installment_amount') ?? 0,
          'additional_in_per' => (int) ($needInstallmentValue($att, 'additional_installment_amount_in_per') ?? 0),
          'balloon' => $needInstallmentValue($att, 'balloon_payment') ?? 0,
          'balloon_in_per' => (int) ($needInstallmentValue($att, 'balloon_payment_in_per') ?? 0),
          'balloon_frequency' => $needInstallmentValue($att, 'balloon_payment_frequency') ?? '',
      ]);
  }

  // Available category-matched templates.
  foreach ($addInstallmentTemplateOptions as $tpl) {
      $tplInfo = $installmentSummary(
          $tpl->number_of_installments,
          $tpl->installment_frequency,
          (float) ($tpl->balloon_payment ?? 0) > 0
      );
      $addInstallmentOptions->push([
          'source' => 'template:'.$tpl->id,
          'name' => $tpl->name,
          'info' => $tplInfo,
          'applied' => false,
          'num' => (int) ($tpl->number_of_installments ?? 0),
          'frequency' => $tpl->installment_frequency ?? '',
          'additional' => $tpl->additional_installment_amount ?? 0,
          'additional_in_per' => (int) ($tpl->additional_installment_amount_in_per ?? 0),
          'balloon' => $tpl->balloon_payment ?? 0,
          'balloon_in_per' => (int) ($tpl->balloon_payment_in_per ?? 0),
          'balloon_frequency' => $tpl->balloon_payment_frequency ?? '',
      ]);
  }
@endphp

<div class="planner">
  <div class="row g-4">

    {{-- Main column --}}
    <div class="col-lg-8 order-2 order-lg-1">

      {{-- Owner / Member Section --}}
      @if($currentOwner)
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div class="d-flex align-items-center gap-2">
              <i class="ti tabler-user text-primary fs-5"></i>
              <h5 class="fw-bold mb-0">Owner / Member</h5>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <a href="{{ route('property_owners.plan.print', $ownershipPlan) }}?mode=brief" target="_blank"
                 class="btn btn-sm btn-outline-primary" title="Print the committed payment plan for this owner">
                <i class="ti tabler-printer me-1"></i> Print Owner Plan
              </a>
            </div>
          </div>

          <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0">
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
        </div>
      @endif

      {{-- Charges Section (read-only snapshot) --}}
      <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
          <div class="d-flex align-items-center gap-2">
            <i class="ti tabler-receipt text-primary fs-5"></i>
            <h5 class="fw-bold mb-0">Charges</h5>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-label-success d-inline-flex align-items-center gap-1">
              <i class="ti tabler-circle-check-filled"></i> Committed Plan
            </span>
            @can('payment_plan_edit')
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                      data-bs-target="#editOwnershipPlanModal">
                <i class="ti tabler-edit me-1"></i> Edit Values
              </button>
            @endcan
          </div>
        </div>

        <div class="border rounded-3 p-3 mb-3 bg-label-secondary">
          <div class="row g-3">
            <div class="col-md-6">
              <small class="micro-label text-muted text-uppercase d-block mb-1">Listed Price</small>
              <div class="fw-semibold small">{{ number_format($data['listing_price'], 2) }} {{ $unit }}</div>
            </div>
            <div class="col-md-6">
              <small class="micro-label text-muted text-uppercase d-block mb-1">Final Price</small>
              <div class="fw-semibold small">{{ number_format($data['total_price'], 2) }} {{ $unit }}</div>
            </div>
          </div>
        </div>

        @if($premiumRows)
          <div class="row g-3 mb-3">
            @foreach($premiumRows as $pc)
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 border-success border-opacity-25 bg-success bg-opacity-10">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                      <span class="rounded bg-primary bg-opacity-10 p-2 d-inline-flex flex-shrink-0">
                        <i class="ti tabler-star text-primary"></i>
                      </span>
                      <div class="min-w-0">
                        <div class="fw-semibold small">{{ $pc['label'] }}</div>
                        <div class="text-muted text-tiny">{{ $pc['detail'] }}</div>
                      </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                      <div class="fw-bold">{{ number_format($pc['amount'], 2) }} {{ $unit }}</div>
                      <span class="badge badge-xs bg-danger">Premium</span>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @endif

        @if($excludedFees->count() || $includedFees->count())
          <div class="row g-3">
            @foreach($excludedFees as $fee)
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 border-success border-opacity-25 bg-success bg-opacity-10">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                      <span class="rounded bg-primary bg-opacity-10 p-2 d-inline-flex flex-shrink-0">
                        <i class="ti tabler-receipt text-primary"></i>
                      </span>
                      <div class="min-w-0">
                        <div class="fw-semibold small">{{ $fee['label'] }}</div>
                        <div class="text-muted text-tiny">{{ $fee['detail'] }}</div>
                      </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                      <div class="fw-bold">{{ number_format($fee['amount'], 2) }} {{ $unit }}</div>
                      <span class="badge badge-xs bg-danger">Excluded (on top)</span>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
            @foreach($includedFees as $fee)
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                      <span class="rounded bg-primary bg-opacity-10 p-2 d-inline-flex flex-shrink-0">
                        <i class="ti tabler-receipt text-primary"></i>
                      </span>
                      <div class="min-w-0">
                        <div class="fw-semibold small">{{ $fee['label'] }}</div>
                        <div class="text-muted text-tiny">{{ $fee['detail'] }}</div>
                      </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                      <div class="fw-bold">{{ number_format($fee['amount'], 2) }} {{ $unit }}</div>
                      <span class="badge badge-xs bg-primary">Included</span>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
            <i class="ti tabler-calendar-x fs-2 opacity-50 mb-2"></i>
            <small>No charges configured.</small>
          </div>
        @endif
      </div>

      {{-- Installment Section (single plan) --}}
      <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
          <div class="d-flex align-items-center gap-2">
            <i class="ti tabler-calendar-repeat text-primary fs-5"></i>
            <h5 class="fw-bold mb-0">Installment Plan</h5>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($installment)
              <span class="badge bg-label-primary">
                <i class="ti tabler-check me-1"></i>{{ $installment['name'] }}
              </span>
              @can('installment_plan_edit')
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#editOwnershipInstallmentModal">
                  <i class="ti tabler-edit me-1"></i> Edit Values
                </button>
              @endcan
              @can('installment_plan_delete')
                <form action="{{ route('ownership_payment_plans.installment.remove', $ownershipPlan) }}" method="POST"
                      onsubmit="return confirmDelete(event, null)">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-danger"
                          title="Remove the installment plan and reset the installment values to none">
                    <i class="ti tabler-trash me-1"></i> Remove Installment
                  </button>
                </form>
              @endcan
            @else
              @can('installment_plan_add')
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#addOwnershipInstallmentModal">
                  <i class="ti tabler-plus me-1"></i> Add Installment Plan
                </button>
              @endcan
            @endif
          </div>
        </div>

        @if($installment)
          <div class="row g-2 mb-3">
            <div class="col-sm-6 col-xl-3">
              <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                <small class="micro-label text-muted text-uppercase d-block mb-1">Installments</small>
                <div class="fw-semibold">{{ $installment['num'] }}
                  @if($installment['frequency'])
                    <span class="text-muted fw-normal">X· {{ ucfirst($installment['frequency']) }}</span>
                  @endif
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-xl-3">
              <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                <small class="micro-label text-muted text-uppercase d-block mb-1">Per Balloon</small>
                <div class="fw-semibold">
                  @if($installment['per_balloon'] > 0)
                    {{ number_format($installment['per_balloon'], 2) }} {{ $unit }}
                    @if($installment['balloon_count'] > 0)
                      <span class="text-muted fw-normal">({{ $installment['balloon_count'] }}x)</span>
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
                <div class="fw-semibold">{{ number_format($installment['per_installment'], 2) }} {{ $unit }}
                  @if(($installment['normal_count'] ?? 0) > 0)
                    <span class="text-muted fw-normal">({{ $installment['normal_count'] }}x)</span>
                  @endif
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-xl-3">
              <div class="border rounded-3 p-3 h-100 bg-label-secondary">
                <small class="micro-label text-muted text-uppercase d-block mb-1">Total Amount</small>
                <div class="fw-semibold">{{ number_format($installment['total'], 2) }} {{ $unit }}</div>
              </div>
            </div>
          </div>

          {{-- Installment schedule --}}
          <div class="border rounded-3 overflow-hidden">
            <div class="px-3 py-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-between">
              <span class="fw-semibold small text-primary">Installment Schedule</span>
              <span
                class="small text-muted">{{ $installment['num'] }} {{ $installment['num'] > 1 ? 'installments' : 'installment' }} · {{ ucfirst($installment['frequency'] ?? '—') }}
                @if($installment['balloon_count'] > 0)
                  · {{ $installment['balloon_count'] }} balloon{{ $installment['balloon_count'] > 1 ? 's' : '' }}
                @endif
              </span>
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
                @foreach($installment['schedule'] as $seq => $row)
                  <tr class="{{ $row['type'] === 'balloon' ? 'bg-label-primary' : '' }}">
                    <td class="ps-3">{{ $seq + 1 }}</td>
                    <td class="ps-3 text-heading">
                      @if($row['type'] === 'balloon')
                        Balloon #{{ $row['balloon_no'] }}
                        @if($installment['balloon_frequency'])
                          <span class="text-muted fw-normal">({{ ucfirst($installment['balloon_frequency']) }})</span>
                        @endif
                      @else
                        Installment #{{ $row['no'] }}
                      @endif
                    </td>
                    <td class="ps-3 fw-semibold">{{ number_format($row['amount'], 2) }} {{ $unit }}</td>
                    <td class="ps-3 text-muted">{{ number_format($row['cumulative'], 2) }} {{ $unit }}</td>
                  </tr>
                @endforeach
                <tr class="table-light">
                  <td class="ps-3 text-heading fw-bold" colspan="2">Total Payable</td>
                  <td class="ps-3 fw-bold">{{ number_format($data['final_price'], 2) }} {{ $unit }}</td>
                  <td class="ps-3 text-muted">—</td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>
        @else
          <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
            <i class="ti tabler-calendar-x fs-2 opacity-50 mb-2"></i>
            <small class="text-center">
              No installment plan attached to this committed plan yet.
              <br>Click <b>Add Installment Plan</b> above to attach one.
            </small>
          </div>
        @endif
      </div>
    </div>

    {{-- Summary sidebar --}}
    <div class="col-lg-4 order-1 order-lg-2">
      <div class="summary-sticky">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-3 bg-light">
          <p class="text-uppercase small text-dark mb-2 fw-bold tracking-wide">Listed Value</p>
          <div class="d-flex align-items-baseline gap-2">
            <h3 class="fw-bold mb-0">{{ number_format($data['listing_price'], 0) }}</h3>
            <span class="text-muted fs-6">{{ $unit }}</span>
          </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-4 bg-gradient text-white plan-summary-card">
          <p class="text-uppercase small mb-3 fw-bold plan-summary-title">Plan Summary</p>

          <div class="d-flex justify-content-between mb-2">
            <span class="small">Listed Price</span>
            <span class="fw-semibold fs-6">{{ number_format($data['listing_price'], 0) }}</span>
          </div>

          @if(count($premiumRows))
            <span class="small d-block">Feature Premiums <span class="opacity-75">(% of listed)</span></span>
            <ul class="list-unstyled mb-1 ps-0 summary-list">
              @foreach($premiumRows as $pc)
                <li class="d-flex justify-content-between">
                  <span>{{ $pc['label'] }}</span>
                  <span>{{ number_format($pc['amount'], 0) }}</span>
                </li>
              @endforeach
            </ul>
            <div class="d-flex justify-content-between fw-semibold small mb-1">
              <span>Total Premiums</span>
              <span>{{ number_format($data['premiums_total'], 0) }}</span>
            </div>
          @endif

          <div class="d-flex justify-content-between fw-semibold small">
            <span class="small">Gross Amount</span>
            <span class="mb-2 bg-label-secondary px-2 rounded text-dark">{{ number_format($grossAmount, 0) }}</span>
          </div>

          <hr class="opacity-25">

          <span class="small d-block mb-1">One Time Charges <span class="opacity-75">(excluded + included)</span></span>

          <div class="ps-2 border-start border-2 opacity-90 mb-2">
            <span class="small d-block mb-1">Excluded Charges <span class="opacity-75">(on top of gross)</span></span>
            <ul class="list-unstyled mb-0 ps-0 summary-list">
              @forelse($excludedFees as $c)
                <li class="d-flex justify-content-between">
                  <span>{{ $c['label'] }}</span>
                  <span>{{ number_format($c['amount'], 0) }}</span>
                </li>
              @empty
                <li class="opacity-50">None</li>
              @endforelse
            </ul>
            <div class="d-flex justify-content-between fw-semibold small mt-1">
              <span>Total Excluded</span>
              <span class="bg-label-secondary px-2 rounded text-dark">{{ number_format($excludedTotal, 0) }}</span>
            </div>

            <span class="small d-block mb-1 mt-2">Included Charges <span class="opacity-75">(in gross)</span></span>
            <ul class="list-unstyled mb-0 ps-0 summary-list">
              @forelse($includedFees as $c)
                <li class="d-flex justify-content-between">
                  <span>{{ $c['label'] }}</span>
                  <span>{{ number_format($c['amount'], 0) }}</span>
                </li>
              @empty
                <li class="opacity-50">None</li>
              @endforelse
            </ul>
            <div class="d-flex justify-content-between fw-semibold small mt-1">
              <span>Total Included</span>
              <span>{{ number_format($includedTotal, 0) }}</span>
            </div>
          </div>

          <div class="d-flex justify-content-between fw-semibold small">
            <span>Total One-time</span>
            <span>{{ number_format($excludedTotal + $includedTotal, 0) }}</span>
          </div>

          <div class="d-flex justify-content-between fw-bold small mt-2">
            <span class="small">Remaining Amount</span>
            <span>{{ number_format($remainingAmount, 0) }}</span>
          </div>

          <hr class="opacity-25">

          @if($installment)
            <div class="d-flex justify-content-between">
              <span class="small">Extra in Case of Installment</span>
              <span
                class="fw-semibold fs-6 bg-label-secondary px-2 rounded text-dark">{{ number_format($extraAmount, 0) }}</span>
            </div>

            <div class="d-flex justify-content-between">
              <span class="small">Total Balloon</span>
              <span class="fw-semibold fs-6">{{ number_format($totalBalloon, 0) }}</span>
            </div>

            <div class="d-flex justify-content-between mb-2">
              <span class="small">Remaining for Installment</span>
              <span class="fw-semibold fs-6">{{ number_format($installmentRemaining, 0) }}</span>
            </div>
          @endif

          <div class="d-flex justify-content-between align-items-center fs-5 fw-bold mb-2 summary-final-row">
            <span>Final Amount</span>
            <span class="bg-label-secondary px-2 rounded text-dark">{{ number_format($data['final_price'], 0) }}</span>
          </div>

          @if($installment)
            <div class="small d-block opacity-75">
              @if($installment['per_balloon'] > 0)
                Per balloon: {{ number_format($installment['per_balloon'], 2) }} {{ $unit }}
                · Per installment: {{ number_format($installment['per_installment'], 2) }} {{ $unit }}
              @else
                Per installment: {{ number_format($installment['per_installment'], 2) }} {{ $unit }}
              @endif
              ({{ $installment['num'] }} installments)
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Edit Ownership Plan Modal --}}
@if(Auth()->user()->can('payment_plan_edit'))
  <div class="modal fade" id="editOwnershipPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-simple modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Edit Committed Plan</h4>
            <p class="text-muted mb-0">Update the committed charge values for this owner's plan.</p>
          </div>

          <form action="{{ route('ownership_payment_plans.update', $ownershipPlan) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold">Listed Price</label>
                <input type="number" name="listed_price" class="form-control" step="0.01" min="0"
                       value="{{ $ownershipPlan->listed_price }}">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Currency</label>
                <select name="price_unit" class="form-select">
                  <option value="PKR" @selected($ownershipPlan->price_unit === 'PKR')>PKR</option>
                  <option value="USD" @selected($ownershipPlan->price_unit === 'USD')>USD</option>
                </select>
              </div>
            </div>

            <hr class="my-2">
            <p class="section-caption fw-semibold small text-uppercase text-muted mb-3">
              Charge Values
              <span class="caption-hint fw-normal">(leave blank to keep current)</span>
            </p>

            <div class="row g-3">
              @foreach([
                  ['key' => 'booking_fee', 'label' => 'Booking Fee', 'surplus' => false],
                  ['key' => 'development_fee', 'label' => 'Development Fee', 'surplus' => true],
                  ['key' => 'down_payment', 'label' => 'Down Payment', 'surplus' => false],
                  ['key' => 'transfer_fee', 'label' => 'Transfer Fee', 'surplus' => true],
                  ['key' => 'confirmation_fee', 'label' => 'Confirmation Fee', 'surplus' => false],
                  ['key' => 'possession_fee', 'label' => 'Possession Fee', 'surplus' => true],
              ] as $fk)
                <div class="col-md-6">
                  <div class="border rounded-3 p-3 bg-label-secondary h-100">
                    <label class="form-label fw-semibold mb-2">{{ $fk['label'] }}</label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <input type="number" name="{{ $fk['key'] }}" class="form-control" step="0.01" min="0"
                             placeholder="{{ $unit }} or %" value="{{ $ownershipPlan->{$fk['key']} }}">
                      <div class="form-check form-switch mb-0 flex-shrink-0">
                        <input type="checkbox" name="{{ $fk['key'] }}_in_per" class="form-check-input"
                               value="1" {{ $ownershipPlan->{$fk['key'].'_in_per'} ? 'checked' : '' }}>
                        <label class="form-check-label small text-nowrap">%</label>
                      </div>
                    </div>
                    @if($fk['surplus'])
                      <div class="form-check">
                        <input type="checkbox" name="{{ $fk['key'] }}_surplus" class="form-check-input"
                               value="1" {{ $ownershipPlan->{$fk['key'].'_surplus'} ? 'checked' : '' }}>
                        <label class="form-check-label small">Additional (on top of gross)</label>
                      </div>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>

            <hr class="my-2">
            <p class="section-caption fw-semibold small text-uppercase text-muted mb-3">
              Feature Premiums <span class="caption-hint fw-normal">(% of listed price)</span>
            </p>

            <div class="row g-3 mb-3">
              @foreach([
                  ['key' => 'corner_premium', 'label' => 'Corner Premium'],
                  ['key' => 'park_facing_premium', 'label' => 'Park Facing Premium'],
                  ['key' => 'main_boulevard_premium', 'label' => 'Main Boulevard Premium'],
              ] as $pk)
                <div class="col-md-4">
                  <div class="border rounded-3 p-3 bg-label-secondary h-100">
                    <label class="form-label fw-semibold mb-2">{{ $pk['label'] }}</label>
                    <div class="input-group">
                      <input type="number" name="{{ $pk['key'] }}" class="form-control" step="0.01" min="0" max="100"
                             placeholder="%" value="{{ $ownershipPlan->{$pk['key']} }}">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">
                <i class="ti tabler-device-floppy me-1"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endif

{{-- Add Ownership Installment Modal --}}
@if(! $installment && Auth()->user()->can('installment_plan_add'))
  <div class="modal fade" id="addOwnershipInstallmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-simple modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Add Installment Plan</h4>
            <p class="text-muted mb-0">Pick an installment plan to attach to this committed plan.</p>
          </div>

          @if($addInstallmentOptions->count())
            <form action="{{ route('ownership_payment_plans.installment.store', $ownershipPlan) }}" method="POST">
              @csrf

              <div class="mb-3">
                <label class="form-label fw-semibold">
                  Installment Plan
                  @if($addInstallmentOptions->contains('applied', true))
                    <span class="text-muted fw-normal">(Applied = already attached to this property's payment plan)</span>
                  @endif
                </label>
                <select id="addOwnershipInstallmentSelect" name="installment_source"
                        class="form-select installment-template-select"
                        data-preview="addOwnershipInstallmentPreview" required>
                  <option value="">Select an installment plan</option>

                  @foreach($addInstallmentOptions as $opt)
                    <option value="{{ $opt['source'] }}"
                            data-name="{{ $opt['name'] }}"
                            data-number-of-installments="{{ $opt['num'] }}"
                            data-frequency="{{ $opt['frequency'] }}"
                            data-additional="{{ $opt['additional'] }}"
                            data-additional-in-per="{{ $opt['additional_in_per'] }}"
                            data-balloon="{{ $opt['balloon'] }}"
                            data-balloon-in-per="{{ $opt['balloon_in_per'] }}"
                            data-balloon-frequency="{{ $opt['balloon_frequency'] }}">
                      {{ $opt['name'] }} — {{ $opt['info'] }}{{ $opt['applied'] ? ' (Applied)' : '' }}
                    </option>
                  @endforeach
                </select>

                <div id="addOwnershipInstallmentPreview" class="mt-3 d-none"
                     data-price-base="{{ $grossAmount }}"
                     data-included-total="{{ $includedTotal }}"
                     data-excluded-total="{{ $excludedTotal }}">
                  <div class="it-preview-list"></div>
                </div>

                <small class="text-muted d-block mt-2">
                  Shows all available installment plans. <b>Applied</b> plans are already attached to this
                  property's payment plan (including custom ones); the rest are category-matched templates you
                  can apply on top.
                </small>
              </div>

              <div class="d-flex justify-content-end gap-2 pt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                  <i class="ti tabler-plus me-1"></i> Attach Plan
                </button>
              </div>
            </form>
          @else
            <div class="border rounded-3 p-3 bg-label-secondary">
              <small class="text-muted">No installment plans or templates available for this category.</small>
            </div>
            <div class="d-flex justify-content-end gap-2 pt-3">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endif

{{-- Edit Ownership Installment Modal --}}
@if($installment && Auth()->user()->can('installment_plan_edit'))
  <div class="modal fade" id="editOwnershipInstallmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-simple  modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          <div class="text-center mb-4">
            <h4 class="mb-2 fw-bolder">Edit Installment Plan</h4>
            <p class="text-muted mb-0">Update the installment values for this committed plan.</p>
          </div>

          <form action="{{ route('ownership_payment_plans.installment.update', $ownershipPlan) }}" method="POST">
            @csrf
            @method('PUT')

            {{-- Prefill from template --}}
            <div class="mb-3">
              <label class="form-label fw-semibold">Load from Template <span
                  class="text-muted">(optional)</span></label>
              <select id="ownEditInstallmentTemplate" class="form-select">
                <option value="">Keep current values</option>
                @foreach($allInstallmentTemplates as $tpl)
                  <option value="{{ $tpl->id }}"
                          data-name="{{ $tpl->name }}"
                          data-number-of-installments="{{ $tpl->number_of_installments }}"
                          data-installment-frequency="{{ $tpl->installment_frequency }}"
                          data-additional-installment-amount="{{ $tpl->additional_installment_amount }}"
                          data-additional-installment-amount-in-per="{{ $tpl->additional_installment_amount_in_per ? 1 : 0 }}"
                          data-balloon-payment="{{ $tpl->balloon_payment }}"
                          data-balloon-payment-in-per="{{ $tpl->balloon_payment_in_per ? 1 : 0 }}"
                          data-balloon-payment-frequency="{{ $tpl->balloon_payment_frequency }}">
                    {{ $tpl->name }}
                  </option>
                @endforeach
              </select>
              <small class="text-muted">Selecting a template will overwrite the fields below with that template's
                values.</small>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Plan Name</label>
              <input type="text" name="name" id="ownEiName" class="form-control"
                     value="{{ $ownershipPlan->name }}">
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Number of Installments</label>
                <input type="number" name="number_of_installments" id="ownEiNumberOfInstallments" class="form-control"
                       min="1" value="{{ $ownershipPlan->number_of_installments }}">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Frequency</label>
                <select name="installment_frequency" id="ownEiFrequency" class="form-select">
                  @foreach(['monthly', 'quarterly', 'biannually', 'annually'] as $f)
                    <option
                      value="{{ $f }}" @selected($ownershipPlan->installment_frequency === $f)>{{ ucfirst($f) }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold">Additional Installment Amount</label>
                <input type="number" name="additional_installment_amount" id="ownEiAdditionalAmount"
                       class="form-control" step="0.01" min="0"
                       value="{{ $ownershipPlan->additional_installment_amount }}">
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch">
                  <input type="checkbox" name="additional_installment_amount_in_per" id="ownEiAdditionalInPer"
                         class="form-check-input" value="1"
                    {{ $ownershipPlan->additional_installment_amount_in_per ? 'checked' : '' }}>
                  <label class="form-check-label small">% of gross</label>
                </div>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold">Balloon Payment</label>
                <input type="number" name="balloon_payment" id="ownEiBalloonPayment" class="form-control"
                       step="0.01" min="0" value="{{ $ownershipPlan->balloon_payment }}">
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch">
                  <input type="checkbox" name="balloon_payment_in_per" id="ownEiBalloonInPer" class="form-check-input"
                         value="1" {{ $ownershipPlan->balloon_payment_in_per ? 'checked' : '' }}>
                  <label class="form-check-label small">% of gross</label>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Balloon Payment Frequency</label>
              <select name="balloon_payment_frequency" id="ownEiBalloonFrequency" class="form-select">
                <option value="" @selected(! $ownershipPlan->balloon_payment_frequency)>None</option>
                @foreach(['quarterly', 'biannually', 'annually'] as $f)
                  <option
                    value="{{ $f }}" @selected($ownershipPlan->balloon_payment_frequency === $f)>{{ ucfirst($f) }}</option>
                @endforeach
              </select>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">
                <i class="ti tabler-device-floppy me-1"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endif

@push('scripts')
  <script>
    document.getElementById('ownEditInstallmentTemplate')?.addEventListener('change', function() {
      var opt = this.selectedOptions[0];
      if (!opt || !opt.value) return;
      var fields = {
        'ownEiName': opt.getAttribute('data-name'),
        'ownEiNumberOfInstallments': opt.getAttribute('data-number-of-installments'),
        'ownEiFrequency': opt.getAttribute('data-installment-frequency'),
        'ownEiAdditionalAmount': opt.getAttribute('data-additional-installment-amount'),
        'ownEiBalloonPayment': opt.getAttribute('data-balloon-payment'),
        'ownEiBalloonFrequency': opt.getAttribute('data-balloon-payment-frequency')
      };
      for (var id in fields) {
        var el = document.getElementById(id);
        if (el && fields[id] !== null) el.value = fields[id];
      }
      var addPer = document.getElementById('ownEiAdditionalInPer');
      if (addPer) addPer.checked = opt.getAttribute('data-additional-installment-amount-in-per') === '1';
      var balloonPer = document.getElementById('ownEiBalloonInPer');
      if (balloonPer) balloonPer.checked = opt.getAttribute('data-balloon-payment-in-per') === '1';
    });
  </script>
@endpush
