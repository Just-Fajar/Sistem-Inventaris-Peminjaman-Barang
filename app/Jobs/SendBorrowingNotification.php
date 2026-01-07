<?php

namespace App\Jobs;

use App\Models\Borrowing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBorrowingNotification implements ShouldQueue
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
        public Borrowing $borrowing,
        public string $notificationType
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->borrowing->user;
        
        // Send notification based on type
        switch ($this->notificationType) {
            case 'approved':
                $user->notify(new \App\Notifications\BorrowingApprovedNotification($this->borrowing));
                break;
            case 'rejected':
                // Implement if rejection notification exists
                break;
            case 'reminder':
                // Implement reminder notification
                break;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        logger()->error('Failed to send borrowing notification', [
            'borrowing_id' => $this->borrowing->id,
            'type' => $this->notificationType,
            'error' => $exception->getMessage(),
        ]);
    }
}
