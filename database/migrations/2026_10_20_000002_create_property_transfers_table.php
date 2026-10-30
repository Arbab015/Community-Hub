<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A transfer freezes the outgoing ownership/plan and the society fee,
     * then (on approval) links the new ownership and the transfer-fee plan.
     * Reserved plans gain the type/parent/base columns needed to act as a
     * transfer-fee plan, and the ledger re-opens `transfer_fee` as a charge.
     */
    public function up(): void
    {
        Schema::table('property_reserved_payment_plans', function (Blueprint $table) {
            $table->enum('plan_type', ['reservation', 'transfer'])
                ->default('reservation')
                ->after('status');
            $table->unsignedBigInteger('parent_plan_id')->nullable()->after('plan_type');
            $table->decimal('fee_base', 15, 2)->nullable()->after('parent_plan_id');

            $table->index(['property_ownerable_type', 'property_ownerable_id', 'plan_type'], 'prpp_morph_type_idx');
            $table->foreign('parent_plan_id', 'prpp_parent_fk')
                ->references('id')->on('property_reserved_payment_plans')->nullOnDelete();
        });

        Schema::create('property_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference_no')->unique();

            // The property/unit being transferred and the ownership/plan it is
            // leaving. `property_ownerable_*` mirrors every other ownership FK.
            $table->string('property_ownerable_type');
            $table->unsignedBigInteger('property_ownerable_id');
            $table->index(['property_ownerable_type', 'property_ownerable_id'], 'ptf_ownerable_idx');
            $table->unsignedBigInteger('from_ownership_id');
            $table->unsignedBigInteger('from_plan_id');
            $table->unsignedBigInteger('new_plan_id')->nullable();

            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->unsignedBigInteger('society_id');

            // Society fee frozen at request time. For a percentage fee,
            // `transfer_fee` stores the RATE and `fee_amount` the computed
            // absolute amount, so later fee-config edits cannot rewrite it.
            $table->unsignedBigInteger('transfer_fee_id')->nullable();
            $table->decimal('transfer_fee', 15, 2)->default(0);
            $table->boolean('transfer_fee_in_per')->default(false);
            $table->decimal('fee_base', 15, 2)->default(0);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('PKR');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            // Non-null only while pending; the unique index guarantees a single
            // open transfer per property/unit (MySQL allows many NULLs).
            $table->string('pending_marker')->nullable()->unique();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('acted_by_user_id')->nullable();
            $table->timestamp('acted_at')->nullable();

            $table->timestamps();

            $table->index('society_id', 'ptf_society_idx');
            $table->index('status', 'ptf_status_idx');
            $table->index('recipient_user_id', 'ptf_recipient_idx');

            $table->foreign('from_ownership_id', 'ptf_from_ownership_fk')
                ->references('id')->on('property_ownership_history')->restrictOnDelete();
            $table->foreign('from_plan_id', 'ptf_from_plan_fk')
                ->references('id')->on('property_reserved_payment_plans')->restrictOnDelete();
            $table->foreign('new_plan_id', 'ptf_new_plan_fk')
                ->references('id')->on('property_reserved_payment_plans')->nullOnDelete();
            $table->foreign('society_id', 'ptf_society_fk')
                ->references('id')->on('societies')->restrictOnDelete();
            $table->foreign('transfer_fee_id', 'ptf_fee_fk')
                ->references('id')->on('preperty_transfer_fees')->nullOnDelete();
            $table->foreign('acted_by_user_id', 'ptf_acted_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('property_ownership_history', function (Blueprint $table) {
            $table->foreign('transfer_id', 'poh_transfer_fk')
                ->references('id')->on('property_transfers')->nullOnDelete();
        });

        DB::statement("ALTER TABLE property_payment_ledgers MODIFY charge_type ENUM(
            'booking_fee','development_fee','down_payment','confirmation_fee',
            'possession_fee','installment','balloon','balance','other','transfer_fee'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE property_payment_ledgers MODIFY charge_type ENUM(
            'booking_fee','development_fee','down_payment','confirmation_fee',
            'possession_fee','installment','balloon','balance','other'
        ) NOT NULL");

        Schema::table('property_ownership_history', function (Blueprint $table) {
            $table->dropForeign('poh_transfer_fk');
        });

        Schema::dropIfExists('property_transfers');

        Schema::table('property_reserved_payment_plans', function (Blueprint $table) {
            $table->dropForeign('prpp_parent_fk');
            $table->dropIndex('prpp_morph_type_idx');
            $table->dropColumn(['plan_type', 'parent_plan_id', 'fee_base']);
        });
    }
};
