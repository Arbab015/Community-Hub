<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The late fee feature only ever writes "collected" rows, but the status is
     * stored so reports can distinguish collected fees and so the column stays
     * compatible with the fee audit trail.
     */
    public function up(): void
    {
        Schema::table('payment_late_fees', function (Blueprint $table) {
            $table->string('status', 20)->default('collected')->after('fee_amount');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('payment_late_fees', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
