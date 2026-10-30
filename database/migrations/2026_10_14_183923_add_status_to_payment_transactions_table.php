<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_transactions', 'status')) {
            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('status')->default('paid')->after('description')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
