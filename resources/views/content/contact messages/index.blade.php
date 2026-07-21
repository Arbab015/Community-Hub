@extends('layouts/layoutMaster')

@section('title', 'Contact Messages')

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <h4 class="mb-1">Contact messages</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          <li class="breadcrumb-item active">Messages</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-message text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  <div class="card">
    <div class="">
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">

          @if(session('success'))
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
            <table id="messages_table" class="table table_to_reload datatables-users">
              <thead class="bg-label-primary">
              <tr>
                <th><input type="checkbox" class="form-check-input" id="select_all"></th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Replied</th>
                <th>Message</th>
                @if($show_actions)
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
  @include('_partials._modals.reply_on_contact_message')

@endsection

@push('scripts')
  <script>

    $(function() {
      // DataTable initialization
      let table = $('#messages_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: `/messages/index`,
        dom: '<\'row align-items-center\'' +
          '<\'col-sm-6 col-12 d-flex align-items-center gap-2 mb-2 mb-sm-0\'l f <\'#bulk-delete-wrap\'>>' +
          '<\'col-sm-6 col-12 d-flex justify-content-sm-end justify-content-start\'<\'dt-actions\'>>' +
          '>' +
          '<\'row\'<\'col-12\'tr>>' +
          '<\'row mt-3 align-items-center\'' +
          '<\'col-md-6\'i>' +
          '<\'col-md-6 d-flex justify-content-end\'p>' +
          '>',
        columns: [{
          data: 'checkbox',
          orderable: false,
          searchable: false
        },
          {
            data: 'full_name'
          },
          {
            data: 'email'
          },
          {
            data: 'replied'
          },
          {
            data: 'message'
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
            <button class="btn btn-sm btn-danger d-none bulk_btn" data-url="{{ route('messages.bulk_delete') }}">
              Bulk Delete
            </button>
          `);
        }
      });
    });


    $(document).on('click', '.reply_on_contact', function() {
      let icon = $(this);
      let email = icon.data('email');
      let messageId = icon.closest('tr').find('.checkbox').val(); // grabs the row's id via checkbox value

      $('#reply_to_email').val(email);
      $('#reply_message_id').val(messageId);
      $('#reply_subject').val('Re: Your message to us');
      $('#reply_body').val('');

      let modal = new bootstrap.Modal(document.getElementById('replyModal'));
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

      .dt-scroll-wrapper > * {
        min-width: 1100px;
      }

      table.dataTable {
        white-space: nowrap;
      }
    }


  </style>
@endpush
