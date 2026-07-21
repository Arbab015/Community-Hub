{{-- Import Errors File Modal --}}
<div class="modal fade" id="importErrorsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title fw-semibold mb-0 d-flex align-items-center gap-2">
          <span class="d-flex align-items-center justify-content-center bg-warning bg-opacity-10 rounded-3 p-2">
            <i class="ti tabler-alert-triangle text-warning fs-5"></i>
          </span>
          Import error reports
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-0">
        <p class="text-muted small px-4 pt-3 mb-2">Download or remove error files from previous imports</p>

        <div id="error_files_loading" class="text-center py-5">
          <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
          <span class="text-muted small">Loading error files...</span>
        </div>

        <div id="error_files_empty" class="d-none text-center py-5">
          <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle p-3 mb-3">
            <i class="ti tabler-file-off text-muted fs-4"></i>
          </div>
          <p class="fw-semibold mb-1">No error files found</p>
          <p class="text-muted small mb-0">All imports completed without errors.</p>
        </div>

        <div id="error_files_table_wrapper" class="d-none">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light border-bottom">
            <tr>
              <th class="ps-4 py-3 text-muted fw-semibold column-title"
                  style="width:150px;">Date &amp; time
              </th>
              <th class="py-3 text-muted fw-semibold column-title">Import file
              </th>
              <th class="py-3 text-muted fw-semibold column-title">Error file
              </th>
              <th class="pe-4 py-3 text-end text-muted fw-semibold column-title">Actions
              </th>
            </tr>
            </thead>
            <tbody id="error_files_tbody">
            {{-- rows injected by JS --}}
            </tbody>
          </table>
        </div>

      </div>

      {{-- Footer --}}
      <div class="modal-footer bg-light d-flex justify-content-between align-items-center pt-3">
        <span class="text-muted small d-flex align-items-center gap-2">
          <i class="ti tabler-info-circle"></i>
          Error files are stored per block
        </span>
        <button type="button" class="btn btn-sm btn-secondary px-4" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>
