<?php

namespace App\Services;

use App\Enums\BorrowingStatus;
use App\Exceptions\BorrowingException;
use App\Jobs\SendBorrowingNotification;
use App\Jobs\SendOverdueNotification;
use App\Models\Borrowing;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BorrowingService
{
    protected ItemService $itemService;

    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * Get string status value from model or string.
     */
    private function getStatusValue(Borrowing $borrowing): string
    {
        if ($borrowing->status instanceof BorrowingStatus) {
            return $borrowing->status->value;
        }

        return (string) $borrowing->status;
    }

    /**
     * Generate unique borrowing code.
     */
    public function generateBorrowingCode(): string
    {
        $date = now()->format('Ymd');
        $lastBorrowing = Borrowing::whereDate('created_at', today())
            ->latest('id')
            ->first();

        $sequence = $lastBorrowing ? ((int) substr($lastBorrowing->borrow_code, -4)) + 1 : 1;

        return "BRW-{$date}-" . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new borrowing.
     *
     * @throws BorrowingException
     */
    public function createBorrowing(array $data, int $userId): Borrowing
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var Item $item */
            $item = Item::lockForUpdate()->findOrFail($data['item_id']);

            // Check stock and condition availability
            if (!$item->isAvailable($data['quantity'])) {
                throw new BorrowingException(
                    'Item is not available in requested quantity',
                    422,
                    ['available_stock' => $item->available_stock]
                );
            }

            $data['borrow_code'] = $this->generateBorrowingCode();
            $data['user_id'] = $userId;
            $status = $data['status'] ?? BorrowingStatus::Pending->value;
            $data['status'] = $status;

            $borrowing = Borrowing::create($data);

            // Deduct stock only if not pending (pending requests preserve stock until admin approval)
            $statusValue = $status instanceof BorrowingStatus ? $status->value : (string) $status;
            if ($statusValue !== BorrowingStatus::Pending->value) {
                $this->itemService->decreaseStock($item, $data['quantity']);
            }

            $borrowing->load(['user', 'item', 'approver']);

            return $borrowing;
        });
    }

    /**
     * Approve a borrowing request (admin).
     *
     * @throws BorrowingException
     */
    public function approveBorrowing(Borrowing $borrowing, int $approverId): Borrowing
    {
        // Initial guard
        $status = $this->getStatusValue($borrowing);
        if ($borrowing->approved_by !== null || in_array($status, ['approved', 'dipinjam', 'dikembalikan', 'terlambat', 'ditolak', 'rejected'])) {
            throw new BorrowingException('Peminjaman sudah diproses atau sudah disetujui.', 400);
        }

        $approvedBorrowing = DB::transaction(function () use ($borrowing, $approverId) {
            /** @var Borrowing $lockedBorrowing */
            $lockedBorrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);

            $lockedStatus = $this->getStatusValue($lockedBorrowing);
            if ($lockedBorrowing->approved_by !== null || in_array($lockedStatus, ['approved', 'dipinjam', 'dikembalikan', 'terlambat', 'ditolak', 'rejected'])) {
                throw new BorrowingException('Peminjaman sudah diproses atau sudah disetujui.', 400);
            }

            /** @var Item $item */
            $item = Item::lockForUpdate()->findOrFail($lockedBorrowing->item_id);

            // If status is pending, deduct stock upon approval
            if ($lockedStatus === 'pending') {
                if (!$item->isAvailable($lockedBorrowing->quantity)) {
                    throw new BorrowingException(
                        'Stok barang tidak mencukupi untuk disetujui.',
                        422,
                        ['available_stock' => $item->available_stock]
                    );
                }

                $this->itemService->decreaseStock($item, $lockedBorrowing->quantity);
            }

            $lockedBorrowing->status = BorrowingStatus::Dipinjam;
            $lockedBorrowing->approved_by = $approverId;
            $lockedBorrowing->approved_at = now();
            $lockedBorrowing->save();

            $lockedBorrowing->load(['user', 'item', 'approver']);

            return $lockedBorrowing;
        });

        // Dispatch queue job for notification
        try {
            SendBorrowingNotification::dispatch($approvedBorrowing, 'approved');
        } catch (\Throwable $e) {
            logger()->warning('Failed to dispatch SendBorrowingNotification: ' . $e->getMessage());
        }

        return $approvedBorrowing;
    }

    /**
     * Reject a borrowing request (admin).
     *
     * @throws BorrowingException
     */
    public function rejectBorrowing(Borrowing $borrowing, ?string $rejectionNote = null): Borrowing
    {
        $status = $this->getStatusValue($borrowing);
        if ($borrowing->approved_by !== null || in_array($status, ['approved', 'dipinjam', 'dikembalikan', 'terlambat', 'ditolak', 'rejected'])) {
            throw new BorrowingException('Peminjaman sudah diproses.', 400);
        }

        return DB::transaction(function () use ($borrowing, $rejectionNote) {
            /** @var Borrowing $lockedBorrowing */
            $lockedBorrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);

            $lockedStatus = $this->getStatusValue($lockedBorrowing);
            if ($lockedBorrowing->approved_by !== null || in_array($lockedStatus, ['approved', 'dipinjam', 'dikembalikan', 'terlambat', 'ditolak', 'rejected'])) {
                throw new BorrowingException('Peminjaman sudah diproses.', 400);
            }

            $lockedBorrowing->status = BorrowingStatus::Rejected;
            $lockedBorrowing->rejection_note = $rejectionNote;
            $lockedBorrowing->save();

            $lockedBorrowing->load(['user', 'item', 'approver']);

            return $lockedBorrowing;
        });
    }

    /**
     * Return a borrowed item.
     *
     * @throws BorrowingException
     */
    public function returnBorrowing(Borrowing $borrowing, ?string $returnDate = null): Borrowing
    {
        $status = $this->getStatusValue($borrowing);
        if ($status === 'dikembalikan') {
            throw new BorrowingException('Item already returned', 422);
        }

        return DB::transaction(function () use ($borrowing, $returnDate) {
            /** @var Borrowing $lockedBorrowing */
            $lockedBorrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);

            $lockedStatus = $this->getStatusValue($lockedBorrowing);
            if ($lockedStatus === 'dikembalikan') {
                throw new BorrowingException('Item already returned', 422);
            }

            if ($lockedStatus !== 'dipinjam' && $lockedStatus !== 'terlambat') {
                throw new BorrowingException('Status peminjaman tidak valid untuk pengembalian', 422);
            }

            /** @var Item $item */
            $item = Item::lockForUpdate()->findOrFail($lockedBorrowing->item_id);

            $parsedReturnDate = $returnDate ? Carbon::parse($returnDate) : now();

            $lockedBorrowing->return_date = $parsedReturnDate;
            $lockedBorrowing->status = BorrowingStatus::Dikembalikan;
            $lockedBorrowing->save();

            // Increase available stock
            $this->itemService->increaseStock($item, $lockedBorrowing->quantity);

            $lockedBorrowing->load(['user', 'item', 'approver']);

            return $lockedBorrowing;
        });
    }

    /**
     * Cancel a pending borrowing.
     *
     * @throws BorrowingException
     */
    public function cancelBorrowing(Borrowing $borrowing): bool
    {
        return DB::transaction(function () use ($borrowing) {
            /** @var Borrowing $lockedBorrowing */
            $lockedBorrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);

            $status = $this->getStatusValue($lockedBorrowing);
            if ($status !== 'pending') {
                throw new BorrowingException('Hanya peminjaman dengan status pending yang dapat dibatalkan', 422);
            }

            return (bool) $lockedBorrowing->delete();
        });
    }

    /**
     * Delete borrowing record.
     *
     * @throws BorrowingException
     */
    public function deleteBorrowing(Borrowing $borrowing): bool
    {
        return DB::transaction(function () use ($borrowing) {
            /** @var Borrowing $lockedBorrowing */
            $lockedBorrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);

            $status = $this->getStatusValue($lockedBorrowing);
            if ($status === 'dipinjam' || $status === 'terlambat') {
                throw new BorrowingException('Cannot delete active borrowing. Please return the item first.', 422);
            }

            return (bool) $lockedBorrowing->delete();
        });
    }

    /**
     * Extend borrowing due date.
     *
     * @throws BorrowingException
     */
    public function extendBorrowing(Borrowing $borrowing, string $newDueDate): Borrowing
    {
        return DB::transaction(function () use ($borrowing, $newDueDate) {
            /** @var Borrowing $lockedBorrowing */
            $lockedBorrowing = Borrowing::lockForUpdate()->findOrFail($borrowing->id);

            $status = $this->getStatusValue($lockedBorrowing);
            if ($status !== 'dipinjam') {
                throw new BorrowingException('Only active borrowings can be extended', 422);
            }

            $newDate = Carbon::parse($newDueDate);

            if ($newDate->isBefore($lockedBorrowing->due_date) || $newDate->equalTo($lockedBorrowing->due_date)) {
                throw new BorrowingException('Tanggal perpanjangan harus setelah tanggal jatuh tempo saat ini', 422);
            }

            $lockedBorrowing->update([
                'due_date' => $newDate,
            ]);

            $lockedBorrowing->load(['user', 'item', 'approver']);

            return $lockedBorrowing->fresh();
        });
    }

    /**
     * Check and update overdue borrowings atomically.
     */
    public function checkOverdueBorrowings(bool $dispatchNotifications = true): int
    {
        if ($dispatchNotifications) {
            $overdueBorrowings = Borrowing::where('status', 'dipinjam')
                ->where('due_date', '<', Carbon::today())
                ->get();

            $count = Borrowing::where('status', 'dipinjam')
                ->where('due_date', '<', Carbon::today())
                ->update(['status' => 'terlambat']);

            foreach ($overdueBorrowings as $borrowing) {
                try {
                    SendOverdueNotification::dispatch($borrowing);
                } catch (\Throwable $e) {
                    logger()->warning('Failed to dispatch SendOverdueNotification: ' . $e->getMessage());
                }
            }

            return $count;
        }

        return Borrowing::where('status', 'dipinjam')
            ->where('due_date', '<', Carbon::today())
            ->update(['status' => 'terlambat']);
    }
}
