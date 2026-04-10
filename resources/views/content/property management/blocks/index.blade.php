@extends('layouts/layoutMaster')

@section('title', 'Blocks/Sectors')
@section('content')
  <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Property Management</p>
  <h4 class="mb-1">Property Blocks/Sectors</h4>
  <nav aria-label="breadcrumb" >
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
      </li>
      <li class="breadcrumb-item active">Blocks/Sectors</li>
    </ol>
  </nav>
    </div>
    <i class="ti tabler-building-estate text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>


  <div class="card">
    <div class="">
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">
          <div class="dt-actions-bar">
            <div id="dt-right-actions" class="d-none">
              <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="addBlockModel(event)" >
                  Add Block
                </button>
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
            <table id="blocks_table" class="table table_to_reload datatables-users">
              <thead class="bg-label-primary">
              <tr>
                <th><input type="checkbox" class="form-check-input" id="select_all"></th>
                <th>Name</th>
                <th>Society</th>
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
  @include('_partials._modals.add_edit_blocks')
@endsection

@push('scripts')
  <script>
    $(function() {
      // DataTable initialization
      let table = $('#blocks_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: `/blocks/index`,
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
          orderable: false,
          searchable: false
        },
          {
            data: 'name'
          },
          {
            data: 'society'
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
            <button class="btn btn-danger d-none bulk_delete_btn" data-url="{{ route('blocks.bulk_delete') }}">
              Bulk Delete
            </button>
          `);
        }
      });
    });

    function addBlockModel(e) {
      e.preventDefault();
      $('#block_id').val('');
      $('#name').val('');
      $('#society_id').val('');
      let modal = new bootstrap.Modal(document.getElementById('blockModal'));
      modal.show();
    }

    $(document).on('click', '.edit_block_btn', function () {
      let btn = $(this);
      $('#block_id').val(btn.data('id'));
      $('#name').val(btn.data('name'));
      $('#society_id').val(btn.data('society_id'));
      $('#modalTitle').text('Edit Block');
      // Show modal
      let modal = new bootstrap.Modal(document.getElementById('blockModal'));
      modal.show();
    });

  </script>
@endpush

@push('styles')
  <style>
    #color-picker-wrapper .pickr,
    #color-picker-wrapper .pickr .pcr-button {
      width: 100% !important;
    }

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

