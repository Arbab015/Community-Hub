@extends('layouts/layoutMaster')

@section('title', 'Rules')

@section('content')
  <h4 class="mb-1">Society Rules</h4>
  <nav aria-label="breadcrumb" class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Rules</li>
    </ol>
  </nav>
  <div class="card">
    <div class="">
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">
          <div class="dt-actions-bar">
            <div id="dt-right-actions" class="d-none">
              <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="addRuleModel(event)" >
                  Add Rule
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
            <table id="rules_table" class="table table_to_reload datatables-users">
              <thead class="bg-label-primary">
              <tr>
                <th><input type="checkbox" class="form-check-input" id="select_all"></th>
                <th>Name</th>
                <th>Description</th>
                <th>Related To</th>
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
  @include('_partials._modals.add_edit_rules')

@endsection

@push('scripts')
  <script>

    $(function() {
      // DataTable initialization
      let table = $('#rules_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: `/rules/index`,
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
            data: 'description'
          },{
            data: 'related_to'
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
            <button class="btn btn-danger d-none bulk_delete_btn" data-url="{{ route('rules.bulk_delete') }}">
              Bulk Delete
            </button>
          `);
        }
      });
    });

    function addRuleModel(e) {
      e.preventDefault();
      $('#ruleId').val('');
      $('#name').val('');
      $('#description').val('');
      $('#modalTitle').text('Add Rule');
      $('input[name="related_to[]"]').prop('checked', false);
      let modal = new bootstrap.Modal(document.getElementById('ruleModal'));
      modal.show();
    }

    $(document).on('click', '.editRuleBtn', function () {
      let btn = $(this);
      $('#ruleId').val(btn.data('id'));
      $('#name').val(btn.data('name'));
      $('#description').val(btn.data('description'));
      // Change modal title
      $('#modalTitle').text('Edit Rule');
      // Uncheck all checkboxes first
      $('input[name="related_to[]"]').prop('checked', false);
      // Get related_to array
      let related = btn.data('related_to');

      if (related && related.length) {
        related.forEach(function (item) {
          $('input[name="related_to[]"][value="' + item + '"]').prop('checked', true);
        });
      }
      // Show modal
      let modal = new bootstrap.Modal(document.getElementById('ruleModal'));
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
