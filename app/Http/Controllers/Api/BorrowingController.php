<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BorrowingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBorrowingRequest;
use App\Http\Requests\UpdateBorrowingRequest;
use App\Models\Borrowing;
use App\Services\BorrowingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BorrowingController extends Controller
{
    protected BorrowingService $borrowingService;

    public function __construct(BorrowingService $borrowingService)
    {
        $this->borrowingService = $borrowingService;
    }

    /**
     * Display a listing of borrowings
     */
    public function index(Request $request)
    {
        $query = Borrowing::with(['user', 'item', 'approver']);

        // Search by code, user name, or item name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('borrow_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('item', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by multiple statuses
        if ($request->has('statuses') && is_array($request->statuses)) {
            $query->whereIn('status', $request->statuses);
        }
        // Single status filter (backward compatibility)
        elseif ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user (for staff to see their own borrowings)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by item
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        // Filter by borrow date range
        if ($request->has('borrow_start') && $request->has('borrow_end')) {
            $query->whereBetween('borrow_date', [$request->borrow_start, $request->borrow_end]);
        }

        // Filter by due date range
        if ($request->has('due_start') && $request->has('due_end')) {
            $query->whereBetween('due_date', [$request->due_start, $request->due_end]);
        }

        // Filter overdue only
        if ($request->has('overdue') && ($request->overdue === 'true' || $request->overdue === true || $request->overdue === '1' || $request->overdue === 1)) {
            $query->where(function ($q) {
                $q->where('status', 'terlambat')
                  ->orWhere(function ($sub) {
                      $sub->where('status', 'dipinjam')
                          ->where('due_date', '<', Carbon::today());
                  });
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['borrow_date', 'due_date', 'return_date', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        // Batch update overdue status atomically via service
        $this->borrowingService->checkOverdueBorrowings(dispatchNotifications: false);

        $borrowings = $query->paginate($request->per_page ?? 15);

        return response()->json($borrowings);
    }

    /**
     * Store a newly created borrowing
     */
    public function store(StoreBorrowingRequest $request)
    {
        try {
            $borrowing = $this->borrowingService->createBorrowing($request->validated(), $request->user()->id);

            return response()->json([
                'message' => 'Borrowing created successfully',
                'data' => $borrowing,
            ], 201);
        } catch (BorrowingException $e) {
            return response()->json(array_merge([
                'message' => $e->getMessage(),
            ], $e->getExtraData()), $e->getStatusCode());
        }
    }

    /**
     * Display the specified borrowing
     */
    public function show(Borrowing $borrowing)
    {
        $borrowing->load(['user', 'item.category', 'approver']);
        $borrowing->updateOverdueStatus();

        return response()->json([
            'data' => $borrowing,
        ]);
    }

    /**
     * Return borrowed item
     */
    public function return(Request $request, Borrowing $borrowing)
    {
        try {
            $returned = $this->borrowingService->returnBorrowing($borrowing, $request->input('return_date'));

            return response()->json([
                'message' => 'Item returned successfully',
                'data' => $returned,
            ], 200);
        } catch (BorrowingException $e) {
            return response()->json(array_merge([
                'message' => $e->getMessage(),
            ], $e->getExtraData()), $e->getStatusCode());
        }
    }

    /**
     * Approve borrowing (admin only)
     */
    public function approve(Request $request, Borrowing $borrowing)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        try {
            $approved = $this->borrowingService->approveBorrowing($borrowing, $request->user()->id);

            return response()->json([
                'message' => 'Borrowing approved successfully',
                'data' => $approved,
            ], 200);
        } catch (BorrowingException $e) {
            return response()->json(array_merge([
                'message' => $e->getMessage(),
            ], $e->getExtraData()), $e->getStatusCode());
        }
    }

    /**
     * Reject borrowing (admin only)
     */
    public function reject(Request $request, Borrowing $borrowing)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        try {
            $rejected = $this->borrowingService->rejectBorrowing($borrowing);

            return response()->json([
                'message' => 'Borrowing rejected successfully',
                'data' => $rejected,
            ], 200);
        } catch (BorrowingException $e) {
            return response()->json(array_merge([
                'message' => $e->getMessage(),
            ], $e->getExtraData()), $e->getStatusCode());
        }
    }

    /**
     * Update the specified borrowing
     */
    public function update(UpdateBorrowingRequest $request, Borrowing $borrowing)
    {
        if ($borrowing->status === 'dikembalikan') {
            return response()->json([
                'message' => 'Cannot update returned borrowing',
            ], 422);
        }

        $borrowing->update($request->validated());
        $borrowing->load(['user', 'item', 'approver']);

        return response()->json([
            'message' => 'Borrowing updated successfully',
            'data' => $borrowing,
        ]);
    }

    /**
     * Remove the specified borrowing
     */
    public function destroy(Borrowing $borrowing)
    {
        try {
            $this->borrowingService->deleteBorrowing($borrowing);

            return response()->json([
                'message' => 'Borrowing deleted successfully',
            ]);
        } catch (BorrowingException $e) {
            return response()->json(array_merge([
                'message' => $e->getMessage(),
            ], $e->getExtraData()), $e->getStatusCode());
        }
    }

    /**
     * Extend borrowing due date
     */
    public function extend(Request $request, Borrowing $borrowing)
    {
        $validated = $request->validate([
            'new_due_date' => 'required|date|after:due_date',
        ]);

        try {
            $extended = $this->borrowingService->extendBorrowing($borrowing, $validated['new_due_date']);

            return response()->json([
                'message' => 'Borrowing extended successfully',
                'data' => $extended,
            ]);
        } catch (BorrowingException $e) {
            return response()->json(array_merge([
                'message' => $e->getMessage(),
            ], $e->getExtraData()), $e->getStatusCode());
        }
    }

    /**
     * Get current user's borrowings
     */
    public function myBorrowings(Request $request)
    {
        $query = Borrowing::with(['item.category'])
            ->where('user_id', $request->user()->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $borrowings = $query->latest()->paginate($request->per_page ?? 15);

        return response()->json($borrowings);
    }
}
