<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {

            $table->dropForeign(['user_id']);

            $table->dropUnique('roles_userid_name_unique');

            $table->unique(
                ['user_id', 'name', 'guard_name'],
                'roles_user_id_name_guard_name_unique'
            );

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {

            // Reverse steps
            $table->dropForeign(['user_id']);

            $table->dropUnique('roles_user_id_name_guard_name_unique');

            $table->unique(
                ['user_id', 'name'],
                'roles_userid_name_unique'
            );

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
