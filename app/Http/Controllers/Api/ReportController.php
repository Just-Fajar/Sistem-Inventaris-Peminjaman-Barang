<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BorrowingsExport;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get borrowing report with filters
     */
    public function borrowings(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:dipinjam,dikembalikan,terlambat',
            'user_id' => 'nullable|exists:users,id',
            'item_id' => 'nullable|exists:items,id',
        ]);

        $query = Borrowing::with(['user', 'item.category', 'approver']);

        // Date range filter
        if ($request->has('start_date')) {
            $query->whereDate('borrow_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('borrow_date', '<=', $request->end_date);
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // User filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Item filter
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        $borrowings = $query->orderBy('borrow_date', 'desc')->get();

        // Statistics
        $statistics = [
            'total_borrowings' => $borrowings->count(),
            'active_borrowings' => $borrowings->where('status', 'dipinjam')->count(),
            'returned_borrowings' => $borrowings->where('status', 'dikembalikan')->count(),
            'overdue_borrowings' => $borrowings->where('status', 'terlambat')->count(),
            'total_items_borrowed' => $borrowings->sum('quantity'),
        ];

        return response()->json([
            'data' => $borrowings,
            'statistics' => $statistics,
            'filters' => $request->only(['start_date', 'end_date', 'status', 'user_id', 'item_id']),
        ]);
    }

    /**
     * Get items report
     */
    public function items(Request $request)
    {
        $items = \App\Models\Item::with(['category', 'borrowings'])
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
            'total_stock' => $items->sum('stock'),
            'available_stock' => $items->sum('available_stock'),
        ];

        return response()->json([
            'data' => $items,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Get overdue borrowings report
     */
    public function overdue(Request $request)
    {
        $overdueBorrowings = Borrowing::with(['user', 'item', 'approver'])
            ->where('status', 'terlambat')
            ->orWhere(function ($query) {
                $query->where('status', 'dipinjam')
                      ->where('due_date', '<', Carbon::now());
            })
            ->orderBy('due_date', 'asc')
            ->get();

        // Update overdue status
        $overdueBorrowings->each(function ($borrowing) {
            $borrowing->updateOverdueStatus();
        });

        return response()->json([
            'data' => $overdueBorrowings,
            'total' => $overdueBorrowings->count(),
        ]);
    }

    /**
     * Get monthly summary
     */
    public function monthly(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

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
            'total_items_borrowed' => $borrowings->sum('quantity'),
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
                'quantity' => $dailyBorrowings->sum('quantity'),
            ];
        }

        return response()->json([
            'period' => [
                'year' => $year,
                'month' => $month,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'statistics' => $statistics,
            'daily_breakdown' => $dailyBreakdown,
            'borrowings' => $borrowings,
        ]);
    }

    /**
     * Export borrowings report to PDF
     */
    public function exportBorrowingsPdf(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:dipinjam,dikembalikan,terlambat',
        ]);

        $query = Borrowing::with(['user', 'item.category', 'approver']);

        if ($request->has('start_date')) {
            $query->whereDate('borrow_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('borrow_date', '<=', $request->end_date);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $borrowings = $query->orderBy('borrow_date', 'desc')->get();

        $statistics = [
            'total' => $borrowings->count(),
            'active' => $borrowings->where('status', 'dipinjam')->count(),
            'returned' => $borrowings->where('status', 'dikembalikan')->count(),
            'overdue' => $borrowings->where('status', 'terlambat')->count(),
        ];

        $pdf = Pdf::loadView('reports.borrowings-pdf', [
            'borrowings' => $borrowings,
            'statistics' => $statistics,
            'filters' => $request->only(['start_date', 'end_date', 'status']),
            'generated_at' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download('laporan-peminjaman-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export borrowings report to Excel
     */
    public function exportBorrowingsExcel(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:dipinjam,dikembalikan,terlambat',
        ]);

        return Excel::download(
            new BorrowingsExport($request->all()),
            'laporan-peminjaman-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
