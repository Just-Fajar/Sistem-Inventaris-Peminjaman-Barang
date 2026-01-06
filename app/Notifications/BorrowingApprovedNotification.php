<?php

namespace App\Notifications;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BorrowingApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $borrowing;

    /**
     * Create a new notification instance.
     */
    public function __construct(Borrowing $borrowing)
    {
        $this->borrowing = $borrowing;
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
            ->subject('Peminjaman Disetujui - ' . $this->borrowing->borrowing_code)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Peminjaman Anda telah disetujui!')
            ->line('**Detail Peminjaman:**')
            ->line('Kode Peminjaman: ' . $this->borrowing->borrowing_code)
            ->line('Barang: ' . $this->borrowing->item->name)
            ->line('Jumlah: ' . $this->borrowing->quantity)
            ->line('Tanggal Pinjam: ' . $this->borrowing->borrow_date->format('d/m/Y'))
            ->line('Tanggal Jatuh Tempo: ' . $this->borrowing->due_date->format('d/m/Y'))
            ->action('Lihat Detail', url('/borrowings/' . $this->borrowing->id))
            ->line('Terima kasih telah menggunakan sistem kami!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'borrowing_approved',
            'borrowing_id' => $this->borrowing->id,
            'borrowing_code' => $this->borrowing->borrowing_code,
            'item_name' => $this->borrowing->item->name,
            'quantity' => $this->borrowing->quantity,
            'due_date' => $this->borrowing->due_date->format('Y-m-d'),
            'message' => 'Peminjaman Anda untuk ' . $this->borrowing->item->name . ' telah disetujui.',
        ];
    }
}
