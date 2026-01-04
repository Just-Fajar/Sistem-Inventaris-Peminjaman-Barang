<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get items in this category
     */
    public function items()
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Get available items count in this category
     */
    public function getAvailableItemsCountAttribute()
    {
        return $this->items()->where('available_stock', '>', 0)->count();
    }
}
