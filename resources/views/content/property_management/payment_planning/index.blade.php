@extends('layouts/layoutMaster')
@section('title', 'Payment-Templates')
@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Property Management</p>
      <h4 class="mb-1">Payment Templates</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active">Templates</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-template text-dark opacity-25 position-absolute end-0 me-4"></i>
  </div>

  <div class="card">
    <div class="card-header pb-1">
      <div class="dt-scroll-wrapper">
        <div class="dt-actions-bar">
          @can('payment_template_create')
            <div id="dt-right-actions" class="d-none">
              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal">
                Add Template
              </button>
            </div>
          @endcan
        </div>

        @if (session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
          <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
          <div class="alert alert-danger">
            <ul>
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif
        <div class="card-datatable">
          <table id="payment_templates_table" class="table datatables-users">
            <thead class="bg-label-primary">
            <tr>
              <th><input type="checkbox" class="form-check-input" id="select_all"></th>
              <th>Name</th>
              <th>Booking Fee</th>
              <th>Development Fee</th>
              <th>Down Payment</th>
              <th>Confirmation Fee</th>
              <th>Possession Fee</th>
              <th>Corner Premium</th>
              <th>Park Facing</th>
              <th>Boulevard Premium</th>
              @if ($show_actions)
                <th>Actions</th>
              @endif
            </tr>
            </thead>
          </table>
        </div>
      </div>
    </div>
  </div>

  @include('_partials._modals.payment_template')
@endsection

@push('scripts')
  <script>
    $(function() {
      let table = $('#payment_templates_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: `{{ route('payment-templates.index') }}`,
        dom: '<\'row align-items-center\'' +
          '<\'col-sm-6 col-12 d-flex align-items-center gap-2 mb-2 mb-sm-0\'l f <\'#bulk-delete-wrap\'>>' +
          '<\'col-sm-6 col-12 d-flex justify-content-sm-end justify-content-start\'<\'dt-actions\'>>' +
          '>' +
          '<\'row\'<\'col-12\'tr>>' +
          '<\'row mt-3 align-items-center\'' +
          '<\'col-md-6\'i>' +
          '<\'col-md-6 d-flex justify-content-end\'p>' +
          '>',
        columns: [
          { data: 'checkbox', orderable: false, searchable: false },
          { data: 'name' },
          { data: 'booking_fee', orderable: false },
          { data: 'development_fee', orderable: false },
          { data: 'down_payment', orderable: false },
          { data: 'confirmation_fee', orderable: false },
          { data: 'possession_fee', orderable: false },
          { data: 'corner_premium', orderable: false },
          { data: 'park_facing_premium', orderable: false },
          { data: 'main_boulevard_premium', orderable: false },
            @if ($show_actions)
          {
            data: 'actions', orderable: false, searchable: false
          },
          @endif
        ],
        initComplete: function() {
          $('.dt-actions').html($('#dt-right-actions').removeClass('d-none'));
          $('#bulk-delete-wrap').html(`
            <button class="btn btn-danger btn-sm d-none bulk_btn" data-url="{{ route('payment-templates.bulk_delete') }}">
              Bulk Delete
            </button>
          `);
        }
      });
    });
  </script>
@endpush

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

    .dataTables_wrapper .col-sm-6 {
      flex-basis: 50%;
      min-width: 0;
    }

    /* Tablet and smaller screens */
    @media (max-width: 768px) {
      .dataTables_wrapper .row.align-items-center {
        flex-direction: column !important;
        align-items: stretch !important;
      }

      .dataTables_wrapper .col-sm-6 {
        flex-basis: 100% !important;
        min-width: auto !important;
      }

      .dataTables_wrapper .col-sm-6.d-flex {
        margin-bottom: 10px !important;
      }

      .dataTables_wrapper .dt-actions {
        width: 100% !important;
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
        margin-top: 0 !important;
        justify-content: flex-start !important;
      }

      .dataTables_wrapper .dt-actions .btn {
        flex: 1 1 auto;
        min-width: 120px;
      }

      #bulk-delete-wrap {
        width: 100%;
      }

      #bulk-delete-wrap .btn {
        width: 100%;
      }
    }

    /* Mobile screens */
    @media (max-width: 575px) {
      .dataTables_wrapper .row.align-items-center {
        flex-direction: column !important;
        align-items: flex-start !important;
      }

      .dataTables_wrapper .col-sm-6 {
        width: 100% !important;
      }

      .dataTables_wrapper .dt-actions {
        width: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
        margin-top: 8px !important;
      }

      .dataTables_wrapper .dt-actions .btn {
        width: 100%;
      }

      #bulk-delete-wrap {
        width: 100%;
        margin-bottom: 8px;
      }

      #bulk-delete-wrap .btn {
        width: 100%;
      }
    }

    /* Large screens with horizontal scroll for table */
    @media (max-width: 1250px) {
      .dt-scroll-wrapper {
        overflow-x: auto;
        width: 100%;
        -webkit-overflow-scrolling: touch;
      }

      .dt-scroll-wrapper > * {
        min-width: 1000px;
      }

      table.dataTable {
        white-space: nowrap;
      }
    }
  </style>
@endpush
