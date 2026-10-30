<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_late_fee_rules', function (Blueprint $table) {
            $table->unsignedInteger('grace_period_days')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_late_fee_rules', function (Blueprint $table) {
            $table->unsignedInteger('grace_period_days')->nullable()->default(0)->change();
        });
    }
};
