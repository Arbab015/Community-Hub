<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        $permissions = [
            'dashboard',
            'listing_system_user',
            'listing_society_owner',
            'add_user',
            'edit_user',
            'delete_user',
            'import_user',
            'export_user',
            'listing_role',
            'add_role',
            'edit_role',
            'delete_role',
            'listing_society',
            'add_society',
            'edit_society',
            'delete_society',
            'all_societies',
            'owner_societies',
            'listing_society_manager',
            'listing_society_member',
            'block_society',
            'discussions',
            'suggestions',
            'issues',
            'complaints',
            'listing_tag',
            'block_post',
          'listing_block_post',
          'listing_requested_post',
            'un-block_request_post',
          'cancel_unblock_request_post',
            'un-block_post',
            'add_tag',
            'edit_tag',
            'delete_tag',
            'report_actions',
            'listing_reports',
            'add_reports',
            'view_post_reports',
            'delete_reports',
            'can_pin',
            'listing_rule',
            'add_rule',
            'edit_rule',
            'delete_rule',

            'listing_block',
            'add_block',
            'edit_block',
            'delete_block',
            'view_block',

            'create_property',
            'edit_property',
            'delete_property',
            'view_property',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $role->syncPermissions(
            Permission::whereNotIn('name', [
                'add_society',
                'edit_society',
                'delete_society',
                'owner_societies',
                'listing_society_manager',
                'listing_society_member',
                'discussions',
                'suggestions',
                'issues',
                'complaints',
                'add_reports',
                'listing_rule',
                'add_rule',
                'edit_rule',
                'delete_rule',
                'listing_block',
                'add_block',
                'edit_block',
                'delete_block',
                'view_block',
               'create_property',
              'edit_property',
              'delete_property',
              'view_property',

            ])->get()
        );
        $user = User::where('email', 'arbabr904@gmail.com')->first();
        if ($user) {
            $user->assignRole(roles: 'Super Admin');
        }

        //permissions for society admin
        $societyAdminPermissions = [
            'dashboard',
            'listing_society',
            'add_society',
            'edit_society',
            'delete_society',
            'owner_societies',
            'listing_society_manager',
            'listing_society_member',
            'block_post',
            'listing_block_post',
            'listing_requested_post',
            'un-block_request_post',
            'cancel_unblock_request_post',
            'un-block_post',
            'listing_reports',
            'report_actions',
            'add_reports',
            'view_post_reports',
            'delete_reports',
            'can_pin',
            'listing_rule',
            'add_rule',
            'edit_rule',
            'delete_rule',

          'listing_block',
          'add_block',
          'edit_block',
          'delete_block',
          'view_block',

          'create_property',
          'edit_property',
          'delete_property',
          'view_property',
        ];

        // Get all users with Society Admin role
        $societyAdmins = User::role('Society Owner')->get();
        // Assign permissions directly to users
        foreach ($societyAdmins as $societyAdmin) {
            $societyAdmin->givePermissionTo($societyAdminPermissions);
        }

        $role = Role::firstOrCreate(['name' => 'Society Member']);
        $role->syncPermissions(
            Permission::whereIn('name', [
                'dashboard',
                'discussions',
                'suggestions',
                'issues',
                'complaints',
                'add_reports',
                'un-block_request_post',
            ])->get()
        );
    }
}
