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
        Schema::create('preperty_transfer_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained('societies')->cascadeOnDelete();
            $table->integer('min_size')->nullable();
            $table->integer('max_size')->nullable();
            $table->enum('size_unit', ['marla', 'square_yard']);
            $table->decimal('fee', 12, 2);
            $table->boolean('is_percentage')->default(false);
            $table->timestamps();

            $table->unique(['society_id', 'min_size', 'max_size']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preperty_transfer_fees');
    }
};
