<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;

class Borrowing extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'borrow_code',
        'user_id',
        'item_id',
        'quantity',
        'borrow_date',
        'due_date',
        'return_date',
        'status',
        'notes',
        'approved_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'borrow_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
        'quantity' => 'integer',
    ];

    /**
     * Get the user that owns the borrowing
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the item that is borrowed
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the admin who approved the borrowing
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if borrowing is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'dikembalikan') {
            return false;
        }

        return Carbon::now()->isAfter($this->due_date);
    }

    /**
     * Update status to overdue if needed
     */
    public function updateOverdueStatus(): void
    {
        if ($this->isOverdue() && $this->status === 'dipinjam') {
            $this->status = 'terlambat';
            $this->save();
        }
    }

    /**
     * Generate unique borrow code
     */
    public static function generateCode(): string
    {
        $prefix = 'BRW';
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        $number = str_pad($count, 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$date}-{$number}";
    }

    /**
     * Process return of borrowed item
     */
    public function processReturn(): bool
    {
        if ($this->status === 'dikembalikan') {
            return false;
        }

        $this->return_date = now();
        $this->status = 'dikembalikan';
        $this->save();

        // Update item stock
        $this->item->increaseStock($this->quantity);

        return true;
    }

    /**
     * Scope for active borrowings
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'dipinjam');
    }

    /**
     * Scope for overdue borrowings
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'terlambat');
    }

    /**
     * Scope for returned borrowings
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'dikembalikan');
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'quantity', 'borrow_date', 'due_date', 'return_date', 'approved_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
