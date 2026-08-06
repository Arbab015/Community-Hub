<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-attachment installment snapshot fields to the pivot table.
     * Each attached installment template gets its own values, so the source
     * template is never mutated when a plan is edited.
     */
    public function up(): void
    {
        Schema::table('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->unsignedInteger('number_of_installments')->nullable();
            $table->enum('installment_frequency', ['monthly', 'quarterly', 'biannually', 'annually'])->nullable();
            $table->double('additional_installment_amount')->nullable();
            $table->boolean('additional_installment_amount_in_per')->nullable()->default(false);
            $table->double('balloon_payment')->nullable();
            $table->boolean('balloon_payment_in_per')->nullable()->default(false);
            $table->enum('balloon_payment_frequency', ['quarterly', 'biannually', 'annually'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'number_of_installments',
                'installment_frequency',
                'additional_installment_amount',
                'additional_installment_amount_in_per',
                'balloon_payment',
                'balloon_payment_in_per',
                'balloon_payment_frequency',
            ]);
        });
    }
};
