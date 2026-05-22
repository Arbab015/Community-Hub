<div class="modal fade" id="userImportModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-md">
    <form class="modal-content" method="POST" action="{{ route('users.import') }}" enctype="multipart/form-data">
      @csrf

      <div class="modal-header">
        <h5 class="modal-title">Import Users</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="alert alert-info d-flex justify-content-between align-items-center">
          <span>Download CSV template for correct format</span>
          <a href="{{ route('users_import_template.download') }}" class="btn btn-sm btn-outline-primary">
            <i class="ti tabler-download me-1"></i> Download Template
          </a>
        </div>

        <div class="mb-2">
          @php
            $creators_roles = $roles->where('user_id', Auth()->id());
             $society_member = $roles->where('name', 'Society Member')->first();
              $society_owner = $roles->where('name', 'Society Owner')->first();
          @endphp
          <label class="form-label">Assign Role to All Users</label>

          @if($slug == "system_users" || $slug == "society_managers")
            <select name="role_id" class="form-select" required>
              <option value="">Select Role</option>
              @foreach ($creators_roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
              @endforeach
            </select>
          @elseif($slug == "society_members")
            <input type="hidden" name="role_id" value="{{ $society_member?->id }}">
            <input type="text"
                   class="form-control"
                   value="{{ $society_member?->name }}"
                   readonly>
          @elseif($slug == "society_owners")
            <input type="hidden" name="role_id" value="{{ $society_owner?->id }}">
            <input type="text"
                   class="form-control"
                   value="{{ $society_owner?->name }}"
                   readonly>
          @endif
          <small class="text-muted">
            This role will be assigned to every imported user
          </small>
        </div>

        <div class="mb-3 ">
          <label class="form-label">Upload CSV File</label>
          <input type="file" name="file" class="form-control" accept=".csv" required />
          <small class="text-muted">Only CSV files allowed</small>
        </div>

      </div>

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
