@extends('layouts/layoutMaster')
@section('title', 'Transfer Fee')

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Property Management</p>
      <h4 class="mb-1">Transfer Fee</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active">Transfer Fee</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-receipt text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  <div class="card">
    <div>
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">
          <div class="dt-actions-bar">
            @can('transfer_fee_create')
              <div id="dt-right-actions" class="d-none">
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                          data-bs-target="#transferFeeModal">
                    Add Transfer Fee
                  </button>
                </div>
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
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="card-datatable">
            <table id="transfer_fees_table" class="table table_to_reload datatables-users">
              <thead class="bg-label-primary">
              <tr>
                <th><input type="checkbox" class="form-check-input" id="select_all"></th>
                <th>Society</th>
                <th>Min Size</th>
                <th>Max Size</th>
                <th>Unit</th>
                <th>Transfer Fee</th>
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
  </div>

  @include('_partials._modals.transfer_fee')
@endsection

@push('scripts')
  <script>
    $(function() {
      $('#transfer_fees_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: `{{ route('transfer_fee.index') }}`,
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
          { data: 'society' },
          { data: 'min_size' },
          { data: 'max_size' },
          { data: 'size_unit', orderable: false },
          { data: 'fee', orderable: false },
            @if ($show_actions)
          {
            data: 'actions', orderable: false, searchable: false
          }
          @endif
        ],
        initComplete: function() {
          $('.dt-actions').html($('#dt-right-actions').removeClass('d-none'));
          $('#bulk-delete-wrap').html(`
        <button class="btn btn-sm btn-danger d-none bulk_btn" data-url="{{ route('transfer_fee.bulk_delete') }}" >
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
