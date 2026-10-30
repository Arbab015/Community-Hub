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
            'pricing_payment_plans_info', // show the pricing details and payment plans details block level

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

            'payment_template_listing',
            'payment_template_create',
            'payment_template_edit',
            'payment_template_delete',

            'installment_template_listing',
            'installment_template_create',
            'installment_template_edit',
            'installment_template_delete',

            // payment planning
            'payment_plan_apply',
            'payment_plan_edit', // edit only payment plan not installment plan
            'payment_plan_delete', // delete whole payment plan with installment plans
            'payment_plan_print', // for print payment plan
            'installment_plan_add', // add installment plan in the payment plan
            'installment_plan_edit',  // edit installment plan in the payment plan
            'installment_plan_delete', // remove or delete the installment plan
            'property_reserve_owner',

            // payment collection / tracking
            'payment_collect', // record payments against a committed plan
            'my_payments_listing',
            'payment_history_switch',
            'reschedule_payments',
            'payment_history',

            'payment_print_by_period',
            'all_transactions_listing',
            'refund_payment',
            'cancel_reserve-ownership',

            'listing_fbr_tax_rate',

            'transfer_fee_listing',
            'transfer_fee_create',
            'transfer_fee_edit',
            'transfer_fee_delete',

            'payment_late_fee_rule_listing',
            'payment_late_fee_rule_create',
            'payment_late_fee_rule_edit',
            'payment_late_fee_rule_delete',

            'property_transfer_listing',
            'property_transfer_create',
            'property_transfer_approve',
            'property_transfer_print',

            'system_settings',

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
            'pricing_payment_plans_info',

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

            'payment_template_listing',
            'payment_template_create',
            'payment_template_edit',
            'payment_template_delete',

            'installment_template_listing',
            'installment_template_create',
            'installment_template_edit',
            'installment_template_delete',

            'payment_plan_apply',
            'payment_plan_edit',
            'payment_plan_delete',
            'payment_plan_print',
            'installment_plan_add',
            'installment_plan_edit',
            'installment_plan_delete',
            'property_reserve_owner',

            'my_payments_listing',
            'refund_payment',
            'cancel_reserve-ownership',
            'reschedule_payments',

            'transfer_fee_listing',
            'transfer_fee_create',
            'transfer_fee_edit',
            'transfer_fee_delete',

            'payment_late_fee_rule_listing',
            'payment_late_fee_rule_create',
            'payment_late_fee_rule_edit',
            'payment_late_fee_rule_delete',

            'property_transfer_listing',
            'property_transfer_create',

            'property_transfer_approve',
        ])->get();

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $role->syncPermissions($superAdminPermissions);

        User::role('Super Admin')->get()->each(function ($admin) use ($superAdminPermissions) {
            $admin->syncPermissions($superAdminPermissions);
        });

        $user = User::firstOrCreate(
            ['email' => 'arbabr904@gmail.com'],
            [
                'first_name' => 'Arbab',
                'last_name' => 'khalid',
                'cnic_passport' => '13403-377377893-1',
                'permanent_address' => 'Dara saleem khan',
                'password' => bcrypt('12345678'),
            ]
        );
        if (! $user->hasRole('Super Admin')) {
            $user->assignRole('Super Admin');
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
            'pricing_payment_plans_info',

            'create_property',
            'edit_property',
            'delete_property',
            'view_property',

            'listing_property',
            'unlist_property',

            'inquiries_listing',
            'see_inquiries',

            'payment_template_listing',
            'payment_template_create',
            'payment_template_edit',
            'payment_template_delete',

            'installment_template_listing',
            'installment_template_create',
            'installment_template_edit',
            'installment_template_delete',

            'payment_plan_apply',
            'payment_plan_edit',
            'payment_plan_delete',
            'payment_plan_print',
            'installment_plan_add',
            'installment_plan_edit',
            'installment_plan_delete',

            'property_reserve_owner',

            'payment_collect',
            'reschedule_payments',
            'payment_history_switch',
            'payment_history',
            'payment_print_by_period',
            'all_transactions_listing',
            'refund_payment',
            'cancel_reserve-ownership',

            'transfer_fee_listing',
            'transfer_fee_create',
            'transfer_fee_edit',
            'transfer_fee_delete',

            'payment_late_fee_rule_listing',
            'payment_late_fee_rule_create',
            'payment_late_fee_rule_edit',
            'payment_late_fee_rule_delete',

            'property_transfer_listing',
            'property_transfer_approve',
            'property_transfer_print',

            'system_settings',
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

            'my_payments_listing',
            'payment_history',

            'property_transfer_create',
            'property_transfer_print',

        ])->get();

        $role = Role::firstOrCreate(['name' => 'Society Member']);
        $role->syncPermissions($societyMemberPermissions);

        User::role('Society Member')->get()->each(function ($member) use ($societyMemberPermissions) {
            $member->syncPermissions($societyMemberPermissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
