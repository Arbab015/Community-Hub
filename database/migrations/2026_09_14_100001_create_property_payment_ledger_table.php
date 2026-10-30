<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per billable charge of a committed ownership plan (down payment,
     * fees, premiums are embedded in the fee totals, installments, balloons).
     *
     * Invariant (enforced by \App\Support\PaymentLedgerService):
     *   SUM(amount_due) === snapshot.final_price
     *
     * Status is stored but always refreshed through determineStatus():
     *   - paid        : remaining_balance <= 0
     *   - pending     : not paid AND (due_date null OR due_date >= today)
     *   - current_due : due today .. 7 days overdue
     *   - late        : more than 7 days overdue
     */
    public function up(): void
    {
        Schema::create('property_payment_ledgers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('ownership_payment_plan_id');
            $table->foreign('ownership_payment_plan_id', 'ledger_plan_fk')
                ->references('id')->on('property_ownership_payment_plans')
                ->cascadeOnDelete();

            $table->enum('charge_type', [
                'booking_fee',
                'development_fee',
                'down_payment',
                '',
                'confirmation_fee',
                'possession_fee',
                'installment',
                'balloon',
                'balance',
                'other',
            ]);
            $table->string('display_name');
            $table->unsignedSmallInteger('sequence_order')->default(1);
            $table->unsignedSmallInteger('installment_no')->nullable();
            $table->unsignedSmallInteger('balloon_no')->nullable();

            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('remaining_balance', 15, 2)->default(0);

            $table->date('due_date')->nullable();
            $table->enum('payment_status', ['pending', 'current_due', 'late', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['ownership_payment_plan_id', 'sequence_order'], 'ledger_plan_seq_idx');
            $table->index(['payment_status', 'due_date'], 'ledger_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_payment_ledgers');
    }
};
