<!-- Add/Edit Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-role">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        <div class="text-center mb-6">
          <h4 class="role-title fs-4" id="modalTitle">Add New Role</h4>
          <p class="text-body-secondary">Set role permissions</p>
        </div>
        <form id="addRoleForm" method="POST" action="{{ route('roles.store') }}">
          @csrf
          <input type="hidden" id="role_id" name="role_id" value="">
          <input type="hidden" id="form_method" name="_method" value="">
          <div class="mb-3">
            <label for="role_name" class="form-label fw-bold fs-6">Role Name</label>
            <input type="text" id="role_name" name="role_name" class="form-control" placeholder="Enter role name"
              required>
          </div>
          <div class="mb-3">
            @foreach ($permissionMatrix as $module => $permissions)
              <div class="mb-2">
                <div class="mb-1">
                  <strong>{{ $module }}:</strong>
                </div>
                <span class="d-inline-flex flex-wrap gap-2 ms-2">
                  @foreach ($permissions as $permission)
                    <div class="form-check form-check-inline">
                      <input class="form-check-input permission-checkbox" type="checkbox" name="permissions[]"
                        value="{{ $permission }}" id="perm_{{ $permission }}">
                      <label class="form-check-label" for="perm_{{ $permission }}">
                        {{ ucwords(str_replace('_', ' ', $permission)) }}
                      </label>
                    </div>
                  @endforeach
                </span>
              </div>
            @endforeach
          </div>
          <button type="submit" class="btn btn-primary" id="submitBtn">Submit</button>
        </form>
      </div>
    </div>
  </div>
</div>
