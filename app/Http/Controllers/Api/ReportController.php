<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BorrowingsExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Get borrowing report with filters
     */
    public function borrowings(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:dipinjam,dikembalikan,terlambat',
            'user_id' => 'nullable|exists:users,id',
            'item_id' => 'nullable|exists:items,id',
        ]);

        $filters = $request->only(['start_date', 'end_date', 'status', 'user_id', 'item_id']);
        $report = $this->reportService->getBorrowingReport($filters);

        return response()->json($report);
    }

    /**
     * Get items report
     */
    public function items(Request $request): JsonResponse
    {
        $report = $this->reportService->getItemReport();

        return response()->json($report);
    }

    /**
     * Get overdue borrowings report
     */
    public function overdue(Request $request): JsonResponse
    {
        $report = $this->reportService->getOverdueReport();

        return response()->json($report);
    }

    /**
     * Get monthly summary
     */
    public function monthly(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $year = (int) $request->input('year', Carbon::now()->year);
        $month = (int) $request->input('month', Carbon::now()->month);

        $report = $this->reportService->getMonthlyReport($year, $month);

        return response()->json($report);
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

        $report = $this->reportService->getBorrowingReport($request->all());

        $pdf = Pdf::loadView('reports.borrowings-pdf', [
            'borrowings' => $report['data'],
            'statistics' => $report['statistics'],
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
