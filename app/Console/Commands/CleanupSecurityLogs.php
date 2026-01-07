<?php

namespace App\Console\Commands;

use App\Models\SecurityAuditLog;
use Illuminate\Console\Command;

class CleanupSecurityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:cleanup-logs {--days= : Number of days to retain logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old security audit logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days') ?? config('security.audit_log_retention_days', 90);

        $this->info("Cleaning up security audit logs older than {$days} days...");

        $deleted = SecurityAuditLog::cleanup($days);

        $this->info("Successfully deleted {$deleted} old security audit log(s).");

        return Command::SUCCESS;
    }
}
