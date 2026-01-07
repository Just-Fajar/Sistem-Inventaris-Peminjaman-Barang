<?php

namespace App\Services;

use App\Jobs\SendBorrowingNotification;
use App\Jobs\SendOverdueNotification;
use App\Models\Borrowing;
use App\Models\Item;
use App\Notifications\BorrowingApprovedNotification;
use Carbon\Carbon;

class BorrowingService
{
    protected $itemService;

    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * Generate unique borrowing code
     */
    public function generateBorrowingCode(): string
    {
        $date = now()->format('Ymd');
        $lastBorrowing = Borrowing::whereDate('created_at', today())
            ->latest('id')
            ->first();
        
        $sequence = $lastBorrowing ? ((int) substr($lastBorrowing->code, -4)) + 1 : 1;
        
        return "BRW-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new borrowing
     */
    public function createBorrowing(array $data, int $userId): Borrowing
    {
        $data['code'] = $this->generateBorrowingCode();
        $data['user_id'] = $userId;
        $data['status'] = 'pending';

        $item = Item::findOrFail($data['item_id']);
        
        // Check stock availability
        if ($item->available_stock < $data['quantity']) {
            throw new \Exception("Stok tidak mencukupi. Stok tersedia: {$item->available_stock}");
        }

        $borrowing = Borrowing::create($data);

        return $borrowing;
    }

    /**
     * Approve a borrowing request
     */
    public function approveBorrowing(Borrowing $borrowing, int $approverId): Borrowing
    {
        if ($borrowing->status !== 'pending') {
            throw new \Exception('Peminjaman sudah diproses');
        }

        $item = $borrowing->item;
        
        // Decrease available stock
        $this->itemService->decreaseStock($item, $borrowing->quantity);

        $borrowing->update([
            'status' => 'dipinjam',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        // Dispatch queue job for notification
        SendBorrowingNotification::dispatch($borrowing->fresh(), 'approved');

        return $borrowing->fresh();
    }

    /**
     * Return a borrowed item
     */
    public function returnBorrowing(Borrowing $borrowing, ?string $returnDate = null): Borrowing
    {
        if ($borrowing->status !== 'dipinjam') {
            throw new \Exception('Status peminjaman tidak valid untuk pengembalian');
        }

        $returnDate = $returnDate ? Carbon::parse($returnDate) : now();
        $item = $borrowing->item;

        // Increase available stock
        $this->itemService->increaseStock($item, $borrowing->quantity);

        // Check if overdue
        $status = 'dikembalikan';
        if ($returnDate->isAfter($borrowing->due_date)) {
            $status = 'terlambat';
        }

        $borrowing->update([
            'return_date' => $returnDate,
            'status' => $status,
        ]);

        return $borrowing->fresh();
    }

    /**
     * Cancel a pending borrowing
     */
    public function cancelBorrowing(Borrowing $borrowing): Borrowing
    {
        if ($borrowing->status !== 'pending') {
            throw new \Exception('Hanya peminjaman dengan status pending yang dapat dibatalkan');
        }

        $borrowing->delete();

        return $borrowing;
    }

    /**
     * Extend borrowing due date
     */
    public function extendBorrowing(Borrowing $borrowing, string $newDueDate): Borrowing
    {
        if ($borrowing->status !== 'dipinjam') {
            throw new \Exception('Hanya peminjaman aktif yang dapat diperpanjang');
        }

        $newDate = Carbon::parse($newDueDate);
        
        if ($newDate->isBefore($borrowing->due_date)) {
            throw new \Exception('Tanggal perpanjangan harus setelah tanggal jatuh tempo saat ini');
        }

        $borrowing->update([
            'due_date' => $newDate,
        ]);

        return $borrowing->fresh();
    }

    /**
     * Check and update overdue borrowings
     */
    public function checkOverdueBorrowings(): int
    {
        $overdueBorrowings = Borrowing::where('status', 'dipinjam')
            ->whereDate('due_date', '<', now())
            ->get();

        foreach ($overdueBorrowings as $borrowing) {
            $borrowing->update(['status' => 'terlambat']);
            
            // Dispatch overdue notification job
            SendOverdueNotification::dispatch($borrowing);
        }

        return $overdueBorrowings->count();
    }
}
