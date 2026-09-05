<?php

namespace App\Enums;

enum BorrowingStatus: string
{
    case Pending = 'pending';
    case Dipinjam = 'dipinjam';
    case Dikembalikan = 'dikembalikan';
    case Terlambat = 'terlambat';
    case Ditolak = 'ditolak';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get all raw string values of the enum cases.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get human-readable Indonesian label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::Dipinjam, self::Approved => 'Sedang Dipinjam',
            self::Dikembalikan => 'Dikembalikan',
            self::Terlambat => 'Terlambat',
            self::Ditolak, self::Rejected => 'Ditolak',
        };
    }

    /**
     * Get UI badge color theme.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Dipinjam, self::Approved => 'info',
            self::Dikembalikan => 'success',
            self::Terlambat => 'danger',
            self::Ditolak, self::Rejected => 'secondary',
        };
    }

    /**
     * Check if the borrowing is in an active state (item currently in user's possession).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Dipinjam, self::Approved, self::Terlambat], true);
    }

    /**
     * Check if the borrowing is in a finished / final state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Dikembalikan, self::Ditolak, self::Rejected], true);
    }

    /**
     * Check if the borrowing can be returned.
     */
    public function canBeReturned(): bool
    {
        return in_array($this, [self::Dipinjam, self::Approved, self::Terlambat], true);
    }

    /**
     * Check if the borrowing can have its due date extended.
     */
    public function canBeExtended(): bool
    {
        return in_array($this, [self::Dipinjam, self::Approved], true);
    }

    /**
     * Check if the borrowing can be approved or rejected by admin.
     */
    public function canBeActioned(): bool
    {
        return $this === self::Pending;
    }
}
