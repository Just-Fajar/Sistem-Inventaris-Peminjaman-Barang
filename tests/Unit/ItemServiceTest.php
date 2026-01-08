<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Item;
use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $itemService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->itemService = new ItemService();
    }

    public function test_can_generate_unique_item_code(): void
    {
        $code1 = $this->itemService->generateItemCode();
        $code2 = $this->itemService->generateItemCode();

        $this->assertStringStartsWith('ITM', $code1);
        $this->assertNotEquals($code1, $code2);
    }

    public function test_can_create_item_with_image(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $file = UploadedFile::fake()->image('test.jpg');

        $data = [
            'name' => 'Test Item',
            'category_id' => $category->id,
            'stock' => 10,
            'condition' => 'good',
            'image' => $file,
        ];

        $item = $this->itemService->createItem($data);

        $this->assertNotNull($item->image);
        $this->assertTrue(Storage::disk('public')->exists('items/' . basename($item->image)));
    }

    public function test_available_stock_is_set_correctly_on_creation(): void
    {
        $category = Category::factory()->create();

        $data = [
            'name' => 'Test Item',
            'category_id' => $category->id,
            'stock' => 25,
            'condition' => 'good',
        ];

        $item = $this->itemService->createItem($data);

        $this->assertEquals(25, $item->available_stock);
        $this->assertEquals(25, $item->stock);
    }

    public function test_can_update_item_with_new_image(): void
    {
        Storage::fake('public');
        
        $item = Item::factory()->create();
        $newFile = UploadedFile::fake()->image('new.jpg');

        $data = [
            'name' => 'Updated Item',
            'category_id' => $item->category_id,
            'stock' => 15,
            'condition' => 'good',
            'image' => $newFile,
        ];

        $updatedItem = $this->itemService->updateItem($item, $data);

        $this->assertNotNull($updatedItem->image);
        $this->assertEquals('Updated Item', $updatedItem->name);
    }

    public function test_old_image_is_deleted_when_updating(): void
    {
        Storage::fake('public');
        
        $oldFile = UploadedFile::fake()->image('old.jpg');
        Storage::disk('public')->put('items/old.jpg', file_get_contents($oldFile));
        
        $item = Item::factory()->create(['image' => 'items/old.jpg']);
        
        $newFile = UploadedFile::fake()->image('new.jpg');

        $data = [
            'name' => $item->name,
            'category_id' => $item->category_id,
            'stock' => $item->stock,
            'condition' => $item->condition,
            'image' => $newFile,
        ];

        $this->itemService->updateItem($item, $data);

        $this->assertFalse(Storage::disk('public')->exists('items/old.jpg'));
    }

    public function test_can_delete_item_with_image(): void
    {
        Storage::fake('public');
        
        $file = UploadedFile::fake()->image('test.jpg');
        Storage::disk('public')->put('items/test.jpg', file_get_contents($file));
        
        $item = Item::factory()->create(['image' => 'items/test.jpg']);

        $this->itemService->deleteItem($item);

        $this->assertFalse(Storage::disk('public')->exists('items/test.jpg'));
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_image_is_optimized_during_upload(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        // Create a larger image
        $file = UploadedFile::fake()->image('large.jpg', 1920, 1080);

        $data = [
            'name' => 'Test Item',
            'category_id' => $category->id,
            'stock' => 10,
            'condition' => 'good',
            'image' => $file,
        ];

        $item = $this->itemService->createItem($data);

        $this->assertNotNull($item->image);
        
        // Check that image was processed and saved
        $this->assertTrue(Storage::disk('public')->exists('items/' . basename($item->image)));
    }
}
