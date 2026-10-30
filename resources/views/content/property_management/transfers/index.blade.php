@extends('layouts/layoutMaster')
@section('title', 'Property Transfers')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'])
@endsection

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Property Management</p>
      <h4 class="mb-1">Property Transfers</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active">Property Transfers</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-exchange text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card">
    <div class="card-header pb-3">
      <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">

        {{-- Left: title + live count + short explanation --}}
        <div class="d-flex align-items-start gap-3">
          <span class="avatar flex-shrink-0 " style="width: 55px; height: 55px">
            <span class="avatar-initial rounded bg-label-primary">
              <i class="ti tabler-exchange icon-22px"></i>
            </span>
          </span>
          <div>
            <h5 class="card-title mb-1 d-flex align-items-center flex-wrap gap-2">
              Transfers
              @if ($transfers instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <span class="badge bg-label-primary rounded-pill  total_transfers">{{ $transfers->total() }}</span>
              @endif
            </h5>
            <div class="d-flex flex-column flex-md-row flex-wrap column-gap-4 row-gap-1 text-muted small">
              <span>
                <i class="ti tabler-circle-check text-success me-1"></i>
                <strong class="text-body">Approve</strong> moves ownership to the recipient and creates their transfer-fee plan.
              </span>
              <span>
                <i class="ti tabler-circle-x text-danger me-1"></i>
                <strong class="text-body">Reject</strong> leaves the ownership as it is.
              </span>
            </div>
          </div>
        </div>

        {{-- Right: search --}}
        <form method="GET" action="{{ route('property_transfers.index') }}" role="search"
              class="transfers-search w-100 flex-shrink-0">
          <label for="transferSearch" class="visually-hidden">Search transfers</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ti tabler-search"></i></span>
            <input type="search" id="transferSearch" name="q" value="{{ request('q') }}"
                   class="form-control" autocomplete="off"
                   placeholder="Search property, owner or recipient">
            @if (request()->filled('q'))
              <a href="{{ route('property_transfers.index') }}" class="btn btn-outline-secondary"
                 title="Clear search" aria-label="Clear search">
                <i class="ti tabler-x"></i>
              </a>
            @endif
            <button class="btn btn-primary" type="submit">Search</button>
          </div>
        </form>
      </div>

      {{-- Active filter hint --}}
      @if (request()->filled('q'))
        <div class="d-flex align-items-center flex-wrap gap-2 mt-3 pt-3 border-top">
          <span class="text-muted small">Showing results for</span>
          <span class="badge bg-label-primary">
            <i class="ti tabler-search me-1"></i>{{ \Illuminate\Support\Str::limit(request('q'), 40) }}
          </span>
          <a href="{{ route('property_transfers.index') }}" class="small text-decoration-none">
            <i class="ti tabler-x me-1"></i>Clear
          </a>
        </div>
      @endif
    </div>

    <div>
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">
          <div class="card-datatable">
            <table id="transfersTable" class="table table_to_reload datatables-users align-middle transfers-table">
              <thead class="bg-label-primary">
              <tr>
                <th class="col-ref">Reference</th>
                <th class="col-prop">Property / Unit</th>
                <th class="col-person">From (Outgoing Owner)</th>
                <th class="col-person">To (Recipient)</th>
                <th class="col-fee">Fee</th>
                <th class="col-date">Requested</th>
                <th class="col-status">Status</th>
                <th class="col-actions text-end">Actions</th>
              </tr>
              </thead>
              <tbody>
              @forelse ($transfers as $transfer)
                @php
                  $ownerable = $transfer->propertyOwnerable;
                  $label = $ownerable instanceof \App\Models\PropertyUnit
                      ? 'Unit '.($ownerable->unit_name ?: $ownerable->uuid)
                      : 'Property '.($ownerable->property_no ?: $ownerable->uuid);
                  $blockName = $ownerable?->floor?->property?->block?->name
                      ?? $ownerable?->block?->name;
                  $societyName = $ownerable?->floor?->property?->block?->society?->name
                      ?? $ownerable?->block?->society?->name;
                  $fromOwner = $transfer->fromOwnership?->owner;
                  $feeAmount = $transfer->feeAmount();
                  $feeText = $transfer->transfer_fee_in_per
                      ? rtrim(rtrim(number_format((float) $transfer->transfer_fee, 2, '.', ''), '0'), '.').'% of '
                        .number_format((float) $transfer->fee_base, 2).' = '
                        .number_format($feeAmount, 2).' '.$transfer->currency
                      : number_format($feeAmount, 2).' '.$transfer->currency;

                  // Once approved, the fee is billed to the incoming owner as a
                  // charge on their transfer-fee plan: show what is still owed.
                  $feePlan = $transfer->newPlan;
                  $feeOutstanding = 0.0;
                  if ($feePlan) {
                      $feeOutstanding = (float) app(\App\Support\PaymentLedgerService::class)->summary($feePlan)['remaining'];
                  }
                  $propertyUuid = $ownerable instanceof \App\Models\PropertyUnit
                      ? ($ownerable->floor?->property?->uuid)
                      : $ownerable?->uuid;

                  $fromName = trim(($fromOwner->first_name ?? '').' '.($fromOwner->last_name ?? '')) ?: '—';
                  $toName = trim(($transfer->recipient?->first_name ?? '').' '.($transfer->recipient?->last_name ?? '')) ?: '—';
                  $byName = trim(($transfer->requestedBy?->first_name ?? '').' '.($transfer->requestedBy?->last_name ?? '')) ?: '—';
                  $location = collect([$blockName, $societyName])->filter()->implode(' · ') ?: '—';
                @endphp
                <tr>
                  <td>
                    <span class="fw-semibold cell-clip"
                          title="{{ $transfer->reference_no }}">{{ $transfer->reference_no }}</span>
                    @if ($transfer->remarks)
                      <div class="text-muted text-tiny cell-clip" title="{{ $transfer->remarks }}">
                        <i class="ti tabler-message"></i>
                        {{ \Illuminate\Support\Str::limit($transfer->remarks, 60) }}
                      </div>
                    @endif
                  </td>
                  <td>
                    <span class="fw-semibold cell-clip" title="{{ $label }}">{{ $label }}</span>
                    <div class="text-muted text-tiny cell-clip" title="{{ $location }}">{{ $location }}</div>
                  </td>
                  <td>
                    <span class="cell-clip" title="{{ $fromName }}">{{ $fromName }}</span>
                    <div class="text-muted text-tiny cell-clip" title="{{ $fromOwner->email ?? '' }}">
                      {{ $fromOwner->email ?? '' }}
                    </div>
                  </td>
                  <td>
                    <span class="cell-clip" title="{{ $toName }}">{{ $toName }}</span>
                    <div class="text-muted text-tiny cell-clip" title="{{ $transfer->recipient?->email ?? '' }}">
                      {{ $transfer->recipient?->email ?? '' }}
                    </div>
                  </td>
                  <td>
                    <span class="cell-clip">{{ $feeText }}</span>
                    @if ($transfer->status === 'approved' && $feePlan)
                      @if ($feeOutstanding <= 0.004)
                        <div class="text-muted text-tiny">
                          <i class="ti tabler-circle-check me-1"></i>Fee paid
                        </div>
                      @else
                        <div class="text-muted text-tiny cell-clip">
                          <i class="ti tabler-clock me-1"></i>Outstanding
                          {{ number_format($feeOutstanding, 2) }} {{ $transfer->currency }}
                        </div>
                      @endif
                    @endif
                  </td>
                  <td>
                    {{ $transfer->created_at->format('d M Y') }}
                    <div class="text-muted text-tiny cell-clip" title="by {{ $byName }}">by {{ $byName }}</div>
                  </td>
                  <td>
                    @if ($transfer->status === 'pending')
                      <span class="badge bg-label-warning">
                  <i class="ti tabler-hourglass-high me-1"></i>Pending
                </span>
                    @elseif ($transfer->status === 'approved')
                      <span class="badge bg-label-success">
                  <i class="ti tabler-check me-1"></i>Approved
                </span>
                      <div class="text-muted text-tiny cell-clip">
                        {{ $transfer->acted_at?->format('d M Y') }}
                        @if ($transfer->actedBy)
                          by {{ $transfer->actedBy->first_name }}
                        @endif
                      </div>
                    @else
                      <span class="badge bg-label-danger">
                  <i class="ti tabler-x me-1"></i>Rejected
                </span>
                      <div class="text-muted text-tiny cell-clip">
                        {{ $transfer->acted_at?->format('d M Y') }}
                        @if ($transfer->actedBy)
                          by {{ $transfer->actedBy->first_name }}
                        @endif
                      </div>
                    @endif
                  </td>
                  <td class="text-end">
                    <div class="d-flex flex-wrap justify-content-end gap-1">
                      @can('property_transfer_print')
                        <a href="{{ route('property_transfers.print', ['transfer' => $transfer, 'print' => 1]) }}"
                           onclick="event.preventDefault(); printDocument(this.href)"
                           class="btn btn-sm btn-outline-secondary" title="Print this transfer">
                          <i class="ti tabler-printer"></i>
                        </a>
                      @endcan

                      @can('payment_collect')
                        @if ($transfer->status === 'approved' && $feePlan && $feeOutstanding > 0.004 && $propertyUuid)
                          <a href="{{ route('property.details', $propertyUuid) }}?tab=payments"
                             class="btn btn-sm btn-outline-primary" title="Collect the transfer fee">
                            <i class="ti tabler-cash me-1"></i>Collect Fee
                          </a>
                        @endif
                      @endcan

                      @can('property_transfer_approve')
                        @if ($transfer->status === 'pending')
                          <form action="{{ route('property_transfers.approve', $transfer) }}" method="POST"
                                class="d-inline js-approve-transfer"
                                data-label="{{ $transfer->propertyOwnerable?->property_no }}"
                                data-recipient="{{ trim(($transfer->recipient?->first_name ?? '').' '.($transfer->recipient?->last_name ?? '')) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success" title="Approve transfer">
                              <i class="ti tabler-check"></i>
                            </button>
                          </form>

                          <button type="button" class="btn btn-sm btn-outline-danger"
                                  data-bs-toggle="modal" data-bs-target="#rejectTransferModal"
                                  data-url="{{ route('property_transfers.reject', $transfer) }}"
                                  title="Reject transfer">
                            <i class="ti tabler-x"></i>
                          </button>
                        @endif
                      @endcan
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="text-center py-4 text-muted">
                    <i class="ti tabler-inbox fs-1 opacity-50 mb-2 d-block"></i>
                    No transfers for your societies yet.
                  </td>
                </tr>
              @endforelse
              </tbody>
            </table>
          </div>

          {{-- Bottom row: same position as the DataTable info + pagination (Laravel pagination) --}}
          <div class="row mt-3 mb-2 align-items-center">
            <div class="col-md-6">
              @if ($transfers instanceof \Illuminate\Pagination\LengthAwarePaginator && $transfers->total())
                <div class="dt-info">
                  Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }}
                  entries
                </div>
              @endif
            </div>
            <div class="col-md-6 d-flex justify-content-end">
              {{ $transfers->links() }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Reject modal --}}
  <div class="modal fade" id="rejectTransferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <form action="" method="POST" id="rejectTransferForm">
          @csrf
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Reject Transfer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted mb-3">
              The transfer will be closed and the requester notified. Ownership is not affected.
            </p>
            <label class="form-label fw-semibold" for="rejectRemarks">Remarks (optional)</label>
            <textarea name="remarks" id="rejectRemarks" class="form-control" rows="3"
                      placeholder="Reason for rejection"></textarea>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">
              <i class="ti tabler-x me-1"></i> Confirm Rejection
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('styles')
  <style>

    .dt-search {
      display: none !important;
    }

    .dt-length label {
      display: none !important;
    }

    table.dataTable thead th {
      border-bottom: 2px solid #cdcdcf !important;
    }

    .dt-scroll-wrapper {
      position: relative;
    }

    .card-header {
      overflow: visible !important;
    }

    .dataTables_wrapper .row.align-items-center {
      flex-wrap: wrap;
    }

    /* Table fits the card width (no sideways scroll); only the values wrap */
    .transfers-table {
      width: 100% !important;
      table-layout: fixed;
    }

    /* Column widths (sum = 100%) */
    .transfers-table .col-ref {
      width: 12%;
    }

    .transfers-table .col-prop {
      width: 12%;
    }

    .transfers-table .col-person {
      width: 15%;
    }

    .transfers-table .col-fee {
      width: 15%;
    }

    .transfers-table .col-date {
      width: 10%;
    }

    .transfers-table .col-status {
      width: 10%;
    }

    .transfers-table .col-actions {
      width: 11%;
    }

    /* Long text breaks onto the next line (full text also shows on hover via title) */
    .transfers-table .cell-clip {
      display: block;
      white-space: normal;
      word-break: break-word;
      overflow-wrap: anywhere;
    }

    .transfers-search {
      max-width: 100%;
    }

    @media (min-width: 992px) {
      .transfers-search {
        max-width: 440px;
      }
    }

    .total_transfers {
      font-size: 0.8rem;
    }
  </style>
