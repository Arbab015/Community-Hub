<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allocation of a single payment across one or more ledger charges,
     * satisfying "one payment can settle multiple charges".
     */
    public function up(): void
    {
        Schema::create('payment_transaction_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_transaction_id');
            $table->foreign('payment_transaction_id', 'alloc_txn_fk')
                ->references('id')->on('payment_transactions')
                ->cascadeOnDelete();

            $table->foreignId('property_payment_ledger_id');
            $table->foreign('property_payment_ledger_id', 'alloc_ledger_fk')
                ->references('id')->on('property_payment_ledgers')
                ->cascadeOnDelete();

            $table->decimal('amount_allocated', 15, 2);

            $table->timestamps();

            $table->index('payment_transaction_id', 'txn_alloc_txn_idx');
            $table->index('property_payment_ledger_id', 'txn_alloc_ledger_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transaction_allocations');
    }
};