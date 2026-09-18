<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment transactions - User pays amount and provides transaction details
     * Stores how much was paid, payment method, and reference information
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Ownership & Payment Plan
            $table->foreignId('ownership_payment_plan_id')
                ->references('id')->on('property_ownership_payment_plans')
                ->cascadeOnDelete();

            // Amount Paid
            $table->decimal('amount_paid', 15, 2);

            // Payment Method
            $table->enum('payment_method', [
                'cash',
                'cheque',
                'bank_transfer',
                'card',
                'mobile_wallet',
                'adjustment',
                'waiver',
                'other',
            ]);

            // Transaction Reference Info
            $table->string('reference_no')->nullable();

            // User who recorded this payment
            $table->foreignId('recorded_by_user_id')->nullable()
                ->references('id')->on('users')
                ->nullOnDelete();

            // When payment was made
            $table->timestamp('paid_at')->nullable();

            // Additional notes/description
            $table->text('description')->nullable();

            // Audit & Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('ownership_payment_plan_id', 'txn_plan_idx');
            $table->index('paid_at', 'txn_paid_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
