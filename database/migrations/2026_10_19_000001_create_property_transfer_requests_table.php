<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference_no')->unique();

            // The property/unit being transferred, the committed plan that had
            // to be fully paid before the request could be made, and the
            // ownership (old owner) it currently sits under.
            $table->morphs('ownerable');
            $table->unsignedBigInteger('payment_plan_id');
            $table->unsignedBigInteger('property_owner_id');

            // Who asked, who receives, and which society is approving.
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('recipient_user_id');
            $table->unsignedBigInteger('society_id');

            // Society fee frozen at request time so later fee edits never
            // change an already-submitted request.
            $table->unsignedBigInteger('transfer_fee_id')->nullable();
            $table->decimal('transfer_fee', 15, 2)->default(0);
            $table->boolean('transfer_fee_is_percentage')->default(false);
            $table->decimal('fee_base', 15, 2)->default(0);
            $table->string('currency', 10)->default('PKR');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            // Non-null only while pending; the unique index guarantees a single
            // open request per property/unit (MySQL allows many NULLs).
            $table->string('pending_marker')->nullable()->unique();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('acted_by_user_id')->nullable();
            $table->timestamp('acted_at')->nullable();

            $table->timestamps();

            $table->index('society_id', 'ptr_society_idx');
            $table->index('status', 'ptr_status_idx');
            $table->index('recipient_user_id', 'ptr_recipient_idx');
            $table->foreign('payment_plan_id', 'ptr_plan_fk')
                ->references('id')->on('property_reserved_payment_plans')->restrictOnDelete();
            $table->foreign('property_owner_id', 'ptr_owner_fk')
                ->references('id')->on('property_owners')->restrictOnDelete();
            $table->foreign('society_id', 'ptr_society_fk')
                ->references('id')->on('societies')->restrictOnDelete();
            $table->foreign('transfer_fee_id', 'ptr_fee_fk')
                ->references('id')->on('preperty_transfer_fees')->nullOnDelete();
            $table->foreign('acted_by_user_id', 'ptr_acted_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_transfer_requests');
    }
};
