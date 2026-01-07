<?php

namespace App\Console\Commands;

use App\Models\Borrowing;
use App\Notifications\BorrowingDueDateReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBorrowingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'borrowings:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for borrowings that are due soon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for borrowings that need reminders...');

        // Get borrowings that are due in 3 days and 1 day
        $reminderDays = [3, 1];
        $totalSent = 0;

        foreach ($reminderDays as $days) {
            $targetDate = Carbon::today()->addDays($days);
            
            $borrowings = Borrowing::where('status', 'dipinjam')
                ->whereDate('due_date', $targetDate)
                ->with(['user', 'item'])
                ->get();

            foreach ($borrowings as $borrowing) {
                try {
                    $borrowing->user->notify(
                        new BorrowingDueDateReminderNotification($borrowing, $days)
                    );
                    $totalSent++;
                    $this->info("Reminder sent to {$borrowing->user->name} for borrowing {$borrowing->code}");
                } catch (\Exception $e) {
                    $this->error("Failed to send reminder for borrowing {$borrowing->code}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Total reminders sent: {$totalSent}");
        
        return Command::SUCCESS;
    }
}
