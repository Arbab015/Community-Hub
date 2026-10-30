<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turn "property_owners" into an append-only ownership history: rename the
     * table and date columns, add the lifecycle status, the transfer link and
     * the derived active marker that enforces one active owner per entity.
     */
    public function up(): void
    {
        Schema::rename('property_owners', 'property_ownership_history');

        Schema::table('property_ownership_history', function (Blueprint $table) {
            $table->renameColumn('start_date', 'started_at');
            $table->renameColumn('end_date', 'ended_at');
        });

        Schema::table('property_ownership_history', function (Blueprint $table) {
            $table->enum('status', ['active', 'transferred', 'cancelled'])
                ->default('active')
                ->after('ended_at');
            $table->unsignedBigInteger('transfer_id')->nullable()->after('status');
            // Non-null only while active; the unique index guarantees a single
            // active ownership per property/unit (MySQL allows many NULLs).
            $table->string('active_marker')->nullable()->unique()->after('transfer_id');

            $table->index('status', 'poh_status_idx');
            $table->index('transfer_id', 'poh_transfer_idx');
        });

        // An ownership with no end date is the active one; a closed record was
        // transferred or cancelled before this column existed, so mark it
        // cancelled (no transfer metadata survives to say otherwise).
        DB::table('property_ownership_history')->whereNull('ended_at')->update([
            'status' => 'active',
            'active_marker' => DB::raw("CONCAT(property_ownerable_type, ':', property_ownerable_id)"),
        ]);

        DB::table('property_ownership_history')->whereNotNull('ended_at')->update([
            'status' => 'cancelled',
            'active_marker' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('property_ownership_history', function (Blueprint $table) {
            $table->dropUnique(['active_marker']);
            $table->dropIndex('poh_status_idx');
            $table->dropIndex('poh_transfer_idx');
            $table->dropColumn(['status', 'transfer_id', 'active_marker']);
        });

        Schema::table('property_ownership_history', function (Blueprint $table) {
            $table->renameColumn('started_at', 'start_date');
            $table->renameColumn('ended_at', 'end_date');
        });

        Schema::rename('property_ownership_history', 'property_owners');
    }
};
