<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownership_cancellation_reversals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // The committed plan this cancellation settles; every piece of
            // context (property/unit, member, ownership) hangs off the plan,
            // so nothing is duplicated here.
            $table->unsignedBigInteger('plan_id');

            // What happened to the payments, who did it, when and why.
            $table->string('status')->default('returned');
            $table->string('type')->default('ownership_cancel_return');
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();

            // Return amount details (money handed back to the owner).
            $table->decimal('return_amount', 15, 2)->default(0);
            $table->decimal('return_tax', 15, 2)->default(0);
            $table->decimal('return_total', 15, 2)->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->string('currency', 10)->default('PKR');

            // Commission / reduction details.
            $table->string('commission_type')->nullable(); // amount | percentage
            $table->decimal('commission_value', 15, 2)->default(0);
            $table->decimal('commission_base', 15, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('plan_id', 'ocr_plan_idx');
            $table->foreign('plan_id', 'ocr_plan_fk')
                ->references('id')->on('property_reserved_payment_plans')->restrictOnDelete();
            $table->foreign('reversed_by', 'ocr_reversed_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_cancellation_reversals');
    }
};
