<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ItemException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use App\Services\ItemService;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    protected ItemService $itemService;

    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

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
    public function store(StoreItemRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image');
        }

        $item = $this->itemService->createItem($validated);

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
    public function update(UpdateItemRequest $request, Item $item)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image');
        }

        $updatedItem = $this->itemService->updateItem($item, $validated);

        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $updatedItem,
        ]);
    }

    /**
     * Remove the specified item
     */
    public function destroy(Item $item)
    {
        try {
            $this->itemService->deleteItem($item);

            return response()->json([
                'message' => 'Item deleted successfully',
            ]);
        } catch (ItemException $e) {
            return response()->json(array_merge([
                'message' => $e->getMessage(),
            ], $e->getExtraData()), $e->getStatusCode());
        }
    }

    /**
     * Bulk delete items
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:items,id',
        ]);

        try {
            $result = $this->itemService->bulkDeleteItems($validated['ids']);

            return response()->json($result);
        } catch (ItemException $e) {
            return response()->json(array_merge([
                'message' => $e->getMessage(),
            ], $e->getExtraData()), $e->getStatusCode());
        }
    }

    /**
     * Get search suggestions for autocomplete
     */
    public function searchSuggestions(Request $request)
    {
        $query = $request->input('q', '');
        $limit = $request->input('limit', 10);

        if (strlen($query) < 2) {
            return response()->json([
                'data' => [],
            ]);
        }

        $items = Item::where('name', 'like', "%{$query}%")
            ->orWhere('code', 'like', "%{$query}%")
            ->orWhereHas('category', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->with('category:id,name')
            ->select('id', 'code', 'name', 'category_id', 'available_stock')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'label' => $item->name . ' (' . $item->code . ')',
                    'value' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'category' => $item->category->name ?? null,
                    'available_stock' => $item->available_stock,
                ];
            });

        return response()->json([
            'data' => $items,
        ]);
    }
}
