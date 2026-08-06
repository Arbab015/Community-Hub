<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pivot table lost its foreign keys (no ON DELETE behavior was
     * applied), leaving orphaned rows when a payment plan is removed.
     * Remove orphans and restore the intended constraints:
     *   - plan delete -> pivot rows cascade
     *   - installment template delete -> pivot reference set null
     */
    public function up(): void
    {
        DB::table('property_payment_plan_installment_templates as p')
            ->leftJoin('property_payment_plans as pp', 'pp.id', '=', 'p.property_payment_plan_id')
            ->whereNull('pp.id')
            ->delete();

        Schema::table('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->foreign('property_payment_plan_id', 'fk_ppp_id')
                ->references('id')
                ->on('property_payment_plans')
                ->cascadeOnDelete();

            $table->foreign('installment_template_id', 'fk_inst_template_id')
                ->references('id')
                ->on('installment_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->dropForeign('fk_ppp_id');
            $table->dropForeign('fk_inst_template_id');
        });
    }
};
