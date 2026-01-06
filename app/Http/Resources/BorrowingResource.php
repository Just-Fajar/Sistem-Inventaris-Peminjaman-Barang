<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BorrowingResource extends JsonResource
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
            'user_id' => $this->user_id,
            'item_id' => $this->item_id,
            'quantity' => $this->quantity,
            'borrow_date' => $this->borrow_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'return_date' => $this->return_date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Conditional relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'item' => new ItemResource($this->whenLoaded('item')),
            'approver' => new UserResource($this->whenLoaded('approver')),
            
            // Calculated fields
            'is_overdue' => $this->when(
                $this->status === 'dipinjam' && $this->due_date && $this->due_date->isPast(),
                true
            ),
            'days_overdue' => $this->when(
                $this->status === 'dipinjam' && $this->due_date && $this->due_date->isPast(),
                now()->diffInDays($this->due_date)
            ),
        ];
    }
}
