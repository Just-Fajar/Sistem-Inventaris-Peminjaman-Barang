<?php

namespace App\Console\Commands;

use App\Models\Borrowing;
use App\Notifications\BorrowingOverdueNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckOverdueBorrowings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'borrowings:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue borrowings and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue borrowings...');

        // Get all overdue borrowings
        $overdueBorrowings = Borrowing::where('status', 'dipinjam')
            ->where('due_date', '<', Carbon::today())
            ->with(['user', 'item'])
            ->get();

        $totalNotified = 0;

        foreach ($overdueBorrowings as $borrowing) {
            $daysOverdue = Carbon::parse($borrowing->due_date)->diffInDays(Carbon::today());
            
            try {
                // Send notification
                $borrowing->user->notify(
                    new BorrowingOverdueNotification($borrowing, $daysOverdue)
                );
                
                $totalNotified++;
                $this->warn("Overdue notification sent to {$borrowing->user->name} for borrowing {$borrowing->code} ({$daysOverdue} days late)");
            } catch (\Exception $e) {
                $this->error("Failed to send notification for borrowing {$borrowing->code}: {$e->getMessage()}");
            }
        }

        $this->info("Total overdue notifications sent: {$totalNotified}");
        
        return Command::SUCCESS;
    }
}
