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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained('floors')->nullable()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('property_units')->nullable()->restrictOnDelete();
            $table->string('room_type');
            $table->boolean('has_attached_bathroom')->default(false);
            $table->boolean('has_attached_ac')->default(false);
            $table->boolean('has_attached_balcony')->default(false);
            $table->boolean('has_attached_wardrobe')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
