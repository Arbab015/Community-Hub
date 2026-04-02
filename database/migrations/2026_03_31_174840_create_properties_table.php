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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')
            ->nullable()
            ->constrained('users')
            ->restrictOnDelete();
            $table->foreignId('block_id')
            ->constrained('blocks')
            ->restrictOnDelete();
            $table->string('property_no');
            $table->string('type');
            $table->enum('category', ['residential', 'commercial', 'other']);
            $table->string('address');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
