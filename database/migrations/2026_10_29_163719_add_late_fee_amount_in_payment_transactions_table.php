<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cached total of the collected late fees of a transaction. It is part of
     * amount_paid and never decreases (late fees are non-refundable).
     */
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 15, 2)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('late_fee_amount');
        });
    }
};
