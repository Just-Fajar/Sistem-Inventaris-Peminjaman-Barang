<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
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
        $isAll = $request->has('all');
        $perPage = $request->per_page ?? 15;
        $cacheKey = 'categories_' . ($isAll ? 'all' : 'paginated_' . $perPage);
        
        $fetchCategories = function () use ($request, $isAll, $perPage) {
            $query = Category::withCount('items');
            
            if ($request->has('search')) {
                $query->where('name', 'like', "%{$request->search}%");
            }
            
            return $isAll 
                ? $query->get() 
                : $query->paginate($perPage);
        };

        if ($request->has('search')) {
            // Jangan cache hasil search
            $categories = $fetchCategories();
        } else {
            $categories = Cache::remember($cacheKey, 3600, $fetchCategories);
        }

        if ($isAll) {
            return CategoryResource::collection($categories);
        }

        $categories->through(fn ($category) => new CategoryResource($category));

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

        return (new CategoryResource($category))
            ->additional(['message' => 'Category created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified category
     */
    public function show(Category $category)
    {
        $category->load('items');

        return new CategoryResource($category);
    }

    /**
     * Update the specified category
     * 
     * @param Category $category
     */
    public function update(Request $request, Category $category)
    {
        /** @var Category&\stdClass $categoryWithId */
        $categoryWithId = $category;
        /** @var int $categoryId */
        $categoryId = (int) $categoryWithId->id;
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $categoryId,
            'description' => 'nullable|string',
        ]);

        $category->update($validated);
        
        // Clear cache setelah update
        Cache::forget('categories_all');
        Cache::forget('categories_paginated_15');

        return (new CategoryResource($category))
            ->additional(['message' => 'Category updated successfully'])
            ->response()
            ->setStatusCode(200);
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

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
