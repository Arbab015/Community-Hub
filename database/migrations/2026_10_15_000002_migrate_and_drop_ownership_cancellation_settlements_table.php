<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ownership_cancellation_settlements')) {
            $rows = DB::table('ownership_cancellation_settlements')->get();

            foreach ($rows as $row) {
                // Rows without a committed plan have no context left to attach
                // to (the new table is plan-scoped), so they are not carried over.
                if (! $row->plan_id) {
                    continue;
                }

                DB::table('ownership_cancellation_reversals')->insert([
                    'uuid' => (string) Str::uuid(),
                    'plan_id' => $row->plan_id,
                    'status' => 'returned',
                    'type' => 'ownership_cancel_return',
                    'reason' => 'Ownership reservation cancelled: the full paid amount (charges + FBR tax) was returned to the owner.',
                    'remarks' => $row->remarks,
                    'reversed_at' => $row->created_at,
                    'reversed_by' => $row->cancelled_by_user_id,
                    'return_amount' => $row->return_amount,
                    'return_tax' => $row->return_tax,
                    'return_total' => $row->return_total,
                    'payment_count' => $row->payment_count,
                    'currency' => $row->currency,
                    'commission_type' => $row->commission_type,
                    'commission_value' => $row->commission_value,
                    'commission_base' => $row->commission_base,
                    'commission_amount' => $row->commission_amount,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::dropIfExists('ownership_cancellation_settlements');
        }
    }

    public function down(): void
    {
        Schema::create('ownership_cancellation_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ownerable_type');
            $table->unsignedBigInteger('ownerable_id');
            $table->unsignedBigInteger('property_owner_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->decimal('return_amount', 15, 2)->default(0);
            $table->decimal('return_tax', 15, 2)->default(0);
            $table->decimal('return_total', 15, 2)->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->string('commission_type')->nullable();
            $table->decimal('commission_value', 15, 2)->default(0);
            $table->decimal('commission_base', 15, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('PKR');
            $table->string('receipt_no')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->timestamps();
            $table->index(['ownerable_type', 'ownerable_id'], 'ocs_ownerable_idx');
            $table->foreign('plan_id', 'ocs_plan_fk')
                ->references('id')->on('property_reserved_payment_plans')->nullOnDelete();
            $table->foreign('owner_id', 'ocs_owner_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by_user_id', 'ocs_cancelled_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }
};
