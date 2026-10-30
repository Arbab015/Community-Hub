<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_payment_ledgers', function (Blueprint $table) {
            // reversed = the ownership was cancelled and the charge's payment
            // was handed back; the charge is closed for good (not reopened
            // like a normal unpay, which goes back to pending).
            $table->enum('payment_status', ['pending', 'current_due', 'late', 'paid', 'reversed'])
                ->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('property_payment_ledgers', function (Blueprint $table) {
            $table->enum('payment_status', ['pending', 'current_due', 'late', 'paid'])
                ->default('pending')->change();
        });
    }
};
