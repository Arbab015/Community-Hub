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
        Schema::create('installment_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');

            $table->unsignedInteger('number_of_installments');
            $table->double('additional_installment_amount');
            $table->boolean('additional_installment_amount_in_per')->default(false);
            $table->enum('installment_frequency', ['monthly', 'quarterly', 'biannually', 'annually'])->nullable();
            $table->double('balloon_payment')->nullable();
            $table->boolean('balloon_payment_in_per')->nullable()->default(false);
            $table->enum('balloon_payment_frequency', ['quarterly', 'biannually', 'annually'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installment_templates');
    }
};
