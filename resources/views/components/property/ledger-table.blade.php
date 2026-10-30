@php
  $showActions = $showActions ?? true;
  $canCollect = \Illuminate\Support\Facades\Gate::allows('payment_collect');
  $actionCol = ($canCollect && $showActions) ? 1 : 0;
  // Charge selection is an action, so it only appears where actions are allowed.
  $checkCol = $actionCol;
  // The table is included once per ownership, so ids have to stay table-scoped.
  $tableId = $tableId ?? 'ledger';
  $colspan = 7 + $actionCol + $checkCol;
  $payableRows = $ledgerRows->filter(fn ($r) => (float) $r->amount_paid > 0)->count();
@endphp

@if($checkCol)
  <div class="d-none align-items-center gap-2 px-3 py-2 mb-2 border rounded-3 bg-label-primary"
       data-unpay-bar="{{ $tableId }}">
    <i class="ti tabler-alert-triangle text-primary"></i>
    <span class="small">
      <span class="fw-semibold" data-selected-count="{{ $tableId }}">0</span>
      charge(s) selected &middot;
      <span class="fw-semibold" data-selected-total="{{ $tableId }}">0.00</span> {{ $currency }}
    </span>
    <button type="button" class="btn btn-sm btn-primary ms-auto" data-unpay-selected="{{ $tableId }}">
      <i class="ti tabler-rotate-clockwise-2 me-1"></i> Unpay selected
    </button>
    <button type="button" class="btn btn-sm btn-link text-muted" data-unpay-clear="{{ $tableId }}">Clear</button>
  </div>
@endif

<div class="table-responsive schedule-scroll">
  <table class="table table-sm mb-0 align-middle">
    <thead class="table-light">
    <tr>
      @if($checkCol)
        <th class="ps-3" style="width: 1%;">
          <input type="checkbox" class="form-check-input" data-select-all="{{ $tableId }}"
                 @disabled($payableRows === 0) aria-label="Select every paid charge">
        </th>
      @endif
      <th class="ps-3">#</th>
      <th class="ps-3">Charge</th>
      <th class="ps-3">Due Date</th>
      <th class="ps-3 text-end">Due</th>
      <th class="ps-3 text-end">Paid</th>
      <th class="ps-3 text-end">Balance</th>
      <th class="ps-3">Status</th>
      @if($actionCol)
        <th class="ps-3 text-end">Action</th>
      @endif
    </tr>
    </thead>
    <tbody>
    @forelse($ledgerRows as $seq => $row)
      <tr id="ledger-row-{{ $row->uuid }}" class="ledger-row">
        @if($checkCol)
          <td class="ps-3">
            {{-- Only a charge that has money against it can be un-paid. --}}
            <input type="checkbox" class="form-check-input"
                   data-charge-check="{{ $tableId }}"
                   data-uuid="{{ $row->uuid }}"
                   data-name="{{ $row->display_name }}"
                   data-amount="{{ number_format((float) $row->amount_paid, 2, '.', '') }}"
                   data-tax="{{ number_format((float) $row->activeAllocations->sum('tax_amount'), 2, '.', '') }}"
                   @disabled((float) $row->amount_paid <= 0)
                   aria-label="Select {{ $row->display_name }}">
          </td>
        @endif
        <td class="ps-3">{{ $seq + 1 }}</td>
        <td class="ps-3">
          <div class="fw-semibold small">{{ $row->display_name }}</div>
          <div class="text-muted text-tiny">
            {{ ucwords(str_replace('_', ' ', $row->charge_type)) }}{{ $row->balloon_no ? ' #'.$row->balloon_no : ($row->installment_no ? ' #'.$row->installment_no : '') }}
            @if($row->notes)
              <span class="text-muted">· {{ $row->notes }}</span>
            @endif
          </div>
        </td>
        <td class="ps-3">{{ $row->due_date?->format('d M Y') ?: '—' }}</td>
        <td class="ps-3 text-end">{{ number_format($row->amount_due, 2) }}</td>
        <td class="ps-3 text-end row-amount-paid">{{ number_format($row->amount_paid, 2) }}</td>
        <td class="ps-3 text-end fw-semibold row-balance">{{ number_format($row->remaining_balance, 2) }}</td>
        <td class="ps-3 row-status">{!! $statusBadge($row->payment_status) !!}</td>
        @if($actionCol)
          <td class="ps-3 text-end row-actions">
            <div class="d-inline-flex align-items-center gap-1">
              @if($row->remaining_balance > 0)
                <button type="button" class="btn btn-sm btn-icon btn-outline-success"
                        data-bs-toggle="modal" data-bs-target="#paymentModal"
                        title="Pay {{ number_format($row->remaining_balance, 2) }} {{ $currency }}"
                        onclick="prefillPaymentForm({{ $row->id }})">
                  <i class="ti tabler-cash"></i>
                </button>
              @else
                {{-- Reverses just this charge, so a bulk payment keeps the
                     charges it settled alongside this one. --}}
                <button type="button" class="btn btn-sm btn-icon btn-outline-warning"
                        title="Unpay {{ number_format($row->amount_paid, 2) }} {{ $currency }} against this charge"
                        onclick="openUnpayCharges('{{ $tableId }}', ['{{ $row->uuid }}'])">
                  <i class="ti tabler-rotate-clockwise-2"></i>
                </button>
              @endif
            </div>
          </td>
        @endif
      </tr>
    @empty
      <tr>
        <td colspan="{{ $colspan }}">
          <div class="text-center py-4 text-muted">No charges generated yet. Use <b>Initiate Payments</b> to generate
            them.
          </div>
        </td>
      </tr>
    @endforelse
    </tbody>
  </table>
</div>
