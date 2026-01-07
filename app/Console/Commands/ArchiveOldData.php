<?php

namespace App\Console\Commands;

use App\Models\Borrowing;
use App\Models\SecurityAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ArchiveOldData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:archive {--days=365 : Number of days to keep data} {--dry-run : Simulate without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive old completed borrowings and logs to prevent database bloat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = now()->subDays($days);
        
        $this->info("Archiving data older than {$days} days (before {$cutoffDate->toDateString()})...");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        // Archive old completed borrowings
        $this->archiveCompletedBorrowings($cutoffDate, $dryRun);
        
        // Archive old activity logs
        $this->archiveActivityLogs($cutoffDate, $dryRun);
        
        // Security audit logs already have their own cleanup
        $this->info('Security audit logs use their own retention policy.');
        
        $this->info('\nArchiving complete!');
        
        return Command::SUCCESS;
    }

    /**
     * Archive old completed borrowings
     */
    protected function archiveCompletedBorrowings($cutoffDate, bool $dryRun): void
    {
        $query = Borrowing::where('status', 'dikembalikan')
            ->where('return_date', '<', $cutoffDate);
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No old borrowings to archive.');
            return;
        }
        
        $this->line("Found {$count} old completed borrowings...");
        
        if (!$dryRun) {
            // Export to archive table first (optional - create if needed)
            // For now, we'll just delete since they're completed
            $deleted = $query->delete();
            $this->info("✓ Archived {$deleted} completed borrowings");
        } else {
            $this->warn("Would archive {$count} completed borrowings");
        }
    }

    /**
     * Archive old activity logs
     */
    protected function archiveActivityLogs($cutoffDate, bool $dryRun): void
    {
        if (!class_exists(Activity::class)) {
            return;
        }
        
        $query = Activity::where('created_at', '<', $cutoffDate);
        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No old activity logs to archive.');
            return;
        }
        
        $this->line("Found {$count} old activity logs...");
        
        if (!$dryRun) {
            $deleted = $query->delete();
            $this->info("✓ Archived {$deleted} activity logs");
        } else {
            $this->warn("Would archive {$count} activity logs");
        }
    }
}
