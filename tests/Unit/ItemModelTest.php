<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Item;
use App\Models\Borrowing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $item->category);
        $this->assertEquals($category->id, $item->category->id);
    }

    public function test_item_has_many_borrowings(): void
    {
        $item = Item::factory()->create();
        Borrowing::factory()->count(3)->create(['item_id' => $item->id]);

        $this->assertEquals(3, $item->borrowings()->count());
    }

    public function test_is_available_returns_true_when_stock_available(): void
    {
        $item = Item::factory()->create(['available_stock' => 5]);

        $this->assertTrue($item->isAvailable());
    }

    public function test_is_available_returns_false_when_no_stock(): void
    {
        $item = Item::factory()->create(['available_stock' => 0]);

        $this->assertFalse($item->isAvailable());
    }

    public function test_is_low_stock_returns_true_when_below_threshold(): void
    {
        $item = Item::factory()->create(['stock' => 100, 'available_stock' => 15]);

        $this->assertTrue($item->isLowStock());
    }

    public function test_is_low_stock_returns_false_when_above_threshold(): void
    {
        $item = Item::factory()->create(['stock' => 100, 'available_stock' => 50]);

        $this->assertFalse($item->isLowStock());
    }

    public function test_available_quantity_returns_correct_count(): void
    {
        $item = Item::factory()->create(['stock' => 20, 'available_stock' => 12]);

        $this->assertEquals(12, $item->availableQuantity());
    }

    public function test_borrowed_quantity_returns_correct_count(): void
    {
        $item = Item::factory()->create(['stock' => 20, 'available_stock' => 12]);

        $this->assertEquals(8, $item->borrowedQuantity());
    }

    public function test_scope_available_filters_items_with_stock(): void
    {
        Item::factory()->create(['available_stock' => 5]);
        Item::factory()->create(['available_stock' => 0]);
        Item::factory()->create(['available_stock' => 10]);

        $available = Item::available()->get();

        $this->assertEquals(2, $available->count());
    }

    public function test_scope_low_stock_filters_items_below_threshold(): void
    {
        Item::factory()->create(['stock' => 100, 'available_stock' => 15]);
        Item::factory()->create(['stock' => 100, 'available_stock' => 50]);
        Item::factory()->create(['stock' => 100, 'available_stock' => 10]);

        $lowStock = Item::lowStock()->get();

        $this->assertEquals(2, $lowStock->count());
    }

    public function test_fillable_attributes_are_mass_assignable(): void
    {
        $data = [
            'name' => 'Test Item',
            'code' => 'TEST-001',
            'stock' => 10,
            'available_stock' => 10,
            'condition' => 'good',
            'description' => 'Test description',
        ];

        $item = new Item($data);

        $this->assertEquals('Test Item', $item->name);
        $this->assertEquals('TEST-001', $item->code);
        $this->assertEquals(10, $item->stock);
    }
}
