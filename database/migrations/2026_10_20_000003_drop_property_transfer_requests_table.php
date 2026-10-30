<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the first (request-based) transfer stack. It held one row of test
 * data on the development database and is replaced by the ownership-history +
 * property_transfers design.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('property_transfer_requests')) {
            $rows = DB::table('property_transfer_requests')->count();
            logger()->info("Dropping property_transfer_requests ({$rows} row(s)).");
        }

        Schema::dropIfExists('property_transfer_requests');
    }

    public function down(): void
    {
        // Intentionally not restorable: the old request stack was disposable.
    }
};
