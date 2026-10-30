<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_ownership_payment_plans', function (Blueprint $table) {
            foreach (['transfer_fee', 'transfer_fee_in_per', 'transfer_fee_surplus'] as $column) {
                if (Schema::hasColumn('property_ownership_payment_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_ownership_payment_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('property_ownership_payment_plans', 'transfer_fee')) {
                $table->double('transfer_fee')->nullable();
            }
            if (! Schema::hasColumn('property_ownership_payment_plans', 'transfer_fee_in_per')) {
                $table->boolean('transfer_fee_in_per')->nullable()->default(false);
            }
            if (! Schema::hasColumn('property_ownership_payment_plans', 'transfer_fee_surplus')) {
                $table->boolean('transfer_fee_surplus')->nullable()->default(true);
            }
        });
    }
};
