<?php

namespace App\Enums;

enum ItemCondition: string
{
    case Baik = 'baik';
    case Rusak = 'rusak';
    case Hilang = 'hilang';

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
            self::Baik => 'Baik',
            self::Rusak => 'Rusak',
            self::Hilang => 'Hilang',
        };
    }

    /**
     * Get UI badge color theme.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Baik => 'success',
            self::Rusak => 'warning',
            self::Hilang => 'danger',
        };
    }

    /**
     * Determine if an item with this condition can be borrowed.
     */
    public function isBorrowable(): bool
    {
        return $this === self::Baik;
    }
}
