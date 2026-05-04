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
        Schema::table('floors', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->onDelete('cascade');

            $table->softDeletes();
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->dropForeign(['floor_id']);
            $table->foreign('floor_id')
                ->references('id')
                ->on('floors')
                ->onDelete('cascade');
            $table->softDeletes();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['floor_id']);
            $table->foreign('floor_id')
                ->references('id')
                ->on('floors')
                ->onDelete('cascade');

            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')
                ->references('id')
                ->on('property_units')
                ->onDelete('cascade');

            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties_related', function (Blueprint $table) {
            //
        });
    }
};
