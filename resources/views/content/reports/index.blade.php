@extends('layouts/layoutMaster')

@section('title', 'Reports')
@section('content')
  <h4 class="mb-1">Reports</h4>
  <nav aria-label="breadcrumb" class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Reports</li>
    </ol>
  </nav>
  <div class="card">
    <div>
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">
          <div class="dt-actions-bar">
            <div id="dt-right-actions" class="d-none">
              <div class="d-flex gap-2">
              </div>
            </div>
          </div>

          @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if (session('error'))
            <div class="alert alert-danger">
              {{ session('error') }}
            </div>
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

          <!-- TABLE -->
          <div class="card-datatable">
            <table id="reports_table" class="table table_to_reload datatables-users">
              <thead class="bg-label-primary">
                <tr>
                  <th><input type="checkbox" class="form-check-input" id="select_all"></th>
                  <th>Post</th>
                  <th>No or reports</th>
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
@endsection

@push('scripts')
  <script>
    $(function() {
      // DataTable initialization
      let table = $('#reports_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: `/reports/index`,
        dom: "<'row align-items-center'" +
          "<'col-sm-6 col-12 d-flex align-items-center gap-2 mb-2 mb-sm-0'l f <'#bulk-delete-wrap'>>" +
          "<'col-sm-6 col-12 d-flex justify-content-sm-end justify-content-start'<'dt-actions'>>" +
          ">" +
          "<'row'<'col-12'tr>>" +
          "<'row mt-3 align-items-center'" +
          "<'col-md-6'i>" +
          "<'col-md-6 d-flex justify-content-end'p>" +
          ">",
        columns: [{
            data: 'checkbox',
            orderable: true,
            searchable: true
          },
          {
            data: 'post'
          },
          {
            data: 'no_of_reports'
          },
          @if ($show_actions)
            {
              data: 'actions',
              orderable: false,
              searchable: false
            },
          @endif
        ],
        initComplete: function() {
          $('.dt-actions').html($('#dt-right-actions').removeClass('d-none'));
          $('#bulk-delete-wrap').html(`
            <button class="btn btn-danger d-none bulk_delete_btn" data-url="{{ route('reports.bulk_delete') }}">
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
    #color-picker-wrapper .pickr,
    #color-picker-wrapper .pickr .pcr-button {
      width: 100% !important;
    }

    .dt-search  {
      display: none !important;
    }

    .dt-length label {
      display: none !important;
    }

    table.dataTable thead th {
      border-bottom: 2px solid #cdcdcf !important;
    }

    @media (max-width: 575px) {
      .dataTables_wrapper .row.align-items-center {
        flex-direction: column !important;
        align-items: flex-start !important;
      }

      .dataTables_wrapper .dt-actions {
        width: 100% !important;
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 10px !important;
        margin-top: 10px !important;
      }
    }

    @media (max-width: 991px) {
      .dt-scroll-wrapper {
        overflow-x: auto;
        width: 100%;
        -webkit-overflow-scrolling: touch;
      }

      .dt-scroll-wrapper>* {
        min-width: 1100px;
      }

      table.dataTable {
        white-space: nowrap;
      }
    }
  </style>
@endpush
