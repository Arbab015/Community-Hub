<div class="modal fade" id="importPropertiesModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-md">
    <form class="modal-content" method="POST" action="{{ route('properties.import') }}" enctype="multipart/form-data">

      @csrf

      <div class="modal-header">
        <h5 class="modal-title">Import Properties</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info d-flex justify-content-between align-items-center mb-1">
          <span>Download CSV template for correct format. </span>
          <a href="{{ route('properties_import_template.download' , ['type' => "template"]) }}" download
             class="btn btn-sm btn-outline-primary">
            <i class="ti tabler-download me-1"></i> Download Template
          </a>
        </div>

        <div class="alert alert-warning d-flex justify-content-between align-items-center">
          <span>Download guidelines before to import properties. </span>
          <a href="{{ route('properties_import_template.download', ['type' => "guidelines"]) }}" download
             class="btn btn-sm btn-outline-secondary">
            <i class="ti tabler-download me-1"></i> Download Guidelines
          </a>
        </div>


        <input type="hidden" name="block_id" value="{{$block->id}}">
        <!-- File Input -->
        <div class="mb-3 ">
          <label class="form-label text-uppercase fw-semibold">Upload CSV File</label>
          <input type="file" name="file" id="file" class="form-control mb-1" accept=".csv" required />
          <small class="text-info">Only CSV files allowed</small>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary">
          Import Properties
        </button>
      </div>

    </form>
  </div>
</div>
