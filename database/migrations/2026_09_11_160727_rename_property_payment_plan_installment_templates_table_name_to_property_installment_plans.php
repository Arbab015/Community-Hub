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
        Schema::rename('property_payment_plan_installment_templates', 'property_installment_plans');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('property_installment_plans', 'property_payment_plan_installment_templates');

    }
};
