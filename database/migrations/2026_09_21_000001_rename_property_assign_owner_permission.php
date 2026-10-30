<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        $oldExists = DB::table($table)->where('name', 'property_assign_owner')->exists();
        $newExists = DB::table($table)->where('name', 'property_reserve_owner')->exists();

        if ($oldExists && ! $newExists) {
            DB::table($table)
                ->where('name', 'property_assign_owner')
                ->update(['name' => 'property_reserve_owner']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        $oldExists = DB::table($table)->where('name', 'property_reserve_owner')->exists();
        $newExists = DB::table($table)->where('name', 'property_assign_owner')->exists();

        if ($oldExists && ! $newExists) {
            DB::table($table)
                ->where('name', 'property_reserve_owner')
                ->update(['name' => 'property_assign_owner']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
