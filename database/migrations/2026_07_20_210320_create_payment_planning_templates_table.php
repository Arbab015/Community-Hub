<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_planning_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->double('booking_fee')->nullable();
            $table->boolean('booking_fee_in_per')->nullable()->default(false);

            $table->double('development_fee')->nullable();
            $table->boolean('development_fee_in_per')->nullable()->default(false);
            $table->boolean('development_fee_surplus')->nullable()->default(false);

            $table->double('down_payment')->nullable();
            $table->boolean('down_payment_in_per')->nullable()->default(false);

            $table->double('transfer_fee')->nullable();
            $table->boolean('transfer_fee_in_per')->nullable()->default(false);
            $table->boolean('transfer_fee_surplus')->nullable()->default(true);

            $table->double('confirmation_fee')->nullable();
            $table->boolean('confirmation_fee_in_per')->nullable()->default(false);

            $table->double('possession_fee')->nullable();
            $table->boolean('possession_fee_in_per')->nullable()->default(false);
            $table->boolean('possession_fee_surplus')->nullable()->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_planning_templates');
    }
};
