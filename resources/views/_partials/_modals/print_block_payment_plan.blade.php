<div class="modal fade" id="printBlockPaymentPlanModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="print_modal_title">Print Block Payment Plan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="text-muted mb-3">Pick the plan type & size to generate the block-level statement.</p>

        {{-- PLAN TYPE --}}
        <div class="mb-3">
          <label class="block-field-label"><i class="ti tabler-building-store me-1"></i> Property Type</label>
          <div class="block-segmented">
            <input type="radio" class="seg-radio" name="plan_type" id="commercial"
                   value="commercial">
            <label class="seg-opt seg-commercial" for="commercial">
              <i class="ti tabler-building-store"></i>
              <span>Commercial</span>
            </label>

            <input type="radio" class="seg-radio" name="plan_type" id="non_commercial"
                   value="non_commercial" checked>
            <label class="seg-opt seg-residential" for="non_commercial">
              <i class="ti tabler-home"></i>
              <span>Non-Commercial</span>
            </label>
          </div>
        </div>

        {{-- CONSTRUCTION TYPE --}}
        <div class="mb-3">
          <label class="block-field-label"><i class="ti tabler-layers-intersect me-1"></i> Construction Status</label>
          <div class="block-segmented">
            <input type="radio" class="seg-radio" name="plan_constructed" id="constructed" value="1">
            <label class="seg-opt seg-constructed" for="constructed">
              <i class="ti tabler-building-bridge-2"></i>
              <span>Constructed</span>
            </label>

            <input type="radio" class="seg-radio" name="plan_constructed" id="non_constructed"
                   value="0" checked>
            <label class="seg-opt seg-non-constructed" for="non_constructed">
              <i class="ti tabler-building-skyscraper"></i>
              <span>Non-Constructed</span>
            </label>
          </div>
        </div>

        {{-- PROPERTY FEATURES (premiums) --}}
        <div class="mb-3">
          <label class="block-field-label"><i class="ti tabler-star me-1"></i> Property Features</label>
          <p class="text-muted small mb-2">Select applicable features to include premiums.</p>
          <div class="d-flex flex-wrap gap-2">
            <input type="checkbox" class="feature-chip-input" id="corner" value="1">
            <label class="feature-chip" for="corner">
              <i class="ti tabler-corner-down-right-double"></i> Corner
            </label>

            <input type="checkbox" class="feature-chip-input" id="park_facing" value="1">
            <label class="feature-chip" for="park_facing">
              <i class="ti tabler-trees"></i> Park Facing
            </label>

            <input type="checkbox" class="feature-chip-input" id="main_boulevard" value="1">
            <label class="feature-chip" for="main_boulevard">
              <i class="ti tabler-road"></i> Main Boulevard
            </label>
          </div>
        </div>

        {{-- SPECIFIC SIZE + LIVE CHECK --}}
        <div class="mb-3">
          <label class="block-field-label"><i class="ti tabler-ruler me-1"></i> Property Size</label>
          <div class="row g-2">
            <div class="col-8">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="ti tabler-ruler text-muted"></i></span>
                <input type="number" class="form-control" id="size" placeholder="e.g. 5" min="0"
                       step="0.01">
              </div>
            </div>
            <div class="col-4">
              <select class="form-select h-100" id="unit">
                <option value="marla">Marla</option>
                <option value="square_yard">Sq. Yard</option>
              </select>
            </div>
          </div>
        </div>

        {{-- LIVE AVAILABILITY STATUS --}}
        <div id="status" class="d-none block-print-status">
          <div class="spinner-border spinner-border-sm me-2 d-none" id="spinner"></div>
          <span id="status_text"></span>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary flex-grow-1" data-bs-dismiss="modal">
          Cancel
        </button>
        <a href="#" class="btn btn-primary d-none flex-grow-1" id="submit"
           onclick="event.preventDefault(); if (this.getAttribute('href') !== '#') printDocument(this.href);">
          <i class="ti tabler-printer me-1"></i> Print Plan
        </a>
      </div>

    </div>
  </div>
</div>

