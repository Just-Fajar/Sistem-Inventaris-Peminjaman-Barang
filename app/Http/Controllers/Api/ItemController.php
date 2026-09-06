<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ItemException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
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
        $items = $this->itemService->getCachedItems($request->all());
        $items->through(fn ($item) => new ItemResource($item));

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
        $item->load('category');

        return (new ItemResource($item))
            ->additional(['message' => 'Item created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified item
     */
    public function show(Item $item)
    {
        $item->load(['category', 'activeBorrowings.user']);

        return new ItemResource($item);
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
        $updatedItem->load('category');

        return (new ItemResource($updatedItem))
            ->additional(['message' => 'Item updated successfully'])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Remove the specified item
     */
    public function destroy(Request $request, Item $item)
    {
        if ($request->user() && $request->user()->cannot('delete', $item)) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

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
        if ($request->user() && $request->user()->cannot('bulkDelete', Item::class)) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

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
