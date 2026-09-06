<?php

namespace App\Services;

use App\Exceptions\ItemException;
use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ItemService
{
    /**
     * Generate unique item code.
     */
    public function generateItemCode(): string
    {
        return Item::generateCode();
    }

    /**
     * Create a new item.
     */
    public function createItem(array $data): Item
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = $this->generateItemCode();
            }

            if (!isset($data['available_stock'])) {
                $data['available_stock'] = $data['stock'] ?? 0;
            }

            $uploadedImage = null;
            if (isset($data['image']) && ($data['image'] instanceof UploadedFile || (is_string($data['image']) && file_exists($data['image'])))) {
                $uploadedImage = $this->handleImageUpload($data['image']);
                $data['image'] = $uploadedImage;
            }

            try {
                $item = Item::create($data);
            } catch (\Throwable $e) {
                if ($uploadedImage) {
                    Storage::disk('public')->delete($uploadedImage);
                }
                throw $e;
            }

            // Clear cache when item is created
            $this->clearItemsCache();

            return $item->fresh(['category']);
        });
    }

    /**
     * Update an existing item.
     */
    public function updateItem(Item $item, array $data): Item
    {
        return DB::transaction(function () use ($item, $data) {
            /** @var Item $lockedItem */
            $lockedItem = Item::lockForUpdate()->findOrFail($item->id);
            $oldImage = $lockedItem->image;
            $newImage = null;

            if (isset($data['image']) && ($data['image'] instanceof UploadedFile || (is_string($data['image']) && file_exists($data['image'])))) {
                $newImage = $this->handleImageUpload($data['image']);
                $data['image'] = $newImage;
            }

            // Update available stock if total stock changes
            if (isset($data['stock'])) {
                $difference = $data['stock'] - $lockedItem->stock;
                $data['available_stock'] = $lockedItem->available_stock + $difference;
            }

            try {
                $lockedItem->update($data);
            } catch (\Throwable $e) {
                if ($newImage) {
                    Storage::disk('public')->delete($newImage);
                }
                throw $e;
            }

            // Delete old image if a new image was successfully stored and DB updated
            if ($newImage && $oldImage && $newImage !== $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }

            // Clear cache when item is updated
            $this->clearItemsCache();

            return $lockedItem->fresh(['category']);
        });
    }

    /**
     * Delete an item.
     *
     * @throws ItemException
     */
    public function deleteItem(Item $item): bool
    {
        return DB::transaction(function () use ($item) {
            /** @var Item $lockedItem */
            $lockedItem = Item::lockForUpdate()->findOrFail($item->id);

            // Check if item has active borrowings (pending, dipinjam, terlambat)
            if ($lockedItem->borrowings()->whereIn('status', ['pending', 'dipinjam', 'terlambat'])->exists() || $lockedItem->activeBorrowings()->count() > 0) {
                throw new ItemException('Cannot delete item with active borrowings', 422);
            }

            $imageToDelete = $lockedItem->image;
            $deleted = (bool) $lockedItem->delete();

            if ($deleted && $imageToDelete) {
                Storage::disk('public')->delete($imageToDelete);
            }

            // Clear cache when item is deleted
            $this->clearItemsCache();

            return $deleted;
        });
    }

    /**
     * Bulk delete items.
     *
     * @throws ItemException
     */
    public function bulkDeleteItems(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            $items = Item::whereIn('id', $ids)->lockForUpdate()->get();

            $itemsWithBorrowings = [];
            foreach ($items as $item) {
                if ($item->borrowings()->whereIn('status', ['pending', 'dipinjam', 'terlambat'])->exists() || $item->activeBorrowings()->count() > 0) {
                    $itemsWithBorrowings[] = $item->name;
                }
            }

            if (!empty($itemsWithBorrowings)) {
                throw new ItemException('Some items have active borrowings and cannot be deleted', 422, [
                    'success' => false,
                    'items' => $itemsWithBorrowings,
                ]);
            }

            $imagesToDelete = [];
            $count = 0;
            foreach ($items as $item) {
                if ($item->image) {
                    $imagesToDelete[] = $item->image;
                }
                $item->delete();
                $count++;
            }

            // Delete storage images after DB deletions succeed within transaction
            foreach ($imagesToDelete as $image) {
                Storage::disk('public')->delete($image);
            }

            $this->clearItemsCache();

            return [
                'success' => true,
                'message' => $count . ' items deleted successfully',
                'count' => $count,
            ];
        });
    }

    /**
     * Handle image upload with optimization (max 800x800, JPEG 85%).
     */
    public function handleImageUpload(UploadedFile|string $image): string
    {
        $filename = Str::uuid() . '.jpg';
        $path = 'items/' . $filename;

        // Create image manager with GD driver
        $manager = new ImageManager(new Driver());

        // Read and process image
        $img = $manager->read($image);

        // Resize if larger than 800x800 while maintaining aspect ratio
        $img->scaleDown(width: 800, height: 800);

        // Encode to JPEG with 85% quality
        $encoded = $img->toJpeg(quality: 85);

        // Save to public storage
        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    /**
     * Update item stock when borrowed.
     */
    public function decreaseStock(Item $item, int $quantity): void
    {
        $item->decreaseStock($quantity);
    }

    /**
     * Update item stock when returned.
     */
    public function increaseStock(Item $item, int $quantity): void
    {
        $item->increaseStock($quantity);
    }

    /**
     * Clear items-related cache safely.
     */
    public function clearItemsCache(): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags(['items'])->flush();
            } else {
                Cache::flush();
            }
        } catch (\Throwable) {
            // Ignore if cache driver does not support tags or flush
        }
    }

    /**
     * Get items with caching and comprehensive filtering.
     */
    public function getCachedItems(array $filters = []): mixed
    {
        $normalizedFilters = $filters;
        ksort($normalizedFilters);
        $page = $filters['page'] ?? (function_exists('request') && request() ? request('page', 1) : 1);
        $cacheKey = 'items:' . md5(json_encode($normalizedFilters) . ':page:' . $page);

        $callback = function () use ($filters) {
            $query = Item::with('category');

            // Search by name, code, description, or category name
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('category', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
                });
            }

            // Filter by multiple or single category
            if (!empty($filters['categories']) && is_array($filters['categories'])) {
                $query->whereIn('category_id', $filters['categories']);
            } elseif (!empty($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }

            // Filter by multiple or single condition
            if (!empty($filters['conditions']) && is_array($filters['conditions'])) {
                $query->whereIn('condition', $filters['conditions']);
            } elseif (!empty($filters['condition'])) {
                $query->where('condition', $filters['condition']);
            }

            // Stock range filtering
            if (isset($filters['stock_min']) || isset($filters['min_stock'])) {
                $minStock = $filters['stock_min'] ?? $filters['min_stock'];
                $query->where('stock', '>=', $minStock);
            }
            if (isset($filters['stock_max']) || isset($filters['max_stock'])) {
                $maxStock = $filters['stock_max'] ?? $filters['max_stock'];
                $query->where('stock', '<=', $maxStock);
            }

            // Availability filters
            if (isset($filters['available']) && ($filters['available'] === 'true' || $filters['available'] === true || $filters['available'] === '1' || $filters['available'] === 1)) {
                $query->where('available_stock', '>', 0);
            }
            if (isset($filters['low_stock']) && ($filters['low_stock'] === 'true' || $filters['low_stock'] === true || $filters['low_stock'] === '1' || $filters['low_stock'] === 1)) {
                $query->whereRaw('available_stock <= stock * 0.2')->where('available_stock', '>', 0);
            }
            if (isset($filters['out_of_stock']) && ($filters['out_of_stock'] === 'true' || $filters['out_of_stock'] === true || $filters['out_of_stock'] === '1' || $filters['out_of_stock'] === 1)) {
                $query->where('available_stock', '=', 0);
            }

            // Sorting
            $sortBy = $filters['sort_by'] ?? 'created_at';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            $allowedSorts = ['name', 'stock', 'available_stock', 'created_at', 'updated_at'];

            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->latest();
            }

            return $query->paginate($filters['per_page'] ?? 15);
        };

        try {
            if (Cache::supportsTags()) {
                return Cache::tags(['items'])->remember($cacheKey, 3600, $callback);
            }
        } catch (\Throwable) {
            // Fallback to standard cache
        }

        return Cache::remember($cacheKey, 3600, $callback);
    }
}
