<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base society_owners table (originally imported via SQL, recreated so
     * the incremental update migrations can run from a fresh database).
     */
    public function up(): void
    {
        Schema::create('society_owners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('society_id');
            $table->unsignedBigInteger('user_id');
            $table->date('start_date');
            $table->string('ownership_document');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_owners');
    }
};
