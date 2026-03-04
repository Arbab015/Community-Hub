<!-- Import Users Modal -->
<div class="modal fade" id="backDropModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-md">
    <form class="modal-content" method="POST" action="{{ route('users.import') }}" enctype="multipart/form-data">
      @csrf

      <!-- Modal Header -->
      <div class="modal-header">
        <h5 class="modal-title">Import Users</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">

        <!-- Download Template -->
        <div class="alert alert-info d-flex justify-content-between align-items-center">
          <span>Download CSV template for correct format</span>
          <a href="{{ route('template.download') }}" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-download"></i> Download Template
          </a>
        </div>

        <!-- Role Selection -->
        <div class="mb-2">
          <label class="form-label">Assign Role to All Users</label>
          <select name="role_id" class="form-select" required>
            <option value="">Select Role</option>
            @foreach ($roles as $role)
              <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
          </select>
          <small class="text-muted">
            This role will be assigned to every imported user
          </small>
        </div>
        
        <!-- File Input -->
        <div class="mb-3 ">
          <label class="form-label">Upload CSV File</label>
          <input type="file" name="file" class="form-control" accept=".csv" required />
          <small class="text-muted">Only CSV files allowed</small>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary">
          Import Users
        </button>
      </div>

    </form>
  </div>
</div>
