<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable snapshot of the payment + installment plan committed to a
     * member at the moment ownership is assigned. One row per
     * property_owners record. Fully decoupled from payment/installment
     * templates and from the seller's for-sale plan, so deleting a template
     * or pruning the source plan can never affect an owner's committed plan.
     */
    public function up(): void
    {
        Schema::create('property_ownership_payment_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // The property (or property unit) this committed plan belongs to.
            $table->morphs('property_ownerable', 'po_ownable_idx');

            $table->foreignId('property_owner_id')
                ->unique()
                ->constrained('property_owners')
                ->restrictOnDelete();

            // ---- Payment plan snapshot (property_payment_plans / payment template) ----
            $table->decimal('listed_price', 15, 2)->nullable();

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

            $table->double('corner_premium')->nullable();
            $table->boolean('corner_premium_in_per')->nullable()->default(true);

            $table->double('park_facing_premium')->nullable();
            $table->boolean('park_facing_premium_in_per')->nullable()->default(true);

            $table->double('main_boulevard_premium')->nullable();
            $table->boolean('main_boulevard_premium_in_per')->nullable()->default(true);

            // ---- Installment plan snapshot (property_installment_plans pivot) ----
            $table->boolean('with_installment')->default(false);
            $table->string('name')->nullable();
            $table->unsignedInteger('number_of_installments')->nullable();
            $table->enum('installment_frequency', ['monthly', 'quarterly', 'biannually', 'annually'])->nullable();
            $table->double('additional_installment_amount')->nullable();
            $table->boolean('additional_installment_amount_in_per')->nullable()->default(false);
            $table->double('balloon_payment')->nullable();
            $table->boolean('balloon_payment_in_per')->nullable()->default(false);
            $table->enum('balloon_payment_frequency', ['quarterly', 'biannually', 'annually'])->nullable();

            $table->decimal('final_price', 15, 2)->nullable();
            $table->string('price_unit')->nullable();
            $table->enum('status', ['active', 'in-progress', 'completed']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_ownership_payment_plans');
    }
};
