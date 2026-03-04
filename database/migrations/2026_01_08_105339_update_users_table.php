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
        Schema::table('users', function (Blueprint $table) {
            $table->string('country')->nullable();
            $table->string('cnic_passport')->unique();
            $table->enum('gender', ['male', 'female', 'other']);
            $table->enum('marital_status', ['married', 'un-married']);
            $table->date('dob')->nullable();
            $table->string('profession')->nullable();
            $table->string('contact', 20)->nullable();
            $table->string('emergency_contact', 20)->nullable();
            $table->string('present_address')->nullable();
            $table->string('permanent_address');
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->boolean('is_main')->default(false);
            $table->string('name');
            $table->string('type');
            $table->string('extension');
            $table->string('size');
            $table->string('link');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};