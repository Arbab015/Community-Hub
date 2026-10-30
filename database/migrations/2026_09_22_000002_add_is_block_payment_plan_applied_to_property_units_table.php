<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track whether the unit's current payment plan/listing still comes
     * from the block-level payment configuration.
     */
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->boolean('is_block_payment_plan_applied')->default(false)->after('amenities');
        });
    }

    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn('is_block_payment_plan_applied');
        });
    }
};
