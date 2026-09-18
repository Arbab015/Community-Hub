@extends('layouts/layoutMaster')

@section('title', 'Properties in ' . ucwords($block?->name ?? 'Block'))

@section('vendor-style')
  @parent
  @vite(['resources/assets/vendor/libs/spinkit/spinkit.scss', 'resources/assets/vendor/libs/notiflix/notiflix.scss', 'resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
  @parent
  @vite(['resources/assets/vendor/libs/notiflix/notiflix.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
  @parent
  @vite(['resources/assets/js/forms-selects.js', 'resources/assets/js/installment-plan-calculator.js', 'resources/assets/js/installment-template-preview.js'])
@endsection

@section('content')

  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div>
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">
        @if(isset($society) && $user_type)
          Society Management
        @else
          Property Management
        @endif</p>
      <h4 class="mb-1">Properties in {{ucwords($block->name) }}</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">

            <a href="{{ route('dashboard.analytics') }}">Home</a>
          </li>
          @if(isset($society))
            <li class="breadcrumb-item">
              <a href="{{ route('societies.index', $user_type) }}" class="text-dark opacity-75 text-decoration-none">Societies</a>
            </li>
            <li class="breadcrumb-item">
              <a href="{{ route('societies.show', [$user_type, $society->uuid]) }}"
                 class="text-dark opacity-75 text-decoration-none">Society</a>
            </li>
          @else
            <li class="breadcrumb-item">
              <a href="{{ route('blocks.index', "society_blocks") }}">Blocks</a>
            </li>
          @endif
          <li class="breadcrumb-item active">Block details</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-building-estate text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  @if (session('success'))
    <div class="alert alert-success">
      <i class="ti tabler-circle-check me-1"></i> {{ session('success') }}
    </div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger">
      <i class="ti tabler-alert-circle me-1"></i> {{ session('error') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li><i class="ti tabler-alert-circle me-1"></i>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- IMPORT PROGRESS BAR (hidden by default) --}}
  <div id="import_progress_wrapper" class="card mb-4 d-none">
    <div class="card-body py-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-2">
          <div class="spinner-border spinner-border-sm text-primary" id="import_spinner" role="status"></div>
          <span class="fw-semibold text-dark" id="import_status_text">Importing properties...</span>
        </div>
        <span class="badge bg-label-primary" id="import_percent_badge">0%</span>
      </div>
      <div class="progress" style="height: 10px; border-radius: 8px;">
        <div
          id="import_progress_bar"
          class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
          role="progressbar"
          style="width: 0%;"
          aria-valuenow="0"
          aria-valuemin="0"
          aria-valuemax="100">
        </div>
      </div>
      <p class="text-muted small mt-2 mb-0" id="import_sub_text">Please wait while your properties are being
        imported.</p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form id="filter_form" class="fiter_form" onsubmit="return false;">
        <div class="row g-4">

          <div class="col-12 col-md-4 mt-0">
            <label class="form-label text-uppercase fw-semibold">Search</label>
            <input type="text" class="form-control" name="search_content" id="search_content"
                   value="{{ request('search_content') }}" placeholder="Search by name or property no...">
          </div>

          <div class="col-12 col-md-4 mt-0">
            <label class="form-label text-uppercase fw-semibold"> Category</label>
            <select class="form-select" name="category" id="category" onchange="propertiesTable.ajax.reload()">
              <option value="">All</option>
              @foreach($property_categories as $category)
                <option
                  value="{{$category}}" {{  request('category') == $category ? 'selected' : "" }} > {{$category}}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-4 mt-0">
            <label class="form-label text-uppercase fw-semibold"> Property Type</label>
            <select class="form-select" id="type" name="type" onchange="propertiesTable.ajax.reload()">
              <option value="">All</option>
              @foreach($property_types as $type)
                <option value="{{$type}}" {{  request('type') == $type ? 'selected' : "" }} > {{$type}}</option>
              @endforeach
            </select>
          </div>
        </div>
      </form>
    </div>
  </div>





  <div class="card">
    <div class="">
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">
          <div class="dt-actions-bar">
            <div id="dt-right-actions" class="d-none">
              <div class="d-flex align-items-center gap-2">

                {{-- Shown only while properties are selected --}}
                <button type="button" id="bulk_delete_btn" class="btn btn-sm btn-danger d-none"
                        onclick="bulkDeleteProperties()">
                  <i class="ti tabler-trash me-1"></i> Bulk Delete
                </button>

                <button type="button" id="apply_payment_plan_btn" class="btn btn-sm btn-primary d-none"
                        onclick="applyPaymentPlanToProperties()">
                  <i class="ti tabler-file-invoice me-1"></i> Apply Payment Plan
                </button>

                <div class="dropdown">
                  <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-bs-toggle="dropdown"
                          aria-expanded="false" title="Actions">
                    <i class="ti tabler-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="javascript:void(0)" onclick="applyListingToBlock()">
                        <i class="ti tabler-tag text-success me-2"></i>Apply Listing
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item d-none" href="javascript:void(0)" id="view_errors_btn"
                         onclick="openErrorModal()">
                        <i class="ti tabler-alert-triangle text-warning me-2"></i>View Errors
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="javascript:void(0)"
                         onclick="openBlockPrintPlan('{{ $block->uuid }}')">
                        <i class="fa-solid fa-print text-primary me-2"></i>Print Plans
                      </a>
                    </li>
                    @can('create_property')
                      <li>
                        <a class="dropdown-item" href="javascript:void(0)" id="import_btn" onclick="showModel()">
                          <i class="fa-solid fa-upload text-secondary me-2"></i>Import
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item"
                           href="{{ isset($society) && $user_type ? route('society.property.create', [$user_type, $block->uuid]) : route('property.create', [$block->uuid]) }}">
                          <i class="ti tabler-plus text-primary me-2"></i>Add Property
                        </a>
                      </li>
                    @endcan
                  </ul>
                </div>

              </div>
            </div>
          </div>

          <!-- TABLE -->
          <div class="card-datatable">
            <table id="properties_table" class="table">
              <thead class="bg-label-primary">

              <tr>
                <th><input type="checkbox" class="form-check-input" id="select_all"></th>
                <th>Property Name</th>
                <th>Property No</th>
                <th>Category</th>
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

  @include('_partials._modals.import_properties')
  @include('_partials._modals.import_errors')
  @include('_partials._modals.apply_payment_plan')
  @include('_partials._modals.apply_listing')
  @include('_partials._modals.listing_property')
  @include('_partials._modals.print_block_payment_plan')

@endsection

@push('scripts')
  <script>
    var propertiesTable;

    document.addEventListener('DOMContentLoaded', function() {
      var searchTimer;
      document.getElementById('search_content').addEventListener('keyup', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
          propertiesTable.ajax.reload();
        }, 1000);
      });

      propertiesTable = $('#properties_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
          url: "{{ isset($society) && $user_type
        ? route('society.block.view', [$user_type, $society->uuid, $block->uuid])
        : route('block.view', $block->uuid) }}",
          data: function(d) {
            d.search_content = document.getElementById('search_content').value;
            d.category = document.getElementById('category').value;
            d.type = document.getElementById('type').value;
          }
        },
        dom: '<\'row align-items-center\'' +
          '<\'col-sm-6 col-12 d-flex align-items-center gap-2 mb-2 mb-sm-0\'l f <\'#bulk-delete-wrap\'>>' +
          '<\'col-sm-6 col-12 d-flex justify-content-sm-end justify-content-start\'<\'dt-actions\'>>' +
          '>' +
          '<\'row\'<\'col-12\'tr>>' +
          '<\'row mt-3 align-items-center\'' +
          '<\'col-md-6\'i>' +
          '<\'col-md-6 d-flex justify-content-end\'p>' +
          '>',
        columns: [{ data: 'checkbox', orderable: false, searchable: false },
          { data: 'name' },
          { data: 'property_no' },
          { data: 'category' },
            @if ($show_actions)
          {
            data: 'actions',
            orderable: false,
            searchable: true
          },
          @endif
        ],
        initComplete: function() {
          $('.dt-actions').html($('#dt-right-actions').removeClass('d-none'));

          propertiesTable.on('draw', function() {
            document.querySelectorAll('.property-checkbox').forEach(function(cb) {
              cb.checked = selectedProperties.has(cb.value);
            });
            syncSelectAllHeader();
          });
        }
      });

      fetch("{{ route('properties.import_properties', $block->uuid) }}")
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (data.status === 'processing') {
            setImportBtnDisabled(true);
            startImportPolling();
          } else if (data.status === 'completed' && data.has_errors) {
            setViewErrorsBtnVisible(true);
          }
        });
    });

    function showModel() {
      var modal = new bootstrap.Modal(document.getElementById('importPropertiesModal'));
      document.getElementById('file').value = '';
      modal.show();
    }

    function setImportBtnDisabled(disabled) {
      var btn = document.getElementById('import_btn');
      btn.disabled = disabled;
      btn.innerHTML = disabled
        ? '<i class="ti tabler-loader me-1"></i> Import in progress...'
        : '<i class="fa-solid fa-upload me-1"></i> Import';
    }

    function setViewErrorsBtnVisible(visible) {
      var btn = document.getElementById('view_errors_btn');
      if (!btn) return;
      btn.classList.toggle('d-none', !visible);
    }

    var error_files = [];

    function openErrorModal() {
      document.getElementById('error_files_loading').classList.remove('d-none');
      document.getElementById('error_files_empty').classList.add('d-none');
      document.getElementById('error_files_table_wrapper').classList.add('d-none');
      document.getElementById('error_files_tbody').innerHTML = '';

      var modal = new bootstrap.Modal(document.getElementById('importErrorsModal'));
      modal.show();

      fetch("{{ route('properties.import_errors_files', $block->uuid) }}")
        .then(function(res) { return res.json(); })
        .then(function(data) {
          document.getElementById('error_files_loading').classList.add('d-none');
          var files = data.import_error_files;

          if (files.length === 0) {
            document.getElementById('error_files_empty').classList.remove('d-none');
            return;
          }

          document.getElementById('error_files_table_wrapper').classList.remove('d-none');
          var tbody = document.getElementById('error_files_tbody');

          files.forEach(function(file) {
            var d = new Date(file.created_at);
            var dateStr = d.toLocaleString('en-US', {
              month: 'short', day: '2-digit', year: 'numeric',
              hour: '2-digit', minute: '2-digit', hour12: true
            });

            var errorFileName = file.file_name
              ? file.file_name.replace(/\.csv$/i, '') + '_errors_.csv'
              : 'import_errors_.csv';
            var downloadUrl = "{{ route('properties.import_errors_download', $block->uuid) }}";

            var row = '<tr>' +
              '<td class="ps-4"><span class="text-muted small">' + dateStr + '</span></td>' +
              '<td><div class="d-flex align-items-center gap-2">' +
              '<i class="ti tabler-file-text text-secondary"></i>' +
              '<span class="small fw-semibold text-truncate" style="max-width:200px" title="' + file.file_name + '">' + file.file_name + '</span>' +
              '</div></td>' +
              '<td><div class="d-flex align-items-center gap-2">' +
              '<i class="ti tabler-file-alert text-warning"></i>' +
              '<span class="small fw-semibold text-truncate" style="max-width:200px" title="' + errorFileName + '">' + errorFileName + '</span>' +
              '</div></td>' +
              '<td class="pe-4"><div class="d-flex justify-content-end gap-2">' +
              '<a href="' + downloadUrl + '" class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center p-0" style="width:30px;height:30px" title="Download error file"><i class="ti tabler-download"></i></a>' +
              '<button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center justify-content-center p-0" style="width:30px;height:30px" title="Delete error file" onclick="deleteErrorFile(this)"><i class="ti tabler-trash"></i></button>' +
              '</div></td></tr>';

            tbody.insertAdjacentHTML('beforeend', row);
          });
        })
        .catch(function() {
          document.getElementById('error_files_loading').classList.add('d-none');
          document.getElementById('error_files_empty').classList.remove('d-none');
        });
    }

    function deleteErrorFile(btn) {
      btn.disabled = true;
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
      }).then(function(result) {
        if (!result.isConfirmed) return;
        fetch("{{ route('properties.import_errors_delete', $block->uuid) }}", {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(function(res) { return res.json(); })
        .then(function() {
          btn.closest('tr').remove();
          if (document.getElementById('error_files_tbody').children.length === 0) {
            document.getElementById('error_files_table_wrapper').classList.add('d-none');
            document.getElementById('error_files_empty').classList.remove('d-none');
            setViewErrorsBtnVisible(false);
          }
        })
        .catch(function() {
          btn.disabled = false;
          alert('Could not delete the error file. Please try again.');
        });
      });
    }

    document.querySelector('#importPropertiesModal form').addEventListener('submit', function(e) {
      e.preventDefault();
      var form = this;
      var modalEl = document.getElementById('importPropertiesModal');

      Block.standard('#section-block', {
        backgroundColor: 'rgba(' + window.Helpers.getCssVar('black-rgb') + ', 0.5)',
        svgSize: '0px'
      });

      var customSpinner = document.createElement('div');
      customSpinner.classList.add('spinner-border', 'text-primary');
      customSpinner.setAttribute('role', 'status');
      document.querySelector('#section-block .notiflix-block').appendChild(customSpinner);

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form)
      })
      .then(function(res) {
        return res.json().then(function(data) {
          Block.remove('#section-block');
          (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();

          if (!res.ok) {
            if (data.errors) {
              showImportErrors(Object.values(data.errors).flat());
            } else if (data.message) {
              showImportErrors([data.message]);
            } else {
              showImportErrors(['Import failed. Please try again.']);
            }
            return;
          }

          if (data.errors) {
            showImportErrors(data.errors);
          } else {
            setImportBtnDisabled(true);
            startImportPolling();
          }
        });
      })
      .catch(function() {
        Block.remove('#section-block');
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
        showImportErrors(['Import failed. Please try again.']);
      });
    });

    function showImportErrors(errors) {
      if (window._importInterval) {
        clearInterval(window._importInterval);
        window._importInterval = null;
      }
      setImportBtnDisabled(false);

      var html = errors.map(function(e) { return '<li class="small">' + e + '</li>'; }).join('');
      var alertHtml = '<div class="alert alert-danger alert-dismissible fade show" id="import_error_alert">' +
        '<strong><i class="ti tabler-alert-circle me-1"></i>Import failed \u2014 ' + errors.length + ' error(s):</strong>' +
        '<hr class="my-1">' +
        '<ul class="mb-0 mt-2 ps-3" style="max-height:150px;overflow-y:auto">' + html + '</ul>' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';

      var old = document.getElementById('import_error_alert');
      if (old) old.remove();
      document.querySelector('.card.mb-4').insertAdjacentHTML('beforebegin', alertHtml);
    }

    function startImportPolling() {
      var wrapper = document.getElementById('import_progress_wrapper');
      var bar = document.getElementById('import_progress_bar');
      var badge = document.getElementById('import_percent_badge');
      var spinner = document.getElementById('import_spinner');
      var status = document.getElementById('import_status_text');
      var subText = document.getElementById('import_sub_text');

      wrapper.classList.remove('d-none');
      bar.style.width = '0%';
      bar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-primary';
      badge.textContent = '0%';
      spinner.classList.remove('d-none');
      status.textContent = 'Importing properties\u2026';
      subText.textContent = 'Please wait while your properties are being imported.';

      if (window._importInterval) clearInterval(window._importInterval);

      window._importInterval = setInterval(function() {
        fetch("{{ route('properties.import_properties', $block->uuid) }}")
          .then(function(res) { return res.json(); })
          .then(function(data) {
            console.log(data);
            var p = parseInt(data.progress) || 0;
            bar.style.width = p + '%';
            bar.setAttribute('aria-valuenow', p);
            badge.textContent = p + '%';

            if (data.status === 'completed') {
              clearInterval(window._importInterval);
              window._importInterval = null;
              spinner.classList.add('d-none');
              setImportBtnDisabled(false);

              if (data.has_errors) {
                bar.className = 'progress-bar bg-warning';
                status.textContent = 'Import completed with some errors.';
                subText.textContent = 'Some rows were skipped. Use the "View Errors" button to download the error report.';
                setViewErrorsBtnVisible(true);
              } else {
                bar.className = 'progress-bar bg-success';
                status.textContent = 'Import completed successfully!';
                subText.textContent = 'All properties were imported.';
                setViewErrorsBtnVisible(false);
                setTimeout(function() {
                  window.location.reload();
                }, 2000);
              }
            }
          });
      }, 1000);
    }

    function openBlockPrintPlan(uuid) {
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

    var selectedProperties = new Set();

    function syncSelectAllHeader() {
      var boxes = Array.from(document.querySelectorAll('.property-checkbox'));
      var header = document.getElementById('select_all');
      if (!header) return;
      header.checked = boxes.length > 0 && boxes.every(function(cb) { return cb.checked; });
    }

    function updatePropertySelectionBar() {
      var hasSelection = selectedProperties.size > 0;
      document.getElementById('bulk_delete_btn').classList.toggle('d-none', !hasSelection);
      document.getElementById('apply_payment_plan_btn').classList.toggle('d-none', !hasSelection);
    }

    document.addEventListener('change', function(e) {
      if (!e.target.classList.contains('property-checkbox')) return;

      if (e.target.checked) {
        selectedProperties.add(e.target.value);
      } else {
        selectedProperties.delete(e.target.value);
      }
      syncSelectAllHeader();
      updatePropertySelectionBar();
    });

    document.getElementById('select_all').addEventListener('change', function() {
      var checked = this.checked;
      document.querySelectorAll('.property-checkbox').forEach(function(cb) {
        cb.checked = checked;
        if (checked) {
          selectedProperties.add(cb.value);
        } else {
          selectedProperties.delete(cb.value);
        }
      });
      updatePropertySelectionBar();
    });

    function applyPaymentPlanToProperties() {
      if (!selectedProperties.size) return;
      document.getElementById('apply_plan_selected_count').textContent = selectedProperties.size;

      var form = document.getElementById('apply_payment_plan_form');
      form.querySelectorAll('input[name="ids[]"]').forEach(function(el) { el.remove(); });
      selectedProperties.forEach(function(uuid) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = uuid;
        form.appendChild(input);
      });

      document.getElementById('apply_plan_type').value = 'property';
      new bootstrap.Modal(document.getElementById('applyPaymentPlanModal')).show();
    }

    function bulkDeleteProperties() {
      if (!selectedProperties.size) return;

      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete them!'
      }).then(function(result) {
        if (!result.isConfirmed) return;
        Swal.fire({
          title: 'Deleting...',
          allowOutsideClick: false,
          showConfirmButton: false,
          didOpen: function() { Swal.showLoading(); }
        });

        var formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
        Array.from(selectedProperties).forEach(function(id) {
          formData.append('ids[]', id);
        });

        fetch("{{ route('properties.bulk_delete') }}", {
          method: 'POST',
          body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
          Swal.fire('Deleted', res.message, 'success');
          selectedProperties.clear();
          propertiesTable.ajax.reload(null, false);
          updatePropertySelectionBar();
        })
        .catch(function() {
          Swal.fire('Error!', 'Something went wrong.', 'error');
        });
      });
    }

    function applyListingToBlock() {
      document.getElementById('listing_block_uuid').value = '{{ $block->uuid }}';
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

    document.getElementById('sell_rent_modal').addEventListener('show.bs.modal', function(e) {
      var btn = e.relatedTarget;
      if (!btn) return;

      var itemType = btn.dataset.type || 'property';
      var itemId = btn.dataset.id || '';
      var listing = btn.dataset.listing ? JSON.parse(btn.dataset.listing) : null;
      var form = document.getElementById('listing_form');

      form.querySelector('[name="type"]').value = itemType;
      form.querySelector('[name="id"]').value = itemId;
      form.action = "{{ route('listing.store') }}";

      document.getElementById('modal_title').textContent = listing ? 'Update Listing' : 'List Property';
      document.getElementById('single_price').classList.remove('d-none');
      document.getElementById('btn_text').textContent = 'Submit Listing';

      if (listing) {
        document.getElementById('type_' + listing.listing_type).checked = true;
        document.getElementById('price').value = listing.price || '';
        document.getElementById('unit').value = listing.unit || 'PKR';
        document.getElementById('show_price').checked = listing.show_price ? true : false;
        document.getElementById('contact').value = listing.contact_no || '';
        document.getElementById('show_contact').checked = listing.show_contact_no ? true : false;
        document.getElementById('email').value = listing.email || '';
        document.getElementById('description').value = listing.description || '';
      } else {
        document.getElementById('type_sell').checked = true;
        document.getElementById('price').value = '';
        document.getElementById('unit').value = 'PKR';
        document.getElementById('show_price').checked = true;
        document.getElementById('contact').value = '';
        document.getElementById('show_contact').checked = true;
        document.getElementById('email').value = '';
        document.getElementById('description').value = '';
      }
    });
  </script>
@endpush

@push('styles')
  <style>
    .import_errors {
      max-height: 150px;
      overflow-y: auto;
    }

    .column-title {
      font-size: 11px;
      letter-spacing: .05em;
      text-transform: uppercase
    }

    .dt-search {
      display: none !important;
    }

    .dt-search label,
    .dataTables_filter label {
      display: none !important;
    }

    .dt-length label,
    .dataTables_length label {
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
