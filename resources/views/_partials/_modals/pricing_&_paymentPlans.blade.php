<div class="modal fade" id="blockDetailsModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Pricing & Payment Plans — <span id="block_details_name"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <ul class="nav nav-tabs mb-3" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_price_ranges" type="button"
                    role="tab">
              <i class="ti tabler-tag me-1"></i>Price Ranges
              <span class="badge bg-label-secondary ms-1" id="price_range_count">0</span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_payment_plans" type="button" role="tab">
              <i class="ti tabler-file-invoice me-1"></i>Payment Plans
              <span class="badge bg-label-secondary ms-1" id="payment_plan_count">0</span>
            </button>
          </li>
        </ul>

        <div class="tab-content">
          {{-- PRICE RANGES TAB --}}
          <div class="tab-pane fade show active" id="tab_price_ranges" role="tabpanel">
            <div id="price_ranges_loading" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
              <span class="ms-2 text-muted">Loading...</span>
            </div>
            <div id="price_ranges_empty" class="text-center py-4 d-none">
              <i class="ti tabler-info-circle text-muted fs-1"></i>
              <p class="text-muted mt-2 mb-0">No price ranges set for this block.</p>
            </div>
            <div id="price_ranges_table_wrapper" class="d-none">
              <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                  <thead class="bg-label-light">
                  <tr>
                    <th class="text-uppercase small">Min Size</th>
                    <th class="text-uppercase small">Max Size</th>
                    <th class="text-uppercase small">Price</th>
                    <th class="text-uppercase small">Unit</th>
                  </tr>
                  </thead>
                  <tbody id="price_ranges_tbody"></tbody>
                </table>
              </div>
            </div>
          </div>

          {{-- PAYMENT PLANS TAB --}}
          <div class="tab-pane fade" id="tab_payment_plans" role="tabpanel">
            <div id="payment_plans_loading" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
              <span class="ms-2 text-muted">Loading...</span>
            </div>
            <div id="payment_plans_empty" class="text-center py-4 d-none">
              <i class="ti tabler-info-circle text-muted fs-1"></i>
              <p class="text-muted mt-2 mb-0">No payment plans set for this block.</p>
            </div>
            <div id="payment_plans_table_wrapper" class="d-none">
              <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                  <thead class="bg-label-light">
                  <tr>
                    <th class="text-uppercase small">Size Range</th>
                    <th class="text-uppercase small">Type</th>
                    <th class="text-uppercase small">Payment Template</th>
                    <th class="text-uppercase small">Installment Templates</th>
                  </tr>
                  </thead>
                  <tbody id="payment_plans_tbody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>
