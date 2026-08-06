<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $foreignKeyNames = collect(Schema::getForeignKeys('roles'))->pluck('name');

        Schema::table('roles', function (Blueprint $table) use ($foreignKeyNames) {
            if ($foreignKeyNames->contains('roles_user_id_foreign')) {
                $table->dropForeign(['user_id']);
            } elseif (Schema::hasIndex('roles', 'roles_user_id_foreign')) {
                $table->dropIndex('roles_user_id_foreign');
            }

            if (Schema::hasIndex('roles', 'roles_userid_name_unique')) {
                $table->dropUnique('roles_userid_name_unique');
            }

            if (! Schema::hasIndex('roles', 'roles_user_id_name_guard_name_unique')) {
                $table->unique(
                    ['user_id', 'name', 'guard_name'],
                    'roles_user_id_name_guard_name_unique'
                );
            }

            if (! $foreignKeyNames->contains('roles_user_id_foreign')) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
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
