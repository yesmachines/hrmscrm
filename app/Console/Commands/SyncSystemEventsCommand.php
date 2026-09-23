<?php

namespace App\Console\Commands;

use App\Services\SystemEventSyncService;
use Illuminate\Console\Command;

class SyncSystemEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:sync-system {--year= : Specific year to sync events for (defaults to current year)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically synchronize work anniversaries, birthdays, new joiners, and approved employee leaves to the events calendar';

    /**
     * Execute the console command.
     */
    public function handle(SystemEventSyncService $service): int
    {
        $yearInput = $this->option('year');
        $year = $yearInput ? (int) $yearInput : (int) now()->format('Y');

        $this->info("Starting system events synchronization for year: {$year}...");

        $results = $service->syncAll($year);

        $this->table(
            ['Event Category', 'Records Synchronized'],
            [
                ['Work Anniversaries', $results['anniversaries']],
                ['Birthdays', $results['birthdays']],
                ['New Joiners', $results['new_joiners']],
                ['Approved Leaves', $results['leaves']],
            ]
        );

        $total = array_sum($results);
        $this->info("System events synchronization complete. Total events synced: {$total}");

        return self::SUCCESS;
    }
}
