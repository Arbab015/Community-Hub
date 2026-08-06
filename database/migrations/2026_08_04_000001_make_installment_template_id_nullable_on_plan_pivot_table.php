<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customized installment attachments keep their values on the pivot row
     * with installment_template_id nulled, so the column must be nullable.
     * ON DELETE SET NULL is kept for the source-template reference.
     */
    public function up(): void
    {
        Schema::table('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->foreignId('installment_template_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('property_payment_plan_installment_templates', function (Blueprint $table) {
            $table->foreignId('installment_template_id')->change();
        });
    }
};
