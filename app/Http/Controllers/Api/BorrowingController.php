<?php

namespace App\Http\Controllers\Api;

use App\Enums\BorrowingStatus;
use App\Exceptions\BorrowingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBorrowingRequest;
use App\Http\Requests\UpdateBorrowingRequest;
use App\Http\Resources\BorrowingResource;
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
        $borrowings->through(fn ($borrowing) => new BorrowingResource($borrowing));

        return response()->json($borrowings);
    }

    /**
     * Store a newly created borrowing
     */
    public function store(StoreBorrowingRequest $request)
    {
        try {
            $borrowing = $this->borrowingService->createBorrowing($request->validated(), $request->user()->id);
            $borrowing->load(['user', 'item.category', 'approver']);

            return (new BorrowingResource($borrowing))
                ->additional(['message' => 'Borrowing created successfully'])
                ->response()
                ->setStatusCode(201);
        } catch (BorrowingException $e) {
            return response()->json(array_merge([
                'message' => $e->getMessage(),
            ], $e->getExtraData()), $e->getStatusCode());
        }
    }

    /**
     * Display the specified borrowing
     */
    public function show(Request $request, Borrowing $borrowing)
    {
        if ($request->user() && $request->user()->cannot('view', $borrowing)) {
            return response()->json([
                'message' => 'Unauthorized. You can only view your own borrowings.',
            ], 403);
        }

        $borrowing->load(['user', 'item.category', 'approver']);
        $borrowing->updateOverdueStatus();

        return new BorrowingResource($borrowing);
    }

    /**
     * Return borrowed item
     */
    public function return(Request $request, Borrowing $borrowing)
    {
        if ($request->user() && $request->user()->cannot('return', $borrowing)) {
            return response()->json([
                'message' => 'Unauthorized. You can only return your own borrowings.',
            ], 403);
        }

        try {
            $returned = $this->borrowingService->returnBorrowing($borrowing, $request->input('return_date'));
            $returned->load(['user', 'item.category', 'approver']);

            return (new BorrowingResource($returned))
                ->additional(['message' => 'Item returned successfully'])
                ->response()
                ->setStatusCode(200);
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
        if ($request->user()->cannot('approve', $borrowing)) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        try {
            $approved = $this->borrowingService->approveBorrowing($borrowing, $request->user()->id);
            $approved->load(['user', 'item.category', 'approver']);

            return (new BorrowingResource($approved))
                ->additional(['message' => 'Borrowing approved successfully'])
                ->response()
                ->setStatusCode(200);
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
        if ($request->user()->cannot('reject', $borrowing)) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        try {
            $rejected = $this->borrowingService->rejectBorrowing($borrowing);
            $rejected->load(['user', 'item.category', 'approver']);

            return (new BorrowingResource($rejected))
                ->additional(['message' => 'Borrowing rejected successfully'])
                ->response()
                ->setStatusCode(200);
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
        if ($request->user() && $request->user()->cannot('update', $borrowing)) {
            return response()->json([
                'message' => 'Unauthorized. You cannot update this borrowing.',
            ], 403);
        }

        $statusValue = $borrowing->status instanceof \App\Enums\BorrowingStatus ? $borrowing->status->value : (string) $borrowing->status;
        if ($statusValue === \App\Enums\BorrowingStatus::Dikembalikan->value) {
            return response()->json([
                'message' => 'Cannot update returned borrowing',
            ], 422);
        }

        $borrowing->update($request->validated());
        $borrowing->load(['user', 'item.category', 'approver']);

        return (new BorrowingResource($borrowing))
            ->additional(['message' => 'Borrowing updated successfully'])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Remove the specified borrowing
     */
    public function destroy(Request $request, Borrowing $borrowing)
    {
        if ($request->user() && $request->user()->cannot('delete', $borrowing)) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required to delete borrowings.',
            ], 403);
        }

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
        if ($request->user() && $request->user()->cannot('extend', $borrowing)) {
            return response()->json([
                'message' => 'Unauthorized. You can only extend your own borrowings.',
            ], 403);
        }

        $validated = $request->validate([
            'new_due_date' => 'required|date|after:due_date',
        ]);

        try {
            $extended = $this->borrowingService->extendBorrowing($borrowing, $validated['new_due_date']);
            $extended->load(['user', 'item.category', 'approver']);

            return (new BorrowingResource($extended))
                ->additional(['message' => 'Borrowing extended successfully'])
                ->response()
                ->setStatusCode(200);
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
        $query = Borrowing::with(['user', 'item.category', 'approver'])
            ->where('user_id', $request->user()->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $borrowings = $query->latest()->paginate($request->per_page ?? 15);
        $borrowings->through(fn ($borrowing) => new BorrowingResource($borrowing));

        return response()->json($borrowings);
    }
}
