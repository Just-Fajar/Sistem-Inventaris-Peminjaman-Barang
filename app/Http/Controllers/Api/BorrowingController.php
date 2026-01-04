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

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user (for staff to see their own borrowings)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('borrow_date', [$request->start_date, $request->end_date]);
        }

        // Update overdue status
        $query->get()->each(function ($borrowing) {
            $borrowing->updateOverdueStatus();
        });

        $borrowings = $query->latest()->paginate($request->per_page ?? 15);

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
}