@endpush

@push('scripts')
  <script>
    (function() {
      var modal = document.getElementById('rejectTransferModal');
      if (!modal) return;

      modal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('rejectTransferForm').action = btn.getAttribute('data-url');
        document.getElementById('rejectRemarks').value = '';
      });
    })();

    // Approving moves the ownership, so ask through SweetAlert rather than a
    // native browser confirm.
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.js-approve-transfer').forEach(function(form) {
        form.addEventListener('submit', function(event) {
          event.preventDefault();

          var recipient = form.getAttribute('data-recipient') || 'the recipient';
          var label = form.getAttribute('data-label');
          var property = label ? 'Property ' + label : 'This property';

          var confirmApprove = function() {
            form.submit();
          };

          if (typeof Swal === 'undefined') {
            if (window.confirm('Approve this transfer? Ownership will move to ' + recipient + '.')) {
              confirmApprove();
            }
            return;
          }

          Swal.fire({
            title: 'Approve this transfer?',
            text: property + ' will move to ' + recipient + '.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, approve',
            cancelButtonText: 'Cancel',
            customClass: {
              confirmButton: 'btn btn-success me-2',
              cancelButton: 'btn btn-outline-secondary'
            },
            buttonsStyling: false
          }).then(function(result) {
            if (result.isConfirmed) confirmApprove();
          });
        });
      });
    });

    // Same DataTable setup as the Transfer Fee page (rows come from Blade; paging stays with Laravel's links)
    document.addEventListener('DOMContentLoaded', function() {
      var el = document.getElementById('transfersTable');
      if (!el || !window.jQuery || !jQuery.fn.DataTable || el.querySelector('tbody td[colspan]')) return;

      jQuery(el).DataTable({
        paging: false,
        info: false,
        autoWidth: false,
        order: [],
        columnDefs: [{ orderable: false, targets: 7 }],
        dom: '<\'row align-items-center\'' +
          '<\'col-sm-6 col-12 d-flex align-items-center gap-2 mb-2 mb-sm-0\'l f>' +
          '<\'col-sm-6 col-12 d-flex justify-content-sm-end justify-content-start\'<\'dt-actions\'>>' +
          '>' +
          '<\'row\'<\'col-12\'tr>>'
      });
    });
  </script>
@endpush
