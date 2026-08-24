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
        Schema::create('block_price_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->integer('min_size')->nullable();
            $table->integer('max_size')->nullable();
            $table->integer('price');
            $table->enum('price_unit', ['marla', 'square_yard']);
            $table->timestamps();

            $table->unique(['block_id', 'min_size', 'max_size']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('block_price_ranges');
    }
};
