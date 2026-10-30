<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FBR tax support for reserved payment plans.
     *
     * The rate is SNAPSHOT onto the plan when it is committed so that editing
     * an fbr_tax_rates slab later can never silently reprice payments that were
     * already recorded, and every receipt can state the rate it actually used.
     *
     * The ledger is deliberately untouched: tax is collected per transaction on
     * top of the charges, so SUM(amount_due) === final_price still holds.
     */
    public function up(): void
    {
        Schema::table('property_reserved_payment_plans', function (Blueprint $table) {
            $table->decimal('fbr_tax_rate_percent', 5, 2)->nullable()->after('price_unit');
            $table->decimal('fbr_tax_amount', 15, 2)->nullable()->after('fbr_tax_rate_percent');
            $table->boolean('is_filer_at_commit')->nullable()->after('fbr_tax_amount');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            // Portion of the payment that cascaded onto ledger charges.
            $table->decimal('charge_subtotal', 15, 2)->nullable()->after('amount_paid');
            // FBR tax collected on that portion, capped by the plan-wide target.
            $table->decimal('tax_amount', 15, 2)->default(0)->after('charge_subtotal');
            // Rate actually applied, snap shotted for the receipt / audit.
            $table->decimal('tax_rate_percent', 5, 2)->default(0)->after('tax_amount');
            $table->boolean('tax_capped')->default(false)->after('tax_rate_percent');
        });

        Schema::table('fbr_tax_rates', function (Blueprint $table) {
            $table->index(['role', 'value_from', 'value_to'], 'fbr_slab_lookup_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('is_filer', 'users_is_filer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_is_filer_idx');
        });

        Schema::table('fbr_tax_rates', function (Blueprint $table) {
            $table->dropIndex('fbr_slab_lookup_idx');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['charge_subtotal', 'tax_amount', 'tax_rate_percent', 'tax_capped']);
        });

        Schema::table('property_reserved_payment_plans', function (Blueprint $table) {
            $table->dropColumn(['fbr_tax_rate_percent', 'fbr_tax_amount', 'is_filer_at_commit']);
        });
    }
};
