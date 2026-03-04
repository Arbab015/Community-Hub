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
        Schema::table('reports', function (Blueprint $table) {
            $table->uuid('uuid')->after('id');
            $table->enum('type', [
                'spam',
                'misleading',
                'hate_speech',
                'harassment',
                'violence',
                'adult_content',
                'scam',
                'illegal_activity',
                'off_topic',
                'other'
            ])->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            //
        });
    }
};