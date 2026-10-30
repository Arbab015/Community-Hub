<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('property_payment_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->morphs('planable');

            // Listing is optional now — a plan can be applied even if not listed yet.
            $table->foreignId('listing_id')
                ->nullable()
                ->constrained('property_listings')
                ->nullOnDelete();

            // Single payment template reference
            $table->foreignId('payment_template_id')
                ->nullable()
                ->constrained('payment_templates')
                ->nullOnDelete();

            $table->double('booking_fee')->nullable();
            $table->boolean('booking_fee_in_per')->nullable()->default(false);

            $table->double('development_fee')->nullable();
            $table->boolean('development_fee_in_per')->nullable()->default(false);
            $table->boolean('development_fee_surplus')->nullable()->default(false);

            $table->double('down_payment')->nullable();
            $table->boolean('down_payment_in_per')->nullable()->default(false);

            $table->double('confirmation_fee')->nullable();
            $table->boolean('confirmation_fee_in_per')->nullable()->default(false);

            $table->double('possession_fee')->nullable();
            $table->boolean('possession_fee_in_per')->nullable()->default(false);
            $table->boolean('possession_fee_surplus')->nullable()->default(false);

            // Installment-plan-specific fields
            $table->double('balloon_payment')->nullable();
            $table->boolean('balloon_payment_in_per')->nullable()->default(false);
            $table->enum('balloon_payment_frequency', ['quarterly', 'biannually', 'annually'])->nullable();

            $table->unsignedInteger('number_of_installments')->nullable();
            $table->double('additional_installment_amount')->nullable();
            $table->boolean('additional_installment_amount_in_per')->default(false)->nullable();
            $table->enum('installment_frequency', ['monthly', 'quarterly', 'biannually', 'annually'])->nullable();

            $table->timestamps();
        });

        // Pivot table for multiple installment templates
        Schema::create('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_payment_plan_id')
                ->constrained('property_payment_plans')
                ->cascadeOnDelete()
                ->name('fk_ppp_id');
            $table->foreignId('installment_template_id')
                ->constrained('installment_templates')
                ->nullOnDelete()
                ->name('fk_inst_template_id');
            $table->timestamps();

            $table->unique(['property_payment_plan_id', 'installment_template_id'], 'uq_ppp_inst_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_payment_plan_installment_templates');
        Schema::dropIfExists('property_payment_plans');
    }
};
