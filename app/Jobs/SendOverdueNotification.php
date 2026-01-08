<?php

namespace App\Jobs;

use App\Models\Borrowing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOverdueNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Borrowing $borrowing
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->borrowing->user;
        
        // Calculate days overdue
        $daysOverdue = now()->diffInDays($this->borrowing->due_date);
        
        // Send email notification
        // Implement your email sending logic here
        // Example: Mail::to($user->email)->send(new OverdueNotificationMail($this->borrowing, $daysOverdue));
        
        /** @var \App\Models\Borrowing&\stdClass $borrowingWithId */
        $borrowingWithId = $this->borrowing;
        /** @var \App\Models\User&\stdClass $userWithId */
        $userWithId = $user;
        
        // Log the overdue notification
        logger()->info('Overdue notification sent', [
            'borrowing_id' => $borrowingWithId->id,
            'user_id' => $userWithId->id,
            'days_overdue' => $daysOverdue,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        /** @var \App\Models\Borrowing&\stdClass $borrowingWithId */
        $borrowingWithId = $this->borrowing;
        
        logger()->error('Failed to send overdue notification', [
            'borrowing_id' => $borrowingWithId->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
