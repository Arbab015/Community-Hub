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
        Schema::table('rules', function (Blueprint $table) {
            $fkNames = collect(Schema::getForeignKeys('rules'))->pluck('name');

            if (Schema::hasColumn('rules', 'user_id')) {
                if ($fkNames->contains('rules_user_id_foreign')) {
                    $table->dropForeign(['user_id']);
                }
                $table->dropColumn('user_id');
            }

            if (! Schema::hasColumn('rules', 'society_owner_id')) {
                $table->unsignedBigInteger('society_owner_id');
                $table->foreign('society_owner_id')->references('id')->on('users')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            //
        });
    }
};
