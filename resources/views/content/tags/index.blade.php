@extends('layouts/layoutMaster')

@section('title', 'Tags')

@section('content')
  <h4 class="mb-1">Tags</h4>
  <nav aria-label="breadcrumb" class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Tags</li>
    </ol>
  </nav>
  <div class="card">
    <div class="">
      <div class="card-header pb-1">
        <div class="dt-scroll-wrapper">
          <div class="dt-actions-bar">
            <div id="dt-right-actions" class="d-none">
              <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tagModal"
                  onclick="openAddTagModal()">
                  Add Tag
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
            <table id="tags_table" class="table table_to_reload datatables-users">
              <thead class="bg-label-primary">
                <tr>
                  <th><input type="checkbox" class="form-check-input" id="select_all"></th>
                  <th>Name</th>
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
  @include('_partials._modals.add_edit_tags')

@endsection

@push('scripts')
  <script>
    let tagPickr = null;

    $(function() {
      // DataTable initialization
      let table = $('#tags_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: `/tags/index`,
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
            <button class="btn btn-danger d-none bulk_delete_btn" data-url="{{ route('tags.bulk_delete') }}">
              Bulk Delete
            </button>
          `);
        }
      });
    });

    function initTagColorPicker(defaultColor) {
      if (tagPickr) {
        tagPickr.destroy();
        tagPickr = null;
      }

      // Recreate the picker element so Pickr has a clean mount point
      const wrapper = document.querySelector('#color-picker-wrapper');
      if (!wrapper) return;
      wrapper.innerHTML = '<div id="color-picker-classic"></div>';

      const pickerElement = document.querySelector('#color-picker-classic');
      if (!pickerElement) return;
      tagPickr = Pickr.create({
        el: pickerElement,
        theme: 'classic',
        default: defaultColor,
        swatches: [
          'rgba(102, 108, 232, 1)',
          'rgba(40, 208, 148, 1)',
          'rgba(255, 73, 97, 1)',
          'rgba(255, 145, 73, 1)',
          'rgba(30, 159, 242, 1)'
        ],
        components: {
          preview: true,
          opacity: true,
          hue: true,
          interaction: {
            hex: true,
            rgba: true,
            hsla: true,
            hsva: true,
            cmyk: true,
            input: true,
            clear: true,
            save: true
          }
        }
      });

      // Capture the actual input value from the color picker input field
      tagPickr.on('change', (color) => {
        setTimeout(() => {
          const resultInput = document.querySelector('.pcr-app .pcr-interaction input.pcr-result');
          if (resultInput && resultInput.value) {
            document.getElementById('selectedColor').value = resultInput.value;
          }
        }, 10);
      });

      // When user types directly in the input
      tagPickr.on('changestop', (source, instance) => {
        const resultInput = document.querySelector('.pcr-app .pcr-interaction input.pcr-result');
        if (resultInput && resultInput.value) {
          document.getElementById('selectedColor').value = resultInput.value;
        }
      });

      // Save the selected color - capture from input field
      tagPickr.on('save', (color) => {
        const resultInput = document.querySelector('.pcr-app .pcr-interaction input.pcr-result');
        if (resultInput && resultInput.value) {
          document.getElementById('selectedColor').value = resultInput.value;
        } else if (color) {
          // Fallback to RGBA if input field not found
          document.getElementById('selectedColor').value = color.toRGBA().toString();
        }
        tagPickr.hide();
      });

      // Also listen to input changes on the Pickr input field
      setTimeout(() => {
        const resultInput = document.querySelector('.pcr-app .pcr-interaction input.pcr-result');
        if (resultInput) {
          resultInput.addEventListener('input', function() {
            document.getElementById('selectedColor').value = this.value;
          });
        }
      }, 500);
    }

    // Open Add Tag Modal
    function openAddTagModal() {
      document.getElementById('tagId').value = '';
      document.getElementById('name').value = '';
      document.getElementById('selectedColor').value = 'rgba(102, 108, 232, 1)';
      document.getElementById('modalTitle').textContent = 'Add Tag';

      setTimeout(() => {
        initTagColorPicker('rgba(102, 108, 232, 1)');
      }, 300);
    }

    // Edit Tag
    $(document).on('click', '.editTagBtn', function() {
      const btn = $(this);
      document.getElementById('tagId').value = btn.data('id');
      document.getElementById('name').value = btn.data('name');
      const tagColor = btn.data('color') || 'rgba(102, 108, 232, 1)';
      document.getElementById('selectedColor').value = tagColor;
      document.getElementById('modalTitle').textContent = 'Edit Tag';
      const modal = new bootstrap.Modal(document.getElementById('tagModal'));
      modal.show();

      setTimeout(() => {
        initTagColorPicker(tagColor);
      }, 300);
    });

    // Cleanup
    $('#tagModal').on('hidden.bs.modal', function() {
      if (tagPickr) {
        tagPickr.destroy();
        tagPickr = null;
      }
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
