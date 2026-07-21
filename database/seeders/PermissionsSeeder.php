<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
            'society_blocks',
            'listing_block',
            'add_block',
            'edit_block',
            'delete_block',
            'view_block',

            'listing_attribute',
            'create_attribute',
            'edit_attribute',
            'delete_attribute',

            'create_property',
            'edit_property',
            'delete_property',
            'view_property',

            'listing_property',
            'unlist_property',

            'inquiries_listing',
            'see_inquiries',

            'owner_properties', // show properties to the owner

            'contact_messages',

            'payment_planning_template_listing',
            'payment_planning_template_create',
            'payment_planning_template_edit',
            'payment_planning_template_delete',

        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdminPermissions = Permission::whereNotIn('name', [
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

            'society_blocks',
            'add_block',
            'edit_block',
            'delete_block',
            'create_property',
            'edit_property',
            'delete_property',

            'listing_property',
            'unlist_property',

            'inquiries_listing',
            'see_inquiries',

            'owner_properties',

            'payment_planning_template_listing',
            'payment_planning_template_create',
            'payment_planning_template_edit',
            'payment_planning_template_delete',
        ])->get();

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $role->syncPermissions($superAdminPermissions);

        User::role('Super Admin')->get()->each(function ($admin) use ($superAdminPermissions) {
            $admin->syncPermissions($superAdminPermissions);
        });

        $user = User::where('email', 'arbabr904@gmail.com')->first();
        if ($user) {
            $user->assignRole(roles: 'Super Admin');
        }

        // Society Owner
        $societyAdminPermissions = [
            'dashboard',
            'listing_society',
            'add_user',
            'edit_user',
            'delete_user',
            'import_user',
            'export_user',

            'listing_role',
            'add_role',
            'edit_role',
            'delete_role',

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
            'society_blocks',
            'listing_block',
            'add_block',
            'edit_block',
            'delete_block',
            'view_block',

            'create_property',
            'edit_property',
            'delete_property',
            'view_property',

            'listing_property',
            'unlist_property',

            'inquiries_listing',
            'see_inquiries',

            'payment_planning_template_listing',
            'payment_planning_template_create',
            'payment_planning_template_edit',
            'payment_planning_template_delete',
        ];

        $societyOwnerRole = Role::firstOrCreate(['name' => 'Society Owner']);
        $societyOwnerRole->syncPermissions($societyAdminPermissions);

        User::role('Society Owner')->get()->each(function ($owner) use ($societyAdminPermissions) {
            $owner->syncPermissions($societyAdminPermissions);
        });

        // Society Member
        $societyMemberPermissions = Permission::whereIn('name', [
            'dashboard',
            'discussions',
            'suggestions',
            'issues',
            'complaints',
            'add_reports',
            'un-block_request_post',

            'owner_properties',
            'edit_property',
            'view_property',
            'listing_property',
            'unlist_property',

            'see_inquiries',
        ])->get();

        $role = Role::firstOrCreate(['name' => 'Society Member']);
        $role->syncPermissions($societyMemberPermissions);

        User::role('Society Member')->get()->each(function ($member) use ($societyMemberPermissions) {
            $member->syncPermissions($societyMemberPermissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
