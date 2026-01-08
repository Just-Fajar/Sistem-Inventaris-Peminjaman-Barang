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
        /** @var \App\Models\Borrowing&\stdClass $borrowingWithId */
        $borrowingWithId = $this->borrowing;
        /** @var \Carbon\Carbon $borrowDate */
        $borrowDate = $this->borrowing->borrow_date;
        /** @var \Carbon\Carbon $dueDate */
        $dueDate = $this->borrowing->due_date;
        
        return (new MailMessage)
            ->subject('Peminjaman Disetujui - ' . $this->borrowing->code)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Peminjaman Anda telah disetujui!')
            ->line('**Detail Peminjaman:**')
            ->line('Kode Peminjaman: ' . $this->borrowing->code)
            ->line('Barang: ' . $this->borrowing->item->name)
            ->line('Jumlah: ' . $this->borrowing->quantity)
            ->line('Tanggal Pinjam: ' . $borrowDate->format('d/m/Y'))
            ->line('Tanggal Jatuh Tempo: ' . $dueDate->format('d/m/Y'))
            ->action('Lihat Detail', url('/borrowings/' . $borrowingWithId->id))
            ->line('Terima kasih telah menggunakan sistem kami!');
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
            'type' => 'borrowing_approved',
            'borrowing_id' => $borrowingWithId->id,
            'borrowing_code' => $this->borrowing->code,
            'item_name' => $this->borrowing->item->name,
            'quantity' => $this->borrowing->quantity,
            'due_date' => $dueDate->format('Y-m-d'),
            'message' => 'Peminjaman Anda untuk ' . $this->borrowing->item->name . ' telah disetujui.',
        ];
    }
}