@push('styles')
  <style>
    #printBlockPaymentPlanModal .form-select,
    #printBlockPaymentPlanModal .form-control,
    #printBlockPaymentPlanModal .input-group-text {
      border-radius: .6rem;
      border-color: #dde1e8;
    }

    #printBlockPaymentPlanModal .input-group-text {
      border-bottom-left-radius: .6rem;
      border-top-left-radius: .6rem;
    }

    #printBlockPaymentPlanModal .form-select:focus,
    #printBlockPaymentPlanModal .form-control:focus {
      border-color: #a3a8ff;
      box-shadow: 0 0 0 .2rem rgba(105, 108, 255, .12);
    }

    .block-field-label {
      display: inline-block;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .05em;
      text-transform: uppercase;
      color: #697a8d;
      margin-bottom: .4rem;
    }

    /* Segmented control */
    .block-segmented {
      display: grid;
      grid-auto-flow: column;
      gap: .4rem;
      background: #f0f2f6;
      padding: .35rem;
      border-radius: .85rem;
    }

    .seg-radio {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .seg-opt {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .45rem;
      padding: .65rem .5rem;
      border-radius: .65rem;
      font-weight: 600;
      font-size: .85rem;
      color: #697a8d;
      cursor: pointer;
      user-select: none;
      transition: all .18s ease;
      border: 1px solid transparent;
    }

    .seg-opt:hover {
      color: #34404e;
    }

    #printBlockPaymentPlanModal #commercial:checked + .seg-commercial,
    #printBlockPaymentPlanModal #non_commercial:checked + .seg-residential,
    #printBlockPaymentPlanModal #constructed:checked + .seg-constructed,
    #printBlockPaymentPlanModal #non_constructed:checked + .seg-non-constructed {
      background: #fff;
      color: #384551;
      border-color: #e4e7ec;
      box-shadow: 0 4px 14px -6px rgba(56, 78, 120, .35), inset 0 1px 0 rgba(255, 255, 255, .9);
    }

    #printBlockPaymentPlanModal #commercial:checked + .seg-commercial i {
      color: #2ab578;
    }

    #printBlockPaymentPlanModal #non_commercial:checked + .seg-residential i {
      color: #696cff;
    }

    #printBlockPaymentPlanModal #constructed:checked + .seg-constructed i {
      color: #8592a3;
    }

    #printBlockPaymentPlanModal #non_constructed:checked + .seg-non-constructed i {
      color: #697a8d;
    }

    /* Feature chips */
    .feature-chip-input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .feature-chip {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      border: 1px solid #dde1e8;
      border-radius: 2rem;
      background: #fff;
      padding: .45rem .95rem .45rem 2.1rem;
      font-size: .82rem;
      font-weight: 600;
      color: #384551;
      cursor: pointer;
      user-select: none;
      transition: all .18s ease;
    }

    .feature-chip i {
      color: #8592a3;
      transition: color .18s ease;
    }

    .feature-chip > i:first-child {
      position: absolute;
      left: .8rem;
    }

    .feature-chip-input:checked + .feature-chip {
      border-color: #696cff;
      background: linear-gradient(135deg, #f3f4ff, #eef1ff);
      box-shadow: 0 6px 16px -8px rgba(105, 108, 255, .5);
    }

    .feature-chip-input:checked + .feature-chip i {
      color: #696cff;
    }

    #printBlockPaymentPlanModal .block-print-status.alert {
      border-radius: .7rem;
      display: flex;
      align-items: center;
    }
  </style>
