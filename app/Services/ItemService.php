<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ItemService
{
    /**
     * Generate unique item code
     */
    public function generateItemCode(): string
    {
        $date = now()->format('Ymd');
        $lastItem = Item::whereDate('created_at', today())
            ->latest('id')
            ->first();
        
        $sequence = $lastItem ? ((int) substr($lastItem->code, -4)) + 1 : 1;
        
        return "ITM-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new item
     */
    public function createItem(array $data): Item
    {
        if (!isset($data['code'])) {
            $data['code'] = $this->generateItemCode();
        }

        $data['available_stock'] = $data['stock'];

        if (isset($data['image'])) {
            $data['image'] = $this->handleImageUpload($data['image']);
        }

        $item = Item::create($data);
        
        // Clear cache when item is created
        $this->clearItemsCache();
        
        return $item;
    }

    /**
     * Update an existing item
     */
    public function updateItem(Item $item, array $data): Item
    {
        if (isset($data['image'])) {
            // Delete old image if exists
            if ($item->image) {
                Storage::disk('public')->delete($item->image);
            }
            
            $data['image'] = $this->handleImageUpload($data['image']);
        }

        // Update available stock if total stock changes
        if (isset($data['stock'])) {
            $borrowedStock = $item->stock - $item->available_stock;
            $data['available_stock'] = $data['stock'] - $borrowedStock;
        }

        $item->update($data);
        
        // Clear cache when item is updated
        $this->clearItemsCache();

        return $item->fresh();
    }

    /**
     * Delete an item
     */
    public function deleteItem(Item $item): bool
    {
        // Check if item has active borrowings
        if ($item->borrowings()->whereIn('status', ['pending', 'dipinjam'])->exists()) {
            throw new \Exception('Tidak dapat menghapus barang yang sedang dipinjam');
        }

        if ($item->image) {
            Storage::disk('public')->delete($item->image);
        }

        $deleted = $item->delete();
        
        // Clear cache when item is deleted
        $this->clearItemsCache();
        
        return $deleted;
    }

    /**
     * Handle image upload with optimization
     */
    private function handleImageUpload($image): string
    {
        $filename = Str::uuid() . '.jpg'; // Always save as JPG
        $path = 'items/' . $filename;
        
        // Create image manager with GD driver
        $manager = new ImageManager(new Driver());
        
        // Read and process image
        $img = $manager->read($image);
        
        // Resize if larger than 800x800 while maintaining aspect ratio
        $img->scaleDown(width: 800, height: 800);
        
        // Encode to JPEG with 85% quality for optimization
        $encoded = $img->toJpeg(quality: 85);
        
        // Save to storage
        Storage::disk('public')->put($path, (string) $encoded);
        
        return $path;
    }

    /**
     * Update item stock when borrowed
     */
    public function decreaseStock(Item $item, int $quantity): void
    {
        if ($item->available_stock < $quantity) {
            throw new \Exception('Stok tidak mencukupi');
        }

        $item->decrement('available_stock', $quantity);
    }

    /**
     * Update item stock when returned
     */
    public function increaseStock(Item $item, int $quantity): void
    {
        $item->increment('available_stock', $quantity);
    }
    
    /**
     * Clear items-related cache
     */
    private function clearItemsCache(): void
    {
        Cache::tags(['items'])->flush();
    }
    
    /**
     * Get items with caching
     */
    public function getCachedItems(array $filters = []): mixed
    {
        $cacheKey = 'items:' . md5(json_encode($filters));
        
        return Cache::tags(['items'])->remember($cacheKey, 3600, function () use ($filters) {
            $query = Item::with('category');
            
            if (isset($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }
            
            if (isset($filters['condition'])) {
                $query->where('condition', $filters['condition']);
            }
            
            if (isset($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('code', 'like', '%' . $filters['search'] . '%');
                });
            }
            
            return $query->paginate($filters['per_page'] ?? 15);
        });
    }
}
