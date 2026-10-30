@extends('layouts.layoutMaster')

@section('title', 'Payments Report')

@section('page-style')
  @vite(['resources/assets/css/print-plan.css'])
@endsection

@section('content')

  @php
$rangeCharges = (float) $transactions->sum('charge_subtotal');
$rangeTax = (float) $transactions->sum('tax_amount');
$rangeLateFee = (float) $transactions->sum('late_fee_amount');
$rangeTotal = (float) $transactions->sum('amount_paid');
  @endphp

  <div class="print-toolbar d-flex justify-content-end gap-2 mb-3">
    @if($backUrl)
      <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary">
        <i class="tabler-arrow-left me-1"></i>Back
      </a>
    @endif
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
      <i class="tabler-printer me-1"></i>Print
    </button>
  </div>

  <div class="invoice-print">
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
          <h5 class="mb-2">PAYMENTS REPORT</h5>
          <p class="mb-0 text-heading"><span>Period:</span>
            <span class="fw-medium">{{ $from->format('M d, Y') }} — {{ $to->format('M d, Y') }}</span>
          </p>
          <p class="mb-0 text-heading"><span>Generated:</span>
            <span class="fw-medium">{{ now()->format('M d, Y') }}</span>
          </p>
        </div>
      </div>

      {{-- PROPERTY / BUYER DETAILS --}}
      <div class="mb-3">
        <h6 class="mb-1 fw-bold">Property Details:</h6>
        @if(! empty($planData['property_name']))
          <p class="mb-0 fw-semibold text-heading">{{ ucwords($planData['property_name']) }}</p>
          <p class="mb-0">Property No: #{{ ucfirst($planData['property_no'] ?? '—') }}</p>
          <p class="mb-0">{{ ucwords($planData['property_address'] ?? '—') }}</p>
        @else
          <p class="mb-0 text-muted">Property details unavailable.</p>
        @endif

        <p class="mb-0 text-heading"><span class="text-heading">Buyer:</span>
          <span class="fw-medium">{{ $owner?->full_name ?? '—' }}</span>
        </p>
        <p class="mb-0 text-heading"><span class="text-heading">Plan Total:</span>
          <span class="fw-medium">{{ number_format((float) ($summary['total_due'] ?? 0), 2) }} {{ $currency }}</span>
        </p>
      </div>

      {{-- TRANSACTIONS IN RANGE --}}
      <h6 class="mb-1 fw-bold">Transactions in this period</h6>
      <table class="table table-sm table-bordered mb-2">
        <thead>
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Charges Settled</th>
          <th>Method</th>
          <th>Reference</th>
          <th class="text-end">Amount</th>
          <th class="text-end">Tax</th>
          <th class="text-end">Late Fee</th>
          <th class="text-end">Total</th>
        </tr>
        </thead>
        <tbody>
        @forelse($transactions as $transaction)
          <tr>
            <td>{{ str_pad((string) $transaction->id, 5, '0', STR_PAD_LEFT) }}</td>
            <td>{{ $transaction->paid_at?->format('M d, Y') }}</td>
            <td>
              @foreach($transaction->allocations as $allocation)
                <span class="d-block">{{ $allocation->ledgerRow?->display_name ?? 'Charge' }}</span>
              @endforeach
            </td>
            <td class="text-uppercase">{{ str_replace('_', ' ', $transaction->payment_method ?? '—') }}</td>
            <td>{{ $transaction->reference_no ?? '—' }}</td>
            <td class="text-end">{{ number_format((float) $transaction->charge_subtotal, 2) }}</td>
            <td class="text-end">{{ number_format((float) $transaction->tax_amount, 2) }}</td>
            <td class="text-end">{{ number_format((float) $transaction->late_fee_amount, 2) }}</td>
            <td class="text-end fw-medium">{{ number_format((float) $transaction->amount_paid, 2) }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="text-center text-muted">No payments were received in this period.</td>
          </tr>
        @endforelse
        </tbody>
        @if($transactions->isNotEmpty())
          <tfoot>
          <tr class="border-top fw-bold">
            <td colspan="5" class="text-end">Period Total</td>
            <td class="text-end">{{ number_format($rangeCharges, 2) }}</td>
            <td class="text-end">{{ number_format($rangeTax, 2) }}</td>
            <td class="text-end">{{ number_format($rangeLateFee, 2) }}</td>
            <td class="text-end">{{ number_format($rangeTotal, 2) }}</td>
          </tr>
          </tfoot>
        @endif
      </table>

      {{-- CHARGE STATUS AS AT PERIOD END --}}
      <h6 class="mb-1 fw-bold">Charge status as at {{ $to->format('M d, Y') }}</h6>
      <table class="table table-sm table-bordered mb-2">
        <thead>
        <tr>
          <th>#</th>
          <th>Charge</th>
          <th>Due Date</th>
          <th class="text-end">Amount Due</th>
          <th class="text-end">Paid to Date</th>
          <th class="text-end">Remaining</th>
          <th class="text-center">Status</th>
        </tr>
        </thead>
        <tbody>
        @forelse($charges as $index => $charge)
          <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $charge['name'] }}</td>
            <td>{{ $charge['due_date'] ? \Illuminate\Support\Carbon::parse($charge['due_date'])->format('M d, Y') : '—' }}</td>
            <td class="text-end">{{ number_format($charge['amount'], 2) }}</td>
            <td class="text-end">{{ number_format($charge['paid'], 2) }}</td>
            <td class="text-end">{{ number_format($charge['remaining'], 2) }}</td>
            <td class="text-center text-uppercase">{{ $charge['status'] }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center text-muted">This plan has no charges.</td>
          </tr>
        @endforelse
        </tbody>
      </table>

      {{-- PLAN TOTALS --}}
      <div class="d-flex justify-content-end">
        <table class="table table-sm w-auto mb-0" style="min-width: 260px;">
          <tbody>
          <tr>
            <td class="text-muted">Final Charges (As Per Plan)</td>
            <td class="text-end fw-medium">{{ number_format((float) ($summary['total_due'] ?? 0), 2) }}</td>
          </tr>
          <tr>
            <td class="text-muted">
              FBR Tax
              @if(($summary['tax_rate_percent'] ?? 0) > 0)
                <span class="text-muted">({{ rtrim(rtrim(number_format((float) $summary['tax_rate_percent'], 2, '.', ''), '0'), '.') }}%)</span>
              @endif
            </td>
            <td class="text-end fw-medium">{{ number_format((float) ($summary['tax_total_due'] ?? 0), 2) }}</td>
          </tr>
          <tr>
            <td class="text-muted">Received Charges</td>
            <td class="text-end fw-medium">{{ number_format((float) ($summary['total_paid'] ?? 0), 2) }}</td>
          </tr>
          <tr>
            <td class="text-muted">Tax Collected</td>
            <td class="text-end fw-medium">{{ number_format((float) ($summary['tax_collected'] ?? 0), 2) }}</td>
          </tr>
          <tr>
            <td class="text-muted">Late Fee Collected</td>
            <td class="text-end fw-medium">{{ number_format((float) ($summary['late_fee_collected'] ?? 0), 2) }}</td>
          </tr>
          <tr>
            <td class="text-muted">Total Received</td>
            <td class="text-end fw-medium">{{ number_format((float) ($summary['total_received'] ?? 0), 2) }}</td>
          </tr>
          <tr class="border-top">
            <td class="fw-bold">Remaining Amount</td>
            <td
              class="text-end fw-bold">{{ number_format((float) ($summary['remaining'] ?? 0), 2) }} {{ $currency }}</td>
          </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 pt-2 border-top">
        <p class="mb-0 text-muted small fs-6">
          This report is computer generated. Charge status reflects only payments dated on or before
          {{ $to->format('M d, Y') }}; later payments are reflected in the to-date totals above.
        </p>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  @if($autoPrint)
    <script data-report-auto-print>
      window.addEventListener('load', () => {
        // Inside the app's hidden print frame the parent triggers printing.
        if (window.self === window.top) window.print();
      });
    </script>
  @endif
@endpush
