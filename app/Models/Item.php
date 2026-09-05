<?php

namespace App\Models;

use App\Enums\ItemCondition;
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
        'condition' => ItemCondition::class,
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
        $conditionValue = $this->condition instanceof ItemCondition ? $this->condition->value : (string) $this->condition;
        return $this->available_stock >= $quantity && $conditionValue === ItemCondition::Baik->value;
    }

    /**
     * Scope items in good condition.
     */
    public function scopeGood($query)
    {
        return $query->where('condition', ItemCondition::Baik->value);
    }

    /**
     * Scope items in damaged condition.
     */
    public function scopeDamaged($query)
    {
        return $query->where('condition', ItemCondition::Rusak->value);
    }

    /**
     * Scope items in lost condition.
     */
    public function scopeLost($query)
    {
        return $query->where('condition', ItemCondition::Hilang->value);
    }

    /**
     * Update stock after borrowing
     */
    public function decreaseStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }

        if ($this->available_stock < $quantity) {
            throw new \InvalidArgumentException("Cannot decrease stock below zero. Current stock: {$this->available_stock}, requested: {$quantity}");
        }

        $this->available_stock -= $quantity;
        $this->save();
    }

    /**
     * Update stock after returning
     */
    public function increaseStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }

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
