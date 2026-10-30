<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename(
            'property_ownership_payment_plans',
            'property_reserved_payment_plans'
        );
    }

    public function down(): void
    {
        Schema::rename(
            'property_reserved_payment_plans',
            'property_ownership_payment_plans'
        );
    }
};
