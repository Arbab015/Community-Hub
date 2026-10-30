<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_late_fees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('property_payment_ledger_id');
            $table->unsignedBigInteger('payment_transaction_id');

            // Rule snapshot.
            $table->unsignedBigInteger('late_fee_rule_id')->nullable();
            $table->string('fine_type', 20);
            $table->decimal('fine_value', 15, 2);
            $table->unsignedInteger('rule_grace_days')->nullable();

            // Calculation snapshot.
            $table->unsignedInteger('days_late');
            $table->decimal('base_amount', 15, 2);
            $table->decimal('fee_amount', 15, 2);

            $table->timestamps();

            $table->unique(['payment_transaction_id', 'property_payment_ledger_id'], 'late_fee_txn_ledger_unique');
            $table->index('property_payment_ledger_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_late_fees');
    }
};
