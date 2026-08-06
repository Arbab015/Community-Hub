@extends('layouts/layoutMaster')

@section('title', 'Payment Plan (Print version)')

@section('page-style')
  <style>
    @media print {
      @page {
        size: A4 portrait;
        margin: 0;
      }

      /* Hide the app chrome so only the plan document prints */
      .layout-navbar,
      .layout-menu,
      .content-footer,
      .print-toolbar,
      .layout-overlay,
      .layout-menu-toggle {
        display: none !important;
      }

      /* Flat A4 document look — no card shadow / rounding */
      .invoice-print .card,
      .invoice-print .invoice-preview-card {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
      }

      .invoice-print .rounded,
      .invoice-print .invoice-preview-header {
        border-radius: 0 !important;
      }

      /* Don't clip tables into a scrollable area when printing */
      .invoice-print .table-responsive {
        overflow: visible !important;
      }

      /* Let long cells wrap instead of stretching the table past the page */
      .invoice-print .text-nowrap {
        white-space: normal !important;
      }

      .invoice-print .table {
        width: 100% !important;
      }

      /* Compact the card so the whole plan fits one A4 page */
      .invoice-print .invoice-preview-card {
        padding: 0.75rem !important;
      }

      .invoice-print .invoice-preview-header {
        padding: 0.2rem 0 0.4rem !important;
      }

      /* Scale headings down to invoice size */
      .invoice-print h5 {
        font-size: 1.05rem !important;
        margin-bottom: 0.2rem !important;
      }

      .invoice-print h6 {
        font-size: 0.85rem !important;
        margin-bottom: 0.25rem !important;
      }

      .invoice-print .app-brand-text {
        font-size: 1.05rem !important;
      }

      .invoice-print .invoice-preview-header .mb-6 {
        margin-bottom: 0.25rem !important;
      }

      .invoice-print .mb-6,
      .invoice-print .mb-4,
      .invoice-print .mb-2,
      .invoice-print .mb-1 {
        margin-bottom: 0.25rem !important;
      }

      .invoice-print .mt-4 {
        margin-top: 0.9rem !important;
      }

      .invoice-print .table {
        font-size: 0.78rem;
      }

      .invoice-print .table th,
      .invoice-print .table td {
        padding: 0.32rem 0.5rem;
      }

      .invoice-print .table-sm th,
      .invoice-print .table-sm td {
        padding: 0.25rem 0.5rem;
      }

      .invoice-print .py-6 {
        padding-top: 0.3rem !important;
        padding-bottom: 0.3rem !important;
      }

      .invoice-print .card-body.px-0 {
        padding-top: 0.4rem;
        padding-bottom: 0.4rem;
      }
    }

    .invoice-print {
      padding: 12mm;
    }
  </style>
@endsection

@section('content')
  {{-- Toolbar (not printed) --}}
  <div class="print-toolbar d-flex align-items-center justify-content-between flex-wrap gap-3 p-4">
    <div>
      <h4 class="fw-bold mb-1">Payment Plan Print</h4>
      <small class="text-muted">Pick an applicant to personalise the plan, or print a simple copy.</small>
    </div>
    <div class="d-flex align-items-center gap-2">
      <select id="print_applicant_select" class="form-select" style="min-width:280px;">
        <option value="">Simple print (no applicant)</option>
        @forelse($applicants as $inquiry)
          <option value="{{ $inquiry->uuid }}" @selected($selectedApplicant?->uuid === $inquiry->uuid)>
            {{ $inquiry->name }}
          </option>
        @empty
        @endforelse
      </select>
      <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="ti tabler-printer me-1"></i> Print
      </button>
    </div>
  </div>

  <div class="invoice-print">
    <x-property.payment-plans-display
      :property="$property"
      :shadow="false"
      :selectedApplicant="$selectedApplicant"
      :printedByName="$printedByName"
      :printedByRole="$printedByRole"
    />
  </div>
@endsection

@push('scripts')
  <script>
    document.getElementById('print_applicant_select')?.addEventListener('change', function() {
      const url = new URL(window.location.href);
      if (this.value) url.searchParams.set('applicant', this.value);
      else url.searchParams.delete('applicant');
      window.location.href = url.toString();
    });
  </script>
@endpush
