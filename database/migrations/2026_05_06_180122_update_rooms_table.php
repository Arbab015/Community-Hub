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
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('has_attached_bathroom');
            $table->dropColumn('has_attached_ac');
            $table->dropColumn('has_attached_balcony');
            $table->dropColumn('has_attached_wardrobe');
            $table->string('amenities')->after('room_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
