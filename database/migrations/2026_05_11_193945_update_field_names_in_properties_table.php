<?php

use App\Models\Property;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {

            $table->enum('construction_status', [
                'constructed',
                'in_progress',
                'pending',
            ])->default('pending')->after('const_status');

        });

        Property::query()->each(function ($property) {

            $property->construction_status = $property->const_status;
            $property->save();

        });

        Schema::table('properties', function (Blueprint $table) {

            $table->dropColumn('const_status');

        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {

            $table->enum('const_status', [
                'constructed',
                'in_progress',
                'pending',
            ])->default('pending')->after('construction_status');

        });

        Property::query()->each(function ($property) {

            $property->const_status = $property->construction_status;
            $property->save();

        });

        Schema::table('properties', function (Blueprint $table) {

            $table->dropColumn('construction_status');

        });
    }
};
