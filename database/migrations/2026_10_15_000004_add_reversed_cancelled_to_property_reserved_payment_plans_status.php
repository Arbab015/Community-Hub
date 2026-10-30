<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_reserved_payment_plans', function (Blueprint $table) {
            // reversed  = reservation cancelled and the paid amount was returned
            // cancelled = reservation closed with nothing paid
            $table->enum('status', ['active', 'in-progress', 'completed', 'reversed', 'cancelled'])
                ->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('property_reserved_payment_plans', function (Blueprint $table) {
            $table->enum('status', ['active', 'in-progress', 'completed'])
                ->default('active')->change();
        });
    }
};
