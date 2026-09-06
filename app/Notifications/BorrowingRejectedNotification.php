<?php

namespace App\Notifications;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BorrowingRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Borrowing $borrowing)
    {
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
        /** @var \Carbon\Carbon|null $borrowDate */
        $borrowDate = $this->borrowing->borrow_date;
        $borrowDateFormatted = $borrowDate ? $borrowDate->format('d/m/Y') : '-';

        $mail = (new MailMessage)
            ->subject('Peminjaman Ditolak - ' . $this->borrowing->code)
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Mohon maaf, permohonan peminjaman barang Anda telah ditolak.')
            ->line('**Detail Peminjaman:**')
            ->line('Kode Peminjaman: ' . $this->borrowing->code)
            ->line('Barang: ' . ($this->borrowing->item?->name ?? 'Barang'))
            ->line('Jumlah: ' . $this->borrowing->quantity)
            ->line('Tanggal Pengajuan: ' . $borrowDateFormatted);

        if (!empty($this->borrowing->rejection_note)) {
            $mail->line('**Alasan Penolakan:** ' . $this->borrowing->rejection_note);
        }

        return $mail
            ->action('Lihat Detail', url('/borrowings/' . $borrowingWithId->id))
            ->line('Silakan hubungi administrator jika Anda memerlukan informasi lebih lanjut.');
    }

    /**
     * Get the array representation of the notification for database channel.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var \App\Models\Borrowing&\stdClass $borrowingWithId */
        $borrowingWithId = $this->borrowing;

        return [
            'type' => 'borrowing_rejected',
            'borrowing_id' => $borrowingWithId->id,
            'borrowing_code' => $this->borrowing->code,
            'item_name' => $this->borrowing->item?->name ?? 'Barang',
            'quantity' => $this->borrowing->quantity,
            'rejection_note' => $this->borrowing->rejection_note,
            'message' => 'Permohonan peminjaman Anda untuk ' . ($this->borrowing->item?->name ?? 'barang') . ' telah ditolak.',
        ];
    }
}
