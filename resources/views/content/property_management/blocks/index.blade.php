@extends('layouts/layoutMaster')

@section('title', 'Blocks/Sectors')

@section('vendor-style')
  @parent
  @vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
  @parent
  @vite(['resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
  @parent
  @vite(['resources/assets/js/forms-selects.js'])
@endsection

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Property Management</p>
      <h4 class="mb-1">Society Blocks/Sectors</h4>
      <nav aria-label="breadcrumb">
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

  <div class="card">
    <div class="">
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">
          <div class="dt-actions-bar">
            <div id="dt-right-actions" class="d-none">
              <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="addBlockModel(event)">
                  Add Block
                </button>
              </div>
            </div>
          </div>

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
  @include('_partials._modals.apply_block_payment_plan')
  @include('_partials._modals.print_block_payment_plan')
  @include('_partials._modals.pricing_&_paymentPlans')
  @include('_partials._modals.apply_listing')

@endsection

@push('scripts')
  <script>
    $(function() {
      var table = $('#blocks_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '/blocks/index',
        dom: '<\'row align-items-center\'' +
          '<\'col-sm-9 col-12 d-flex align-items-center gap-2 mb-2 mb-sm-0\'l f <\'#bulk-delete-wrap\'>>' +
          '<\'col-sm-3 col-12 d-flex justify-content-sm-end justify-content-start\'<\'dt-actions\'>>' +
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
          $('#bulk-delete-wrap').html(
            '<button class="btn btn-sm btn-danger d-none bulk_btn" data-url="{{ route('blocks.bulk_delete') }}">Bulk Delete</button>' +
            '<button class="btn btn-sm btn-primary d-none" id="bulk_apply_plan_btn" onclick="applyPaymentPlanToBlocks()"><i class="ti tabler-file-invoice me-1"></i> Apply Payment Plan</button>' +
            '<button class="btn btn-sm btn-success d-none" id="bulk_apply_listing_btn" onclick="applyListingToBlocks()"><i class="ti tabler-tag me-1"></i> Apply Listing</button>'
          );
        }
      });

      var syncBulkBtns = function() {
        var planBtn = document.getElementById('bulk_apply_plan_btn');
        var listBtn = document.getElementById('bulk_apply_listing_btn');
        if (!planBtn || !listBtn) return;
        var hasChecked = !!document.querySelectorAll('.checkbox:checked').length;
        planBtn.classList.toggle('d-none', !hasChecked);
        listBtn.classList.toggle('d-none', !hasChecked);
      };
      document.addEventListener('change', function(e) {
        if (e.target.classList.contains('checkbox') || e.target.id === 'select_all') {
          syncBulkBtns();
        }
      });
      table.on('draw', syncBulkBtns);
    });

    function applyPaymentPlanToBlocks() {
      var checkboxes = document.querySelectorAll('.checkbox:checked');
      var ids = Array.from(checkboxes).map(function(cb) { return cb.value; });
      if (!ids.length) return;

      document.getElementById('block_plan_selected_count').textContent = ids.length;

      var form = document.getElementById('apply_block_payment_plan_form');
      form.querySelectorAll('input[name="block_ids[]"]').forEach(function(el) { el.remove(); });
      ids.forEach(function(id) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'block_ids[]';
        input.value = id;
        form.appendChild(input);
      });

      new bootstrap.Modal(document.getElementById('applyBlockPaymentPlanModal')).show();
    }

    function applyListingToBlocks() {
      var checkboxes = document.querySelectorAll('.checkbox:checked');
      var uuids = Array.from(checkboxes).map(function(cb) {
        return cb.closest('tr').querySelector('.checkbox').dataset.uuid;
      });
      if (!uuids.length) return;

      var form = document.getElementById('apply_listing_form');
      document.getElementById('listing_block_uuid').value = '';
      form.querySelectorAll('input[name="block_uuids[]"]').forEach(function(el) { el.remove(); });

      uuids.forEach(function(uuid) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'block_uuids[]';
        input.value = uuid;
        form.appendChild(input);
      });

      new bootstrap.Modal(document.getElementById('applyListingModal')).show();
    }

    document.getElementById('applyListingModal').addEventListener('change', function(e) {
      if (e.target.name === 'listing_type') {
        var isRent = e.target.value === 'rent';
        document.getElementById('listing_price_wrapper').classList.toggle('d-none', isRent);
        document.getElementById('listing_show_price').closest('.form-check.form-switch').classList.toggle('d-none', isRent);
        if (isRent) {
          document.getElementById('listing_price').value = '';
        }
      }
    });

    document.getElementById('applyListingModal').addEventListener('hidden.bs.modal', function() {
      this.querySelectorAll('input[name="block_uuids[]"]').forEach(function(el) { el.remove(); });
    });

    function addBlockModel(e) {
      e.preventDefault();
      document.getElementById('block_id').value = '';
      document.getElementById('name').value = '';
      var hiddenSociety = document.getElementById('hidden_society_id');
      if (hiddenSociety) hiddenSociety.remove();
      var societySelect = document.getElementById('society_id');
      societySelect.value = '';
      societySelect.disabled = false;
      var modal = new bootstrap.Modal(document.getElementById('blockModal'));
      modal.show();
    }

    document.addEventListener('click', function(e) {
      var printBtn = e.target.closest('.block_print_plan_btn');
      if (printBtn) {
        var uuid = printBtn.dataset.uuid;

        document.getElementById('block_print_loading').classList.add('d-none');
        document.getElementById('block_print_status').classList.add('d-none');
        document.getElementById('block_print_submit').classList.add('d-none');
        document.getElementById('block_print_size').value = '';
        document.getElementById('block_print_unit').value = 'marla';
        document.getElementById('block_print_corner').checked = false;
        document.getElementById('block_print_park_facing').checked = false;
        document.getElementById('block_print_main_boulevard').checked = false;

        document.getElementById('printBlockPaymentPlanModal').dataset.blockUuid = uuid;
        new bootstrap.Modal(document.getElementById('printBlockPaymentPlanModal')).show();
      }

      var editBtn = e.target.closest('.edit_block_btn');
      if (editBtn) {
        document.getElementById('block_id').value = editBtn.dataset.id;
        document.getElementById('name').value = editBtn.dataset.name;
        document.getElementById('society_id').value = editBtn.dataset.societyId;
        document.getElementById('society_id').disabled = true;
        var hiddenInput = document.getElementById('hidden_society_id');
        if (hiddenInput) hiddenInput.value = editBtn.dataset.societyId;
        document.getElementById('modalTitle').textContent = 'Edit Block';
        var modal = new bootstrap.Modal(document.getElementById('blockModal'));
        modal.show();
      }

      var detailsBtn = e.target.closest('.block_details_btn');
      if (detailsBtn) {
        var uuid = detailsBtn.dataset.uuid;
        var name = detailsBtn.dataset.name;

        document.getElementById('block_details_name').textContent = name;

        document.getElementById('price_ranges_loading').classList.remove('d-none');
        document.getElementById('price_ranges_empty').classList.add('d-none');
        document.getElementById('price_ranges_table_wrapper').classList.add('d-none');
        document.getElementById('price_ranges_tbody').innerHTML = '';
        document.getElementById('price_range_count').textContent = '0';

        document.getElementById('payment_plans_loading').classList.remove('d-none');
        document.getElementById('payment_plans_empty').classList.add('d-none');
        document.getElementById('payment_plans_table_wrapper').classList.add('d-none');
        document.getElementById('payment_plans_tbody').innerHTML = '';
        document.getElementById('payment_plan_count').textContent = '0';

        var modal = new bootstrap.Modal(document.getElementById('blockDetailsModal'));
        modal.show();

        fetch('/blocks/' + uuid + '/details')
          .then(function(res) { return res.json(); })
          .then(function(res) {
            document.getElementById('price_ranges_loading').classList.add('d-none');
            document.getElementById('payment_plans_loading').classList.add('d-none');

            if (res.priceRanges.length === 0) {
              document.getElementById('price_ranges_empty').classList.remove('d-none');
            } else {
              document.getElementById('price_range_count').textContent = res.priceRanges.length;
              var tbody = document.getElementById('price_ranges_tbody');
              res.priceRanges.forEach(function(r) {
                var row = '<tr>' +
                  '<td>' + (r.min_size ?? '\u2014') + '</td>' +
                  '<td>' + (r.max_size ?? '\u221E') + '</td>' +
                  '<td>' + Number(r.price).toLocaleString() + '</td>' +
                  '<td><span class="badge bg-label-secondary">' + r.price_unit + '</span></td>' +
                  '</tr>';
                tbody.insertAdjacentHTML('beforeend', row);
              });
              document.getElementById('price_ranges_table_wrapper').classList.remove('d-none');
            }

            if (res.paymentPlans.length === 0) {
              document.getElementById('payment_plans_empty').classList.remove('d-none');
            } else {
              document.getElementById('payment_plan_count').textContent = res.paymentPlans.length;
              var tbody2 = document.getElementById('payment_plans_tbody');
              res.paymentPlans.forEach(function(p) {
                var sizeRange = (p.min_size ?? '\u2014') + ' - ' + (p.max_size ?? '\u221E') + ' ' + p.size_unit;
                var typeBadge = p.is_commercial
                  ? '<span class="badge bg-label-success">Commercial</span>'
                  : '<span class="badge bg-label-primary">Non-Commercial</span>';
                var tplBadge = p.payment_template
                  ? '<span class="badge bg-label-warning">' + p.payment_template.name + '</span>'
                  : '<span class="text-muted">\u2014</span>';
                var instBadges = p.installment_templates.length > 0
                  ? p.installment_templates.map(function(n) {
                    return '<span class="badge bg-label-info">' + n + '</span>';
                  }).join(' ')
                  : '<span class="text-muted">\u2014</span>';

                var row2 = '<tr>' +
                  '<td>' + sizeRange + '</td>' +
                  '<td>' + typeBadge + '</td>' +
                  '<td>' + tplBadge + '</td>' +
                  '<td>' + instBadges + '</td>' +
                  '</tr>';
                tbody2.insertAdjacentHTML('beforeend', row2);
              });
              document.getElementById('payment_plans_table_wrapper').classList.remove('d-none');
            }
          })
          .catch(function() {
            document.getElementById('price_ranges_loading').classList.add('d-none');
            document.getElementById('payment_plans_loading').classList.add('d-none');
            document.getElementById('price_ranges_empty').classList.remove('d-none');
            document.getElementById('payment_plans_empty').classList.remove('d-none');
          });
      }
    });

    document.getElementById('block_print_size').addEventListener('input', checkPaymentPlanAvailability);

    ['block_print_unit', 'block_print_corner', 'block_print_park_facing', 'block_print_main_boulevard'].forEach(function(id) {
      document.getElementById(id).addEventListener('change', checkPaymentPlanAvailability);
    });
    document.querySelectorAll('input[name="block_print_type"]').forEach(function(el) {
      el.addEventListener('change', checkPaymentPlanAvailability);
    });

    function getSelectedFeatures() {
      var features = [];
      if (document.getElementById('block_print_corner').checked) features.push('corner_premium');
      if (document.getElementById('block_print_park_facing').checked) features.push('park_facing_premium');
      if (document.getElementById('block_print_main_boulevard').checked) features.push('main_boulevard_premium');
      return features;
    }

    function checkPaymentPlanAvailability() {
      var uuid = document.getElementById('printBlockPaymentPlanModal').dataset.blockUuid;
      var size = document.getElementById('block_print_size').value;
      var unit = document.getElementById('block_print_unit').value;
      var typeEl = document.querySelector('input[name="block_print_type"]:checked');
      var type = typeEl ? typeEl.value : 'non_commercial';
      var features = getSelectedFeatures();

      document.getElementById('block_print_submit').classList.add('d-none');
      if (!uuid || !size || parseFloat(size) <= 0) {
        document.getElementById('block_print_status').classList.remove('d-none', 'alert-success', 'alert-danger');
        document.getElementById('block_print_status_text').textContent = 'Enter a property size to check availability.';
        return;
      }

      document.getElementById('block_print_status').classList.remove('d-none', 'alert-success', 'alert-danger');
      document.getElementById('block_print_status').classList.add('text-muted');
      document.getElementById('block_print_loading').classList.remove('d-none');
      document.getElementById('block_print_status_text').textContent = 'Checking availability...';

      fetch('/blocks/' + uuid + '/print-plan/available?type=' + encodeURIComponent(type) + '&size=' + encodeURIComponent(size) + '&unit=' + encodeURIComponent(unit))
        .then(function(res) { return res.json(); })
        .then(function(res) {
          document.getElementById('block_print_loading').classList.add('d-none');
          if (res.available) {
            document.getElementById('block_print_status').classList.remove('alert-danger', 'text-muted');
            document.getElementById('block_print_status').classList.add('alert', 'alert-success');
            document.getElementById('block_print_status_text').innerHTML = '<i class="ti tabler-circle-check me-1"></i>' + res.message;
            var printUrl = '/blocks/' + uuid + '/print-plan?type=' + encodeURIComponent(type) + '&size=' + encodeURIComponent(size) + '&unit=' + encodeURIComponent(unit);
            if (features.length > 0) {
              printUrl += '&features=' + features.join(',');
            }
            document.getElementById('block_print_submit').classList.remove('d-none');
            document.getElementById('block_print_submit').href = printUrl;
          } else {
            document.getElementById('block_print_status').classList.remove('text-muted');
            document.getElementById('block_print_status').classList.add('alert', 'alert-danger');
            document.getElementById('block_print_status_text').innerHTML = '<i class="ti tabler-alert-triangle me-1"></i>' + res.message;
          }
        })
        .catch(function() {
          document.getElementById('block_print_loading').classList.add('d-none');
          document.getElementById('block_print_status').classList.remove('text-muted');
          document.getElementById('block_print_status').classList.add('alert', 'alert-danger');
          document.getElementById('block_print_status_text').textContent = 'Could not verify availability. Please try again.';
        });
    }
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

