<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Step 1: Add new enum column
        Schema::table('properties', function (Blueprint $table) {
            $table->enum('const_status', ['constructed', 'in_progress', 'pending'])
                ->default('pending')
                ->after('is_constructed');
        });

        // Step 2: Migrate old data
        DB::table('properties')->where('is_constructed', 1)
            ->update(['const_status' => 'constructed']);

        DB::table('properties')->where('is_constructed', 0)
            ->update(['const_status' => 'pending']);

        // Step 3: Drop old column
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('is_constructed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            //
        });
    }
};
