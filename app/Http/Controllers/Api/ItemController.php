<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    /**
     * Display a listing of items
     */
    public function index(Request $request)
    {
        $query = Item::with('category');

        // Search - improved with multiple field search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by multiple categories
        if ($request->has('categories') && is_array($request->categories)) {
            $query->whereIn('category_id', $request->categories);
        }
        // Single category filter (backward compatibility)
        elseif ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by multiple conditions
        if ($request->has('conditions') && is_array($request->conditions)) {
            $query->whereIn('condition', $request->conditions);
        }
        // Single condition filter (backward compatibility)
        elseif ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        // Filter by stock range
        if ($request->has('stock_min')) {
            $query->where('stock', '>=', $request->stock_min);
        }
        if ($request->has('stock_max')) {
            $query->where('stock', '<=', $request->stock_max);
        }

        // Filter by availability
        if ($request->has('available') && $request->available === 'true') {
            $query->where('available_stock', '>', 0);
        }

        // Low stock filter
        if ($request->has('low_stock') && $request->low_stock === 'true') {
            $query->whereRaw('available_stock <= stock * 0.2')->where('available_stock', '>', 0);
        }

        // Out of stock filter
        if ($request->has('out_of_stock') && $request->out_of_stock === 'true') {
            $query->where('available_stock', '=', 0);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['name', 'stock', 'available_stock', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        $items = $query->paginate($request->per_page ?? 15);

        return response()->json($items);
    }

    /**
     * Store a newly created item
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
            'condition' => 'required|in:baik,rusak,hilang',
        ]);

        // Generate unique code
        $validated['code'] = Item::generateCode();
        $validated['available_stock'] = $validated['stock'];

        // Handle image upload
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('items', 'public');
        }

        $item = Item::create($validated);
        $item->load('category');

        return response()->json([
            'message' => 'Item created successfully',
            'data' => $item,
        ], 201);
    }

    /**
     * Display the specified item
     */
    public function show(Item $item)
    {
        $item->load(['category', 'activeBorrowings.user']);

        return response()->json([
            'data' => $item,
        ]);
    }

    /**
     * Update the specified item
     */
    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'sometimes|exists:categories,id',
            'stock' => 'sometimes|integer|min:0',
            'image' => 'nullable|image|max:2048',
            'condition' => 'sometimes|in:baik,rusak,hilang',
        ]);

        // Update available stock if total stock changes
        if (isset($validated['stock'])) {
            $difference = $validated['stock'] - $item->stock;
            $validated['available_stock'] = $item->available_stock + $difference;
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($item->image) {
                Storage::disk('public')->delete($item->image);
            }
            $validated['image'] = $request->file('image')->store('items', 'public');
        }

        $item->update($validated);
        $item->load('category');

        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $item,
        ]);
    }

    /**
     * Remove the specified item
     */
    public function destroy(Item $item)
    {
        // Check if item has active borrowings
        if ($item->activeBorrowings()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete item with active borrowings',
            ], 422);
        }

        // Delete image
        if ($item->image) {
            Storage::disk('public')->delete($item->image);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }
}
