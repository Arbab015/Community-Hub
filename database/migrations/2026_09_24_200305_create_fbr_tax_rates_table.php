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
        Schema::create('fbr_tax_rates', function (Blueprint $table) {
            $table->id();
            // Who this rate applies to
            $table->enum('role', ['buyer', 'seller']);
            $table->enum('filer_status', ['filer', 'non_filer']);

            $table->unsignedBigInteger('value_from')->default(0);
            $table->unsignedBigInteger('value_to')->nullable(); // null = "and above"

            $table->decimal('rate_percent', 5, 2);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fbr_tax_rates');
    }
};
