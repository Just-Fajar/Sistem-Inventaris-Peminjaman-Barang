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
        
        return (new MailMessage)
            ->level($urgency)
            ->subject('Pengingat Jatuh Tempo Peminjaman - ' . $this->borrowing->borrowing_code)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Peminjaman Anda akan jatuh tempo dalam **' . $this->daysUntilDue . ' hari**.')
            ->line('**Detail Peminjaman:**')
            ->line('Kode Peminjaman: ' . $this->borrowing->borrowing_code)
            ->line('Barang: ' . $this->borrowing->item->name)
            ->line('Jumlah: ' . $this->borrowing->quantity)
            ->line('Tanggal Jatuh Tempo: ' . $this->borrowing->due_date->format('d/m/Y'))
            ->action('Lihat Detail', url('/borrowings/' . $this->borrowing->id))
            ->line('Harap kembalikan barang tepat waktu untuk menghindari denda keterlambatan.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'borrowing_due_reminder',
            'borrowing_id' => $this->borrowing->id,
            'borrowing_code' => $this->borrowing->borrowing_code,
            'item_name' => $this->borrowing->item->name,
            'quantity' => $this->borrowing->quantity,
            'due_date' => $this->borrowing->due_date->format('Y-m-d'),
            'days_until_due' => $this->daysUntilDue,
            'message' => 'Peminjaman ' . $this->borrowing->borrowing_code . ' akan jatuh tempo dalam ' . $this->daysUntilDue . ' hari.',
        ];
    }
}
