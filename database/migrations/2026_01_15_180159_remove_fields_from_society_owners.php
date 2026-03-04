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
        Schema::table('society_owners', function (Blueprint $table) {
            $table->dropColumn('start_date');
            $table->dropColumn('ownership_document');
            $table->date('joining_date')->after('society_id');
        });
    }

    /**
     * 
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('society_owners', function (Blueprint $table) {
            //
        });
    }
};
