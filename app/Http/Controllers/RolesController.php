<?php

namespace App\Http\Controllers;

use App\Models\Role as ModelsRole;
use App\Services\PermissionMatrixService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

class RolesController extends Controller
{
    public function index(Request $request, $roleId = null)
    {
        $excluded_roles = ['Super Admin', 'Society Owner'];
        $roles = Role::whereNotIn('name', $excluded_roles)
            ->where('user_id', auth()->id())
            ->with(['permissions', 'users'])
            ->withCount('users')
            ->get();
        $permissions = Permission::orderBy('name')->where('name', "!=", "dashboard")->get();
        $permissionMatrix = PermissionMatrixService::build();
        $login_user = Auth::user();
        return view(
            'content.roles_and_permissions.index',
            compact('roles', 'permissionMatrix', 'login_user')
        );
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'role_name' => 'required|string|max:50'
            ]);


            $role = ModelsRole::create([
                'name' => $request->role_name,
                'user_id' => Auth::id()
            ]);

            // Assign default dashboard permission
            $dashboard_per = Permission::where('name', "dashboard")->get();
            $role->givePermissionTo($dashboard_per);

            // Assign additional permissions if provided
            if (!empty($request->permissions)) {
                $role->givePermissionTo($request->permissions);
            }

            return redirect()->route('roles.index')->with('success', 'Role created successfully.');
        } catch (\Illuminate\Database\QueryException $e) {

            if ($e->errorInfo[1] == 1062) {
                return redirect()->back()->withInput()->with('error', 'Role already exists for this user and guard.');
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'role_name' => 'required|string|max:50'
            ]);
            $role = Role::findOrFail($id);
            $role->update([
                'name' => $request->role_name,
            ]);
            if (!empty($request->permissions)) {
                $role->syncPermissions($request->permissions);
            }
            return redirect()->back()->with('success', 'Role Updated Successfully.');
        } catch (QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == '1062') {
                return redirect()->back()->withInput()->with('error', 'Duplicate name entry');
            }
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);
            $role->delete();
            return redirect()->route('roles.index')->with('success', 'Role deleted successfully. ');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
