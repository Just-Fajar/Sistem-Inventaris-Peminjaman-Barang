<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(Request $request)
    {
        // Cache categories untuk 1 jam (3600 detik)
        $cacheKey = 'categories_' . ($request->has('all') ? 'all' : 'paginated_' . ($request->per_page ?? 15));
        
        if ($request->has('search')) {
            // Jangan cache hasil search
            $query = Category::withCount('items');
            $query->where('name', 'like', "%{$request->search}%");
            
            $categories = $request->has('all') 
                ? $query->get() 
                : $query->paginate($request->per_page ?? 15);
                
            return response()->json($categories);
        }
        
        $categories = Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = Category::withCount('items');
            
            return $request->has('all') 
                ? $query->get() 
                : $query->paginate($request->per_page ?? 15);
        });

        return response()->json($categories);
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($validated);
        
        // Clear cache setelah create
        Cache::forget('categories_all');
        Cache::forget('categories_paginated_15');

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified category
     */
    public function show(Category $category)
    {
        $category->load('items');

        return response()->json([
            'data' => $category,
        ]);
    }

    /**
     * Update the specified category
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $category->update($validated);
        
        // Clear cache setelah update
        Cache::forget('categories_all');
        Cache::forget('categories_paginated_15');

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    /**
     * Remove the specified category
     */
    public function destroy(Category $category)
    {
        // Check if category has items
        if ($category->items()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with items',
            ], 422);
        }

        $category->delete();
        
        // Clear cache setelah delete
        Cache::forget('categories_all');
        Cache::forget('categories_paginated_15');
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
