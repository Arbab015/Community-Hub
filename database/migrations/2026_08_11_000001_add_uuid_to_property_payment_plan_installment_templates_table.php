<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Adds a uuid column to the installment-attachment pivot rows so URLs
     * never expose the auto-increment id.
     */
    public function up(): void
    {
        Schema::table('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        foreach (DB::table('property_payment_plan_installment_templates')->whereNull('uuid')->pluck('id') as $id) {
            DB::table('property_payment_plan_installment_templates')
                ->where('id', $id)
                ->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::table('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->dropUnique('property_payment_plan_installment_templates_uuid_unique');
            $table->dropColumn('uuid');
        });
    }
};
