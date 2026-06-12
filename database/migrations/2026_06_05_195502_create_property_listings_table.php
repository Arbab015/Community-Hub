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
        Schema::create('property_listings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->morphs('listable');

            $table->string('contact_no');
            $table->boolean('show_contact_no')->default(true);

            $table->string('email');
            $table->string('listing_type');
            $table->integer('price');
            $table->boolean('show_price')->default(true);

            $table->string('unit');
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_listings');
    }
};
