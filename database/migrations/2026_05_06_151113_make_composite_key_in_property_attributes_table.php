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
        Schema::table('property_attributes', function (Blueprint $table) {
            $table->unique(['title', 'type'], 'unique_attribute_type_title_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_attributes', function (Blueprint $table) {
            //
        });
    }
};
