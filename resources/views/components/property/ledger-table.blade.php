@php
  $showActions = $showActions ?? true;
  $canCollect = \Illuminate\Support\Facades\Gate::allows('payment_collect');
  $actionCol = ($canCollect && $showActions) ? 1 : 0;
  $colspan = 7 + $actionCol;
@endphp

<div class="table-responsive schedule-scroll">
  <table class="table table-sm mb-0 align-middle">
    <thead class="table-light">
    <tr>
      <th class="ps-3">#</th>
      <th class="ps-3">Charge</th>
      <th class="ps-3">Due Date</th>
      <th class="ps-3 text-end">Due</th>
      <th class="ps-3 text-end">Paid</th>
      <th class="ps-3 text-end">Balance</th>
      <th class="ps-3">Status</th>
      @if($actionCol)<th class="ps-3 text-end">Action</th>@endif
    </tr>
    </thead>
    <tbody>
    @forelse($ledgerRows as $seq => $row)
      <tr id="ledger-row-{{ $row->uuid }}" class="ledger-row">
        <td class="ps-3">{{ $seq + 1 }}</td>
        <td class="ps-3">
          <div class="fw-semibold small">{{ $row->display_name }}</div>
          <div class="text-muted text-tiny">
            {{ ucwords(str_replace('_', ' ', $row->charge_type)) }}{{ $row->balloon_no ? ' #'.$row->balloon_no : ($row->installment_no ? ' #'.$row->installment_no : '') }}
            @if($row->notes)<span class="text-muted">· {{ $row->notes }}</span>@endif
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
                        onclick="prefillPaymentForm({{ $row->id }}, {{ $row->remaining_balance }})">
                  <i class="ti tabler-cash"></i>
                </button>
              @else
                <button type="button" class="btn btn-sm btn-icon btn-outline-warning"
                        title="Refund / undo this payment"
                        onclick="openUnpayModal('{{ $row->uuid }}', '{{ addslashes($row->display_name) }}')">
                  <i class="ti tabler-rotate-clockwise-2"></i>
                </button>
              @endif
            </div>
          </td>
        @endif
      </tr>
    @empty
      <tr>
        <td colspan="{{ $colspan }}"><div class="text-center py-4 text-muted">No charges generated yet. Use <b>Initiate Payments</b> to generate them.</div></td>
      </tr>
    @endforelse
    </tbody>
  </table>
</div>