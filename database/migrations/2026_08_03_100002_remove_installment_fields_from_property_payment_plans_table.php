<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Installment values move from the plan row into the pivot table so each
     * attached installment template keeps its own per-plan values. Also add
     * premium snapshot columns to the plan so edited values never touch the
     * payment template.
     */
    public function up(): void
    {
        // Copy any plan-level installment values into every attached pivot row
        $plans = DB::table('property_payment_plans')
            ->where(function ($q) {
                $q->whereNotNull('number_of_installments')
                    ->orWhereNotNull('additional_installment_amount')
                    ->orWhereNotNull('balloon_payment');
            })
            ->get();

        foreach ($plans as $plan) {
            DB::table('property_payment_plan_installment_templates')
                ->where('property_payment_plan_id', $plan->id)
                ->update([
                    'number_of_installments' => $plan->number_of_installments,
                    'installment_frequency' => $plan->installment_frequency,
                    'additional_installment_amount' => $plan->additional_installment_amount,
                    'additional_installment_amount_in_per' => $plan->additional_installment_amount_in_per,
                    'balloon_payment' => $plan->balloon_payment,
                    'balloon_payment_in_per' => $plan->balloon_payment_in_per,
                    'balloon_payment_frequency' => $plan->balloon_payment_frequency,
                ]);
        }

        // Backfill the pivot name from the source template when missing
        DB::table('property_payment_plan_installment_templates as pvt')
            ->join('installment_templates as it', 'it.id', '=', 'pvt.installment_template_id')
            ->whereNull('pvt.name')
            ->update(['pvt.name' => DB::raw('it.name')]);

        Schema::table('property_payment_plans', function (Blueprint $table) {
            $table->dropColumn([
                'balloon_payment',
                'balloon_payment_in_per',
                'balloon_payment_frequency',
                'number_of_installments',
                'additional_installment_amount',
                'additional_installment_amount_in_per',
                'installment_frequency',
            ]);
        });

        Schema::table('property_payment_plans', function (Blueprint $table) {
            $table->double('corner_premium')->nullable();
            $table->boolean('corner_premium_in_per')->nullable()->default(true);

            $table->double('park_facing_premium')->nullable();
            $table->boolean('park_facing_premium_in_per')->nullable()->default(true);

            $table->double('main_boulevard_premium')->nullable();
            $table->boolean('main_boulevard_premium_in_per')->nullable()->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('property_payment_plans', function (Blueprint $table) {
            $table->dropColumn([
                'corner_premium',
                'corner_premium_in_per',
                'park_facing_premium',
                'park_facing_premium_in_per',
                'main_boulevard_premium',
                'main_boulevard_premium_in_per',
            ]);
        });

        Schema::table('property_payment_plans', function (Blueprint $table) {
            $table->double('balloon_payment')->nullable();
            $table->boolean('balloon_payment_in_per')->nullable()->default(false);
            $table->enum('balloon_payment_frequency', ['quarterly', 'biannually', 'annually'])->nullable();
            $table->unsignedInteger('number_of_installments')->nullable();
            $table->double('additional_installment_amount')->nullable();
            $table->boolean('additional_installment_amount_in_per')->default(false)->nullable();
            $table->enum('installment_frequency', ['monthly', 'quarterly', 'biannually', 'annually'])->nullable();
        });

        // Restore plan-level values from the first attached pivot row, if any
        $pivots = DB::table('property_payment_plan_installment_templates')
            ->orderBy('id')
            ->get()
            ->groupBy('property_payment_plan_id');

        foreach ($pivots as $planId => $rows) {
            $row = $rows->first();

            DB::table('property_payment_plans')->where('id', $planId)->update([
                'number_of_installments' => $row->number_of_installments,
                'installment_frequency' => $row->installment_frequency,
                'additional_installment_amount' => $row->additional_installment_amount,
                'additional_installment_amount_in_per' => $row->additional_installment_amount_in_per,
                'balloon_payment' => $row->balloon_payment,
                'balloon_payment_in_per' => $row->balloon_payment_in_per,
                'balloon_payment_frequency' => $row->balloon_payment_frequency,
            ]);
        }
    }
};
