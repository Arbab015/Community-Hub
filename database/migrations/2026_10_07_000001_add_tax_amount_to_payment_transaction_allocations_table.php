<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the FBR tax share of each charge a payment settled.
     *
     * The transaction still carries the aggregate tax_amount; this column
     * records how much of it belongs to each lined allocation so un-paying a
     * charge can refund its exact tax from the database instead of recomputing
     * proportional shares, and so old receipts survive later slab edits.
     *
     * SUM(payment_transaction_allocations.tax_amount) === transactions.tax_amount
     */
    public function up(): void
    {
        Schema::table('payment_transaction_allocations', function (Blueprint $table) {
            $table->decimal('tax_amount', 15, 2)->default(0)->after('amount_allocated');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transaction_allocations', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });
    }
};
