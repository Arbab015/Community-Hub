<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add nullable UUID column first
        Schema::table('property_units', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        Schema::table('floors', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Step 2: Backfill UUIDs for existing records
        DB::table('property_units')->whereNull('uuid')->get()->each(function ($row) {
            DB::table('property_units')->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        });

        DB::table('rooms')->whereNull('uuid')->get()->each(function ($row) {
            DB::table('rooms')->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        });

        DB::table('floors')->whereNull('uuid')->get()->each(function ($row) {
            DB::table('floors')->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        });

        // Step 3: Make column NOT NULL + UNIQUE
        Schema::table('property_units', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });

        Schema::table('floors', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_related_tables', function (Blueprint $table) {
            //
        });
    }
};
