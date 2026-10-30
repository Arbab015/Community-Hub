<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        $columns = array_values(array_filter([
            'reversed_by_user_id',
            'reversed_at',
            'reversal_reason',
            'reversal_type',
            'reversal_remarks',
        ], fn ($column) => Schema::hasColumn('payment_transactions', $column)));

        if ($columns === []) {
            return;
        }

        if (in_array('reversed_by_user_id', $columns, true)) {
            // PHP 8.2: MySQL cannot drop a column that still has a foreign key,
            // so the constraint is removed first when it is present at all.
            try {
                Schema::table('payment_transactions', function (Blueprint $table) {
                    $table->dropForeign(['reversed_by_user_id']);
                });
            } catch (\Throwable $e) {
                // No such constraint - nothing to drop.
            }
        }

        Schema::table('payment_transactions', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('reversed_by_user_id')->nullable()->after('status')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversed_by_user_id');
            $table->string('reversal_reason')->nullable()->after('reversed_at');
            $table->string('reversal_type')->nullable()->after('reversal_reason');
            $table->text('reversal_remarks')->nullable()->after('reversal_type');
        });
    }
};
