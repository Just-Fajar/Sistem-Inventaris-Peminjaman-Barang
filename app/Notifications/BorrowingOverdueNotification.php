<?php

namespace App\Notifications;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BorrowingOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $borrowing;
    protected $daysOverdue;

    /**
     * Create a new notification instance.
     */
    public function __construct(Borrowing $borrowing, int $daysOverdue)
    {
        $this->borrowing = $borrowing;
        $this->daysOverdue = $daysOverdue;
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
        return (new MailMessage)
            ->level('error')
            ->subject('Peminjaman Terlambat - ' . $this->borrowing->borrowing_code)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Peminjaman Anda telah **terlambat ' . $this->daysOverdue . ' hari**.')
            ->line('**Detail Peminjaman:**')
            ->line('Kode Peminjaman: ' . $this->borrowing->borrowing_code)
            ->line('Barang: ' . $this->borrowing->item->name)
            ->line('Jumlah: ' . $this->borrowing->quantity)
            ->line('Tanggal Jatuh Tempo: ' . $this->borrowing->due_date->format('d/m/Y'))
            ->line('Hari Keterlambatan: ' . $this->daysOverdue . ' hari')
            ->action('Lihat Detail', url('/borrowings/' . $this->borrowing->id))
            ->line('Harap segera kembalikan barang yang dipinjam untuk menghindari sanksi lebih lanjut.')
            ->line('Terima kasih atas perhatian Anda.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'borrowing_overdue',
            'borrowing_id' => $this->borrowing->id,
            'borrowing_code' => $this->borrowing->borrowing_code,
            'item_name' => $this->borrowing->item->name,
            'quantity' => $this->borrowing->quantity,
            'due_date' => $this->borrowing->due_date->format('Y-m-d'),
            'days_overdue' => $this->daysOverdue,
            'message' => 'Peminjaman ' . $this->borrowing->borrowing_code . ' telah terlambat ' . $this->daysOverdue . ' hari.',
        ];
    }
}
