<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_payment_ledgers', function (Blueprint $table) {
            $table->enum('charge_type', [
                'booking_fee',
                'development_fee',
                'down_payment',
                'confirmation_fee',
                'possession_fee',
                'installment',
                'balloon',
                'balance',
                'other',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('property_payment_ledgers', function (Blueprint $table) {
            $table->enum('charge_type', [
                'booking_fee',
                'development_fee',
                'down_payment',
                'transfer_fee',
                'confirmation_fee',
                'possession_fee',
                'installment',
                'balloon',
                'balance',
                'other',
            ])->change();
        });
    }
};
