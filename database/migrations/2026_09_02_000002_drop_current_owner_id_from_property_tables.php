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
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_owner_id');
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('current_owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->foreignId('current_owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }
};
