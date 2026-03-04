<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class PermissionMatrixService
{
    public static function build()
    {
        $permissions = auth()->user()->getAllPermissions()->reject(function ($permission) {
            return $permission->name === 'dashboard';
        });
        $permissionMatrix = [];

        foreach ($permissions as $permission) {
            if ($permission === 'dashboard') {
                continue;
            }
            $name = $permission->name;

            if (str_contains($name, '_user')) {
                $module = 'User Management';
            } elseif (str_contains($name, '_role')) {
                $module = 'Role Management';
            } elseif (str_contains($name, '_society')) {
                $module = 'Society Management';
            } else {
                $module = 'Other Permissions';
            }

            $permissionMatrix[$module][] = $name;
        }

        return $permissionMatrix;
    }
}