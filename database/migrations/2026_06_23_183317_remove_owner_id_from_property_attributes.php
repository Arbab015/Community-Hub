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
            $table->dropForeign(['owner_id']);
            $table->dropUnique('unique_attribute_owner_type_title_id');
            $table->dropColumn('owner_id');
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
