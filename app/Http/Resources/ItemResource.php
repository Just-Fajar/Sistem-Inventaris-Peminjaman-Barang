<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'stock' => $this->stock,
            'available_stock' => $this->available_stock,
            'condition' => $this->condition,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Conditional relationships
            'category' => new CategoryResource($this->whenLoaded('category')),
            'borrowings' => BorrowingResource::collection($this->whenLoaded('borrowings')),
            'borrowings_count' => $this->when(isset($this->borrowings_count), $this->borrowings_count),
        ];
    }
}
