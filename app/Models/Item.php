<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Item extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'category_id',
        'stock',
        'available_stock',
        'image',
        'condition',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'stock' => 'integer',
        'available_stock' => 'integer',
    ];

    /**
     * Get the category that owns the item
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get borrowings for this item
     */
    public function borrowings()
    {
        return $this->hasMany(Borrowing::class);
    }

    /**
     * Get active borrowings for this item
     */
    public function activeBorrowings()
    {
        return $this->hasMany(Borrowing::class)->where('status', 'dipinjam');
    }

    /**
     * Check if item is available for borrowing
     */
    public function isAvailable($quantity = 1): bool
    {
        return $this->available_stock >= $quantity && $this->condition === 'baik';
    }

    /**
     * Update stock after borrowing
     */
    public function decreaseStock(int $quantity): void
    {
        $this->available_stock -= $quantity;
        $this->save();
    }

    /**
     * Update stock after returning
     */
    public function increaseStock(int $quantity): void
    {
        $this->available_stock += $quantity;
        $this->save();
    }

    /**
     * Generate unique item code
     */
    public static function generateCode(): string
    {
        $prefix = 'ITM';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));
        
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'category_id', 'stock', 'available_stock', 'condition'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