@endpush

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var modalRoot = document.getElementById('printBlockPaymentPlanModal');
      if (!modalRoot) return;

      var sizeInput = modalRoot.querySelector('#size');
      var unitSelect = modalRoot.querySelector('#unit');
      var statusBox = modalRoot.querySelector('#status');
      var statusText = modalRoot.querySelector('#status_text');
      var spinner = modalRoot.querySelector('#spinner');
      var submitLink = modalRoot.querySelector('#submit');
      var cornerChip = modalRoot.querySelector('#corner');
      var parkFacingChip = modalRoot.querySelector('#park_facing');
      var boulevardChip = modalRoot.querySelector('#main_boulevard');

      function openPrintPlan(uuid) {
        if (!uuid) return;
        modalRoot.dataset.blockUuid = uuid;
        spinner.classList.add('d-none');
        statusBox.classList.add('d-none');
        statusBox.classList.remove('alert', 'alert-success', 'alert-danger', 'text-muted');
        submitLink.classList.add('d-none');
        sizeInput.value = '';
        unitSelect.value = 'marla';
        cornerChip.checked = false;
        parkFacingChip.checked = false;
        boulevardChip.checked = false;
        new bootstrap.Modal(modalRoot).show();
      }

      window.openBlockPrintPlan = openPrintPlan;

      document.addEventListener('click', function(e) {
        var printBtn = e.target.closest('.print_plan_btn');
        if (printBtn) openPrintPlan(printBtn.dataset.uuid);
      });

      function selectedFeatures() {
        var features = [];
        if (cornerChip.checked) features.push('corner_premium');
        if (parkFacingChip.checked) features.push('park_facing_premium');
        if (boulevardChip.checked) features.push('main_boulevard_premium');
        return features;
      }

      function checkAvailability() {
        var uuid = modalRoot.dataset.blockUuid;
        var size = sizeInput.value;
        var unit = unitSelect.value;
        var typeEl = modalRoot.querySelector('input[name="plan_type"]:checked');
        var type = typeEl ? typeEl.value : 'non_commercial';
        var constructedEl = modalRoot.querySelector('input[name="plan_constructed"]:checked');
        var constructed = constructedEl ? constructedEl.value : '0';
        var features = selectedFeatures();

        submitLink.classList.add('d-none');
        statusBox.classList.remove('alert-success', 'alert-danger', 'text-muted');

        if (!uuid || !size || parseFloat(size) <= 0) {
          statusBox.classList.remove('d-none');
          statusText.textContent = 'Enter a property size to check availability.';
          return;
        }

        statusBox.classList.remove('d-none');
        statusBox.classList.add('text-muted');
        spinner.classList.remove('d-none');
        statusText.textContent = 'Checking availability...';

        var query = 'type=' + encodeURIComponent(type) +
          '&constructed=' + encodeURIComponent(constructed) +
          '&size=' + encodeURIComponent(size) +
          '&unit=' + encodeURIComponent(unit);

        fetch('/blocks/' + uuid + '/print-plan/available?' + query)
          .then(function(res) {
            return res.json();
          })
          .then(function(res) {
            spinner.classList.add('d-none');
            if (res.available) {
              statusBox.classList.remove('alert-danger', 'text-muted');
              statusBox.classList.add('alert', 'alert-success');
              statusText.innerHTML = '<i class="ti tabler-circle-check me-1"></i>' + res.message;
              var printUrl = '/blocks/' + uuid + '/print-plan?' + query;
              if (features.length > 0) {
                printUrl += '&features=' + features.join(',');
              }
              submitLink.classList.remove('d-none');
              submitLink.href = printUrl;
            } else {
              statusBox.classList.remove('text-muted');
              statusBox.classList.add('alert', 'alert-danger');
              statusText.innerHTML = '<i class="ti tabler-alert-triangle me-1"></i>' + res.message;
            }
          })
          .catch(function() {
            spinner.classList.add('d-none');
            statusBox.classList.remove('text-muted');
            statusBox.classList.add('alert', 'alert-danger');
            statusText.textContent = 'Could not verify availability. Please try again.';
          });
      }

      sizeInput.addEventListener('input', checkAvailability);
      [unitSelect, cornerChip, parkFacingChip, boulevardChip].forEach(function(el) {
        el.addEventListener('change', checkAvailability);
      });
      modalRoot.querySelectorAll('input[name="plan_type"]').forEach(function(el) {
        el.addEventListener('change', checkAvailability);
      });
      modalRoot.querySelectorAll('input[name="plan_constructed"]').forEach(function(el) {
        el.addEventListener('change', checkAvailability);
      });
    });
  </script>
@endpush
