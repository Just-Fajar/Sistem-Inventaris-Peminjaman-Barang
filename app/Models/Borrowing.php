<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Enums\BorrowingStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        'rejection_note',
        'approved_by',
        'approved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'borrow_date' => 'datetime',
        'due_date' => 'datetime',
        'return_date' => 'datetime',
        'approved_at' => 'datetime',
        'quantity' => 'integer',
        'status' => BorrowingStatus::class,
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'code',
    ];

    /**
     * Get code attribute alias for borrow_code.
     */
    public function getCodeAttribute(): ?string
    {
        return $this->borrow_code;
    }

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
        $statusValue = $this->status instanceof BorrowingStatus ? $this->status->value : (string) $this->status;
        if ($statusValue === BorrowingStatus::Dikembalikan->value) {
            return false;
        }

        return Carbon::now()->isAfter($this->due_date);
    }

    /**
     * Update status to overdue if needed
     */
    public function updateOverdueStatus(): void
    {
        $statusValue = $this->status instanceof BorrowingStatus ? $this->status->value : (string) $this->status;
        if ($this->isOverdue() && $statusValue === BorrowingStatus::Dipinjam->value) {
            $this->status = BorrowingStatus::Terlambat;
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
        $statusValue = $this->status instanceof BorrowingStatus ? $this->status->value : (string) $this->status;
        if ($statusValue === BorrowingStatus::Dikembalikan->value) {
            return false;
        }

        return DB::transaction(function () {
            // Re-check after acquiring transaction to prevent race conditions
            $this->refresh();
            $statusValue = $this->status instanceof BorrowingStatus ? $this->status->value : (string) $this->status;
            if ($statusValue === BorrowingStatus::Dikembalikan->value) {
                return false;
            }

            $item = Item::lockForUpdate()->find($this->item_id);

            $this->return_date = now();
            $this->status = BorrowingStatus::Dikembalikan;
            $this->save();

            // Update item stock
            if ($item) {
                $item->increaseStock($this->quantity);
            }

            return true;
        });
    }

    /**
     * Scope for active borrowings
     */
    public function scopeActive($query)
    {
        return $query->where('status', BorrowingStatus::Dipinjam->value);
    }

    /**
     * Scope for overdue borrowings
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', BorrowingStatus::Terlambat->value);
    }

    /**
     * Scope for returned borrowings
     */
    public function scopeReturned($query)
    {
        return $query->where('status', BorrowingStatus::Dikembalikan->value);
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
