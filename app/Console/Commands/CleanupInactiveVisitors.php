<?php

// app/Console/Commands/CleanupInactiveVisitors.php

namespace App\Console\Commands;

use App\Models\PropertyInquiry;
use App\Models\Visitor;
use App\Models\VisitorFavourite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupInactiveVisitors extends Command
{
    protected $signature = 'visitors:cleanup {--months=6} {--dry-run}';

    protected $description = 'Delete visitors (and their related records) inactive for X months';

    public function handle()
    {
        $months = (int) $this->option('months');
        $cutoff = now()->subMonths($months);
        $dryRun = $this->option('dry-run');

        $query = Visitor::where('last_active_at', '<', $cutoff)
            ->orWhereNull('last_active_at');

        $total = $query->count();
        $this->info("Found {$total} visitor(s) inactive since before {$cutoff->toDateString()}.");

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry run — no records deleted.');

            return self::SUCCESS;
        }
        $deletedCount = 0;

        Visitor::where('last_active_at', '<', $cutoff)
            ->orWhereNull('last_active_at')
            ->chunkById(500, function ($visitors) use (&$deletedCount) {
                foreach ($visitors as $visitor) {
                    DB::transaction(function () use ($visitor) {
                        VisitorFavourite::where('visitor_id', $visitor->id)->delete();
                        PropertyInquiry::where('visitor_id', $visitor->id)->delete();
                        $visitor->delete();
                    });
                    $deletedCount++;
                }
            });

        $this->info("Deleted {$deletedCount} visitor(s) and their related records.");
        Log::info("visitors:cleanup — deleted {$deletedCount} inactive visitors (cutoff: {$cutoff}).");

        return self::SUCCESS;
    }
}
