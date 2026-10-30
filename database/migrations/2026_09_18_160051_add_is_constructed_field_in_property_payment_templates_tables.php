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
        if (! Schema::hasColumn('payment_templates', 'is_constructed')) {
            Schema::table('payment_templates', function (Blueprint $table) {
                $table->boolean('is_constructed')->after('is_commercial')->default(false);
            });
        }

        if (! Schema::hasColumn('installment_templates', 'is_constructed')) {
            Schema::table('installment_templates', function (Blueprint $table) {
                $table->boolean('is_constructed')->after('is_commercial')->default(false);
            });
        }

        Schema::table('block_payment_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('block_payment_plans', 'is_constructed')) {
                $table->boolean('is_constructed')->after('is_commercial')->default(false);
            }

            if (Schema::hasIndex('block_payment_plans', 'block_payment_plans_block_id_is_commercial_unique')) {
                $table->dropForeign(['block_id']);
                $table->dropUnique('block_payment_plans_block_id_is_commercial_unique');
            }

            if (! Schema::hasIndex('block_payment_plans', 'block_payment_plans_block_id_is_commercial_is_constructed_unique')) {
                $table->unique(['block_id', 'is_commercial', 'is_constructed'], 'block_payment_plans_block_id_is_commercial_is_constructed_unique');
                $table->foreign('block_id')->references('id')->on('blocks')->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_templates', function (Blueprint $table) {
            $table->dropColumn('is_constructed');
        });
        Schema::table('installment_templates', function (Blueprint $table) {
            $table->dropColumn('is_constructed');
        });
        Schema::table('block_payment_plans', function (Blueprint $table) {
            if (Schema::hasIndex('block_payment_plans', 'block_payment_plans_block_id_is_commercial_is_constructed_unique')) {
                $table->dropForeign(['block_id']);
                $table->dropUnique('block_payment_plans_block_id_is_commercial_is_constructed_unique');
            }

            if (! Schema::hasIndex('block_payment_plans', 'block_payment_plans_block_id_is_commercial_unique')) {
                $table->unique(['block_id', 'is_commercial'], 'block_payment_plans_block_id_is_commercial_unique');
            }

            $table->foreign('block_id')->references('id')->on('blocks')->cascadeOnDelete();

            $table->dropColumn('is_constructed');
        });
    }
};
