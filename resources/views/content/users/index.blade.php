@extends('layouts/layoutMaster')

@section('title', $slug)

@section('content')

  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Users Management</p>
      <h4 class="mb-1">
        {{ $slug == 'society_owners' ? 'Society Owners' : 'System Users' }}
      </h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active">Users</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-users text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  <div class="card">
    <div class="">
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">
          <div class="dt-actions-bar">
            <div id="dt-right-actions" class="d-none">
              <div class="d-flex gap-2">
                @can('export_user')
                  <div class="btn-group">
                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                      <i class="fa-solid fa-upload me-1 text-primary"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item export-excel" href="#">Excel</a></li>
                      <li><a class="dropdown-item export-csv" href="#">CSV</a></li>
                      <li><a class="dropdown-item export-pdf" href="#">PDF</a></li>
                      <li><a class="dropdown-item export-print" href="#">Print</a></li>
                    </ul>
                  </div>
                @endcan
                @can('import_user')
                  <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#userImportModal">
                    <i class="fa-solid fa-download me-1 text-primary"></i> Import
                  </button>
                @endcan
                @can('add_user')
                  <a href="{{ route('user.create', $slug) }}" class="btn btn-primary">
                    Add {{ $slug == 'society_owners' ? 'Owner' : 'User' }}
                  </a>
                @endcan

              </div>
            </div>
          </div>

          @if (session('success'))
            <div class="alert alert-success">
              {{ session('success') }}
            </div>
          @endif


          <!-- TABLE -->
          <div class="card-datatable">
            <table id="users_table" class="table table_to_reload datatables-users">
              <thead class="bg-label-primary">
              <tr>
                <th><input class="form-check-input" type="checkbox" id="select_all"></th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                @if($slug === "society_members")
                  <th>Society Name</th>
                @endif
                <th>Contact</th>
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
  @include('_partials._modals.import_users_model')
@endsection

@push('scripts')
  <script>
    let slug = "{{ $slug }}";
    $(function() {
      let table = $('#users_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: `/users/index/${slug}`,
        dom: '<\'row align-items-center\'' +
          '<\'col-sm-6 col-12 d-flex align-items-center gap-2 mb-2 mb-sm-0\'l f <\'#bulk-delete-wrap\'>>' +
          '<\'col-sm-6 col-12 d-flex justify-content-sm-end justify-content-start\'<\'dt-actions\'>>' +
          '>' +
          '<\'row\'<\'col-12\'tr>>' +
          '<\'row mt-3 align-items-center\'' +
          '<\'col-md-6\'i>' +
          '<\'col-md-6 d-flex justify-content-end\'p>' +
          '>',
        buttons: [{
          extend: 'excel',
          className: 'buttons-excel d-none',
          exportOptions: {
            columns: [1, 2, 3, 4]
          }
        },
          {
            extend: 'csv',
            className: 'buttons-csv d-none',
            exportOptions: {
              columns: [1, 2, 3, 4]
            }
          },
          {
            extend: 'pdf',
            className: 'buttons-pdf d-none',
            exportOptions: {
              columns: [1, 2, 3, 4]
            }
          },
          {
            extend: 'print',
            className: 'buttons-print d-none',
            exportOptions: {
              columns: [1, 2, 3, 4]
            }
          }
        ],

        columns: [{
          data: 'checkbox',
          orderable: false,
          searchable: false
        },
          {
            data: 'name'
          },
          {
            data: 'email'
          },
          {
            data: 'role'
          },
            @if($slug === "society_members")
          {
            data: 'society'
          },
            @endif
          {
            data: 'contact'
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
        <button class="btn btn-danger d-none bulk_delete_btn" data-url="{{ route('users.bulk_delete') }}" >
          Bulk Delete
        </button>
      `);
        }
      });

      $('.export-excel').on('click', e => {
        e.preventDefault();
        table.button('.buttons-excel').trigger();
      });

      $('.export-csv').on('click', e => {
        e.preventDefault();
        table.button('.buttons-csv').trigger();
      });

      $('.export-pdf').on('click', e => {
        e.preventDefault();
        table.button('.buttons-pdf').trigger();
      });

      $('.export-print').on('click', e => {
        e.preventDefault();
        table.button('.buttons-print').trigger();
      });
    });
  </script>
@endpush


@push('styles')
  <style>
    .dt-search label {
      display: none !important;
    }

    .dt-length label {
      display: none !important;
    }

    table.dataTable thead th {
      border-bottom: 2px solid #cdcdcf !important;
    }

    @media (max-width: 575px) {
      .dataTables_wrapper > .row:first-child {
        flex-wrap: nowrap !important;
      }

      .dataTables_wrapper > .row:first-child > div {
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: 100% !important;
      }
    }

    @media (max-width: 991px) {
      .dt-scroll-wrapper {
        overflow-x: auto;
        width: 100%;
        -webkit-overflow-scrolling: touch;
      }

      .dt-scroll-wrapper > * {
        min-width: 1100px;
      }

      table.dataTable {
        white-space: nowrap;
      }
    }

  </style>
@endpush
