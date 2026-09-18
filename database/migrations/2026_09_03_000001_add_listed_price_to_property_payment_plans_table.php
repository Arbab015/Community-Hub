<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot the property/unit's listed price onto the payment plan so
     * calculations keep working even after the listing is removed (e.g. when
     * an owner is assigned and the listing is deleted).
     */
    public function up(): void
    {
        Schema::table('property_payment_plans', function (Blueprint $table) {
            $table->decimal('listed_price', 15, 2)->nullable()->after('payment_template_id');
            $table->string('listed_unit', 20)->nullable()->after('listed_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_payment_plans', function (Blueprint $table) {
            $table->dropColumn(['listed_unit', 'listed_price']);
        });
    }
};
