/**
 * App user list
 */

'use strict';

// On edit role click, update text
var roleEditList = document.querySelectorAll('.role-edit-modal'),
  roleAdd = document.querySelector('.add-new-role'),
  roleTitle = document.querySelector('.role-title');

if (roleEditList) {
  roleEditList.forEach(function (roleEditEl) {
    roleEditEl.onclick = function () {
      roleTitle.innerHTML = 'Edit Role'; // reset text
    };
  });
}
if (roleAdd) {
  roleAdd.onclick = function () {
    roleTitle.innerHTML = 'Add New Role';
  };
}

document.addEventListener('DOMContentLoaded', function () {
  const modal = new bootstrap.Modal(document.getElementById('addRoleModal'));
  const form = document.getElementById('addRoleForm');
  const roleIdInput = document.getElementById('role_id');
  const methodInput = document.getElementById('form_method');
  const roleNameInput = document.getElementById('role_name');
  const modalTitle = document.getElementById('modalTitle');

  const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');

  document.getElementById('addRoleBtn')?.addEventListener('click', () => {
    modalTitle.innerText = 'Add New Role';
    form.action = `/role/add`; // roles.store
    roleIdInput.value = '';
    methodInput.value = '';

    roleNameInput.value = '';

    permissionCheckboxes.forEach(cb => (cb.checked = false));
  });

  document.querySelectorAll('.edit-role').forEach(btn => {
    btn.addEventListener('click', () => {
      const roleId = btn.dataset.id;
      const roleName = btn.dataset.name;
      const permissions = JSON.parse(btn.dataset.permissions);

      modalTitle.innerText = 'Edit Role';
      form.action = `/role/update/${roleId}`; // roles.update
      roleIdInput.value = roleId;
      methodInput.value = 'PUT';

      roleNameInput.value = roleName;

      permissionCheckboxes.forEach(cb => {
        cb.checked = permissions.includes(cb.value);
      });

      modal.show();
    });
  });
});
