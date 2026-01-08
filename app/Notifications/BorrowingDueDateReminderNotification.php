<?php

namespace App\Notifications;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BorrowingDueDateReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $borrowing;
    protected $daysUntilDue;

    /**
     * Create a new notification instance.
     */
    public function __construct(Borrowing $borrowing, int $daysUntilDue)
    {
        $this->borrowing = $borrowing;
        $this->daysUntilDue = $daysUntilDue;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $urgency = $this->daysUntilDue <= 1 ? 'warning' : 'info';
        
        /** @var \App\Models\Borrowing&\stdClass $borrowingWithId */
        $borrowingWithId = $this->borrowing;
        /** @var \Carbon\Carbon $dueDate */
        $dueDate = $this->borrowing->due_date;
        
        return (new MailMessage)
            ->level($urgency)
            ->subject('Pengingat Jatuh Tempo Peminjaman - ' . $this->borrowing->code)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Peminjaman Anda akan jatuh tempo dalam **' . $this->daysUntilDue . ' hari**.')
            ->line('**Detail Peminjaman:**')
            ->line('Kode Peminjaman: ' . $this->borrowing->code)
            ->line('Barang: ' . $this->borrowing->item->name)
            ->line('Jumlah: ' . $this->borrowing->quantity)
            ->line('Tanggal Jatuh Tempo: ' . $dueDate->format('d/m/Y'))
            ->action('Lihat Detail', url('/borrowings/' . $borrowingWithId->id))
            ->line('Harap kembalikan barang tepat waktu untuk menghindari denda keterlambatan.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var \App\Models\Borrowing&\stdClass $borrowingWithId */
        $borrowingWithId = $this->borrowing;
        /** @var \Carbon\Carbon $dueDate */
        $dueDate = $this->borrowing->due_date;
        
        return [
            'type' => 'borrowing_due_reminder',
            'borrowing_id' => $borrowingWithId->id,
            'borrowing_code' => $this->borrowing->code,
            'item_name' => $this->borrowing->item->name,
            'quantity' => $this->borrowing->quantity,
            'due_date' => $dueDate->format('Y-m-d'),
            'days_until_due' => $this->daysUntilDue,
            'message' => 'Peminjaman ' . $this->borrowing->code . ' akan jatuh tempo dalam ' . $this->daysUntilDue . ' hari.',
        ];
    }
}
