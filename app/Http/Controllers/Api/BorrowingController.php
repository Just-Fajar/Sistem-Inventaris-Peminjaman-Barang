<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Models\Item;
use Illuminate\Http\Request;

class BorrowingController extends Controller
{
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
                $q->where('code', 'like', "%{$search}%")
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
        if ($request->has('overdue') && $request->overdue === 'true') {
            $query->where('status', 'dipinjam')
                  ->where('due_date', '<', now());
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

        // Update overdue status
        $query->get()->each(function ($borrowing) {
            $borrowing->updateOverdueStatus();
        });

        $borrowings = $query->paginate($request->per_page ?? 15);

        return response()->json($borrowings);
    }

    /**
     * Store a newly created borrowing
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
            'borrow_date' => 'required|date',
            'due_date' => 'required|date|after:borrow_date',
            'notes' => 'nullable|string',
        ]);

        $item = Item::findOrFail($validated['item_id']);

        // Check if item is available
        if (!$item->isAvailable($validated['quantity'])) {
            return response()->json([
                'message' => 'Item is not available in requested quantity',
                'available_stock' => $item->available_stock,
            ], 422);
        }

        // Generate unique code
        $validated['borrow_code'] = Borrowing::generateCode();
        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'dipinjam';

        // Create borrowing
        $borrowing = Borrowing::create($validated);

        // Update item stock
        $item->decreaseStock($validated['quantity']);

        $borrowing->load(['user', 'item', 'approver']);

        return response()->json([
            'message' => 'Borrowing created successfully',
            'data' => $borrowing,
        ], 201);
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
        if ($borrowing->status === 'dikembalikan') {
            return response()->json([
                'message' => 'Item already returned',
            ], 422);
        }

        $success = $borrowing->processReturn();

        if (!$success) {
            return response()->json([
                'message' => 'Failed to process return',
            ], 500);
        }

        $borrowing->load(['user', 'item', 'approver']);

        return response()->json([
            'message' => 'Item returned successfully',
            'data' => $borrowing,
        ]);
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

        $borrowing->approved_by = $request->user()->id;
        $borrowing->save();

        $borrowing->load(['user', 'item', 'approver']);

        return response()->json([
            'message' => 'Borrowing approved successfully',
            'data' => $borrowing,
        ]);
    }

    /**
     * Update the specified borrowing
     */
    public function update(Request $request, Borrowing $borrowing)
    {
        if ($borrowing->status === 'dikembalikan') {
            return response()->json([
                'message' => 'Cannot update returned borrowing',
            ], 422);
        }

        $validated = $request->validate([
            'due_date' => 'sometimes|date|after:borrow_date',
            'notes' => 'nullable|string',
        ]);

        $borrowing->update($validated);
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
        // Only allow deletion if not yet borrowed or admin
        if ($borrowing->status === 'dipinjam' || $borrowing->status === 'terlambat') {
            return response()->json([
                'message' => 'Cannot delete active borrowing. Please return the item first.',
            ], 422);
        }

        $borrowing->delete();

        return response()->json([
            'message' => 'Borrowing deleted successfully',
        ]);
    }

    /**
     * Extend borrowing due date
     */
    public function extend(Request $request, Borrowing $borrowing)
    {
        $validated = $request->validate([
            'new_due_date' => 'required|date|after:due_date',
        ]);

        if ($borrowing->status !== 'dipinjam') {
            return response()->json([
                'message' => 'Only active borrowings can be extended',
            ], 422);
        }

        $borrowing->update(['due_date' => $validated['new_due_date']]);
        $borrowing->load(['user', 'item', 'approver']);

        return response()->json([
            'message' => 'Borrowing extended successfully',
            'data' => $borrowing,
        ]);
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

