<?php

namespace App\Services;

use App\Models\Borrowing;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Get borrowing report with filters and statistics
     */
    public function getBorrowingReport(array $filters = []): array
    {
        $query = Borrowing::with(['user', 'item.category', 'approver']);

        // Date range filter
        if (!empty($filters['start_date'])) {
            $query->whereDate('borrow_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('borrow_date', '<=', $filters['end_date']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // User filter
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Item filter
        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }

        $borrowings = $query->orderBy('borrow_date', 'desc')->get();

        // Statistics
        $statistics = [
            'total_borrowings' => $borrowings->count(),
            'active_borrowings' => $borrowings->where('status', 'dipinjam')->count(),
            'returned_borrowings' => $borrowings->where('status', 'dikembalikan')->count(),
            'overdue_borrowings' => $borrowings->where('status', 'terlambat')->count(),
            'total_items_borrowed' => (int) $borrowings->sum('quantity'),
            'total' => $borrowings->count(),
            'active' => $borrowings->where('status', 'dipinjam')->count(),
            'returned' => $borrowings->where('status', 'dikembalikan')->count(),
            'overdue' => $borrowings->where('status', 'terlambat')->count(),
        ];

        return [
            'data' => $borrowings,
            'statistics' => $statistics,
            'filters' => $filters,
        ];
    }

    /**
     * Get items report with counts and aggregated stock statistics
     */
    public function getItemReport(): array
    {
        $items = Item::with(['category', 'borrowings'])
            ->withCount([
                'borrowings',
                'borrowings as active_borrowings_count' => function ($query) {
                    $query->where('status', 'dipinjam');
                },
            ])
            ->get();

        $statistics = [
            'total_items' => $items->count(),
            'available_items' => $items->where('available_stock', '>', 0)->count(),
            'out_of_stock' => $items->where('available_stock', 0)->count(),
            'total_stock' => (int) $items->sum('stock'),
            'available_stock' => (int) $items->sum('available_stock'),
        ];

        return [
            'data' => $items,
            'statistics' => $statistics,
        ];
    }

    /**
     * Get overdue borrowings report (with atomic batch status update)
     */
    public function getOverdueReport(): array
    {
        // 1. Batch update overdue borrowings in a single atomic query (eliminating N+1)
        Borrowing::where('status', 'dipinjam')
            ->where('due_date', '<', Carbon::today())
            ->update(['status' => 'terlambat']);

        // 2. Fetch all overdue borrowings with relations in a single eager loaded query
        $overdueBorrowings = Borrowing::with(['user', 'item', 'approver'])
            ->where('status', 'terlambat')
            ->orWhere(function ($query) {
                $query->where('status', 'dipinjam')
                      ->where('due_date', '<', Carbon::today());
            })
            ->orderBy('due_date', 'asc')
            ->get();

        return [
            'data' => $overdueBorrowings,
            'total' => $overdueBorrowings->count(),
        ];
    }

    /**
     * Get monthly borrowings summary and daily breakdown
     */
    public function getMonthlyReport(int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $borrowings = Borrowing::with(['user', 'item'])
            ->whereBetween('borrow_date', [$startDate, $endDate])
            ->get();

        $statistics = [
            'total_borrowings' => $borrowings->count(),
            'active_borrowings' => $borrowings->where('status', 'dipinjam')->count(),
            'returned_borrowings' => $borrowings->where('status', 'dikembalikan')->count(),
            'overdue_borrowings' => $borrowings->where('status', 'terlambat')->count(),
            'total_items_borrowed' => (int) $borrowings->sum('quantity'),
        ];

        // Daily breakdown
        $dailyBreakdown = [];
        for ($day = 1; $day <= $endDate->day; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dailyBorrowings = $borrowings->filter(function ($borrowing) use ($date) {
                /** @var \Carbon\Carbon $borrowDate */
                $borrowDate = $borrowing->borrow_date;
                return $borrowDate->format('Y-m-d') === $date->format('Y-m-d');
            });

            $dailyBreakdown[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $dailyBorrowings->count(),
                'quantity' => (int) $dailyBorrowings->sum('quantity'),
            ];
        }

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'statistics' => $statistics,
            'daily_breakdown' => $dailyBreakdown,
            'borrowings' => $borrowings,
        ];
    }

    /**
     * Get borrowing statistics
     */
    public function getBorrowingStats(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Borrowing::query();

        if ($startDate && $endDate) {
            $query->whereBetween('borrow_date', [$startDate, $endDate]);
        }

        $total = $query->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $active = (clone $query)->where('status', 'dipinjam')->count();
        $returned = (clone $query)->where('status', 'dikembalikan')->count();
        $overdue = (clone $query)->where('status', 'terlambat')->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'active' => $active,
            'returned' => $returned,
            'overdue' => $overdue,
        ];
    }

    /**
     * Get borrowing trends by month
     */
    public function getBorrowingTrends(int $months = 6): array
    {
        $trends = Borrowing::select(
                DB::raw('DATE_FORMAT(borrow_date, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('borrow_date', '>=', now()->subMonths($months))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray();

        return $trends;
    }

    /**
     * Get item statistics
     */
    public function getItemStats(): array
    {
        $total = Item::count();
        $available = Item::where('available_stock', '>', 0)->count();
        $borrowed = Item::where('available_stock', '<', DB::raw('stock'))->count();
        $lowStock = Item::where('available_stock', '<=', 5)->count();

        $byCategory = Item::select('category_id', DB::raw('COUNT(*) as count'))
            ->with('category:id,name')
            ->groupBy('category_id')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category->name ?? 'Unknown',
                    'count' => $item->count,
                ];
            })
            ->toArray();

        $byCondition = Item::select('condition', DB::raw('COUNT(*) as count'))
            ->groupBy('condition')
            ->get()
            ->pluck('count', 'condition')
            ->toArray();

        return [
            'total' => $total,
            'available' => $available,
            'borrowed' => $borrowed,
            'low_stock' => $lowStock,
            'by_category' => $byCategory,
            'by_condition' => $byCondition,
        ];
    }

    /**
     * Get overdue borrowings
     */
    public function getOverdueBorrowings(): array
    {
        $borrowings = Borrowing::with(['user:id,name,email', 'item:id,name,code'])
            ->whereIn('status', ['dipinjam', 'terlambat'])
            ->whereDate('due_date', '<', now())
            ->orderBy('due_date')
            ->get()
            ->map(function ($borrowing) {
                /** @var \Carbon\Carbon $dueDate */
                $dueDate = $borrowing->due_date;
                
                return [
                    'id' => $borrowing->id,
                    'borrow_code' => $borrowing->borrow_code,
                    'code' => $borrowing->borrow_code,
                    'user' => $borrowing->user->name,
                    'user_email' => $borrowing->user->email,
                    'item' => $borrowing->item->name,
                    'item_code' => $borrowing->item->code,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'days_overdue' => now()->diffInDays($dueDate),
                    'quantity' => $borrowing->quantity,
                ];
            })
            ->toArray();

        return $borrowings;
    }

    /**
     * Get most borrowed items
     */
    public function getMostBorrowedItems(int $limit = 10): array
    {
        $items = Item::withCount('borrowings')
            ->with('category:id,name')
            ->orderBy('borrowings_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'category' => $item->category->name ?? 'Unknown',
                    'borrow_count' => $item->borrowings_count,
                    'current_stock' => $item->stock,
                    'available_stock' => $item->available_stock,
                ];
            })
            ->toArray();

        return $items;
    }

    /**
     * Get user borrowing history
     */
    public function getUserBorrowingHistory(int $userId): array
    {
        $stats = [
            'total' => Borrowing::where('user_id', $userId)->count(),
            'active' => Borrowing::where('user_id', $userId)->where('status', 'dipinjam')->count(),
            'returned' => Borrowing::where('user_id', $userId)->where('status', 'dikembalikan')->count(),
            'overdue' => Borrowing::where('user_id', $userId)->where('status', 'terlambat')->count(),
        ];

        $recentBorrowings = Borrowing::with(['item:id,name,code'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($borrowing) {
                /** @var \Carbon\Carbon $borrowDate */
                $borrowDate = $borrowing->borrow_date;
                /** @var \Carbon\Carbon $dueDate */
                $dueDate = $borrowing->due_date;
                
                return [
                    'id' => $borrowing->id,
                    'borrow_code' => $borrowing->borrow_code,
                    'code' => $borrowing->borrow_code,
                    'item' => $borrowing->item->name,
                    'quantity' => $borrowing->quantity,
                    'borrow_date' => $borrowDate->format('Y-m-d'),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'return_date' => $borrowing->return_date?->format('Y-m-d'),
                    'status' => $borrowing->status,
                ];
            })
            ->toArray();

        return [
            'stats' => $stats,
            'recent_borrowings' => $recentBorrowings,
        ];
    }
}
