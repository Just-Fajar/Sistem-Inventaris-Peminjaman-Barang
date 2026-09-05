<?php

namespace Tests\Feature;

use App\Exceptions\ItemException;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Category $category;
    protected ItemService $itemService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff@example.com',
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Elektronik Kantor',
        ]);

        $this->itemService = app(ItemService::class);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Direct Service Logic, Stock Recalculation, Image Processing)
     * ========================================================================= */

    public function test_whitebox_service_create_item_generates_code_and_sets_available_stock(): void
    {
        $item = $this->itemService->createItem([
            'name' => 'Projector Epson EB-X500',
            'category_id' => $this->category->id,
            'stock' => 5,
            'condition' => 'baik',
            'description' => 'Projector HDMI 3600 Lumens',
        ]);

        $this->assertInstanceOf(Item::class, $item);
        $this->assertStringStartsWith('ITM-', $item->code);
        $condition = $item->condition;
        $this->assertEquals('baik', $condition instanceof \App\Enums\ItemCondition ? $condition->value : $condition);
    }

    public function test_whitebox_service_create_item_with_image_optimizes_file(): void
    {
        $fakeImage = UploadedFile::fake()->image('projector.png', 1600, 1200);

        $item = $this->itemService->createItem([
            'name' => 'Projector InFocus',
            'category_id' => $this->category->id,
            'stock' => 3,
            'condition' => 'baik',
            'image' => $fakeImage,
        ]);

        $this->assertNotNull($item->image);
        Storage::disk('public')->assertExists($item->image);
    }

    public function test_whitebox_service_update_item_recalculates_available_stock_difference(): void
    {
        $item = $this->itemService->createItem([
            'name' => 'Monitor LG 24 Inch',
            'category_id' => $this->category->id,
            'stock' => 10,
            'condition' => 'baik',
        ]);

        // Simulate 3 items borrowed: available_stock becomes 7
        $item->update(['available_stock' => 7]);

        // Admin increases total stock from 10 to 15 (difference = +5)
        $updated = $this->itemService->updateItem($item, [
            'stock' => 15,
        ]);

        // Available stock should now be 7 + 5 = 12
        $this->assertEquals(15, $updated->stock);
        $this->assertEquals(12, $updated->available_stock);
    }

    public function test_whitebox_service_update_item_replaces_and_deletes_old_image(): void
    {
        $oldImage = UploadedFile::fake()->image('old_photo.jpg', 800, 600);
        $item = $this->itemService->createItem([
            'name' => 'Keyboard Mechanical',
            'category_id' => $this->category->id,
            'stock' => 8,
            'condition' => 'baik',
            'image' => $oldImage,
        ]);

        $oldImagePath = $item->image;
        Storage::disk('public')->assertExists($oldImagePath);

        // Upload new image
        $newImage = UploadedFile::fake()->image('new_photo.png', 1000, 1000);
        $updated = $this->itemService->updateItem($item, [
            'image' => $newImage,
        ]);

        $this->assertNotEquals($oldImagePath, $updated->image);
        Storage::disk('public')->assertExists($updated->image);
        Storage::disk('public')->assertMissing($oldImagePath);
    }

    public function test_whitebox_service_delete_item_throws_when_active_borrowings_exist(): void
    {
        $item = $this->itemService->createItem([
            'name' => 'Camera DSLR Canon',
            'category_id' => $this->category->id,
            'stock' => 2,
            'condition' => 'baik',
        ]);

        Borrowing::factory()->create([
            'item_id' => $item->id,
            'user_id' => $this->staff->id,
            'quantity' => 1,
            'status' => 'dipinjam',
        ]);

        $this->expectException(ItemException::class);
        $this->expectExceptionMessage('Cannot delete item with active borrowings');

        $this->itemService->deleteItem($item);
    }

    public function test_whitebox_service_delete_item_deletes_image_and_soft_deletes_record(): void
    {
        $image = UploadedFile::fake()->image('printer.jpg', 600, 600);
        $item = $this->itemService->createItem([
            'name' => 'Printer HP LaserJet',
            'category_id' => $this->category->id,
            'stock' => 2,
            'condition' => 'baik',
            'image' => $image,
        ]);

        $imagePath = $item->image;
        Storage::disk('public')->assertExists($imagePath);

        $result = $this->itemService->deleteItem($item);

        $this->assertTrue($result);
        $this->assertSoftDeleted('items', ['id' => $item->id]);
        Storage::disk('public')->assertMissing($imagePath);
    }

    public function test_whitebox_service_bulk_delete_items(): void
    {
        $item1 = $this->itemService->createItem([
            'name' => 'Bulk Item 1',
            'category_id' => $this->category->id,
            'stock' => 2,
            'condition' => 'baik',
        ]);

        $item2 = $this->itemService->createItem([
            'name' => 'Bulk Item 2',
            'category_id' => $this->category->id,
            'stock' => 3,
            'condition' => 'baik',
        ]);

        $result = $this->itemService->bulkDeleteItems([$item1->id, $item2->id]);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['count']);
        $this->assertSoftDeleted('items', ['id' => $item1->id]);
        $this->assertSoftDeleted('items', ['id' => $item2->id]);
    }

    public function test_whitebox_service_bulk_delete_throws_if_any_item_has_active_borrowings(): void
    {
        $item1 = $this->itemService->createItem([
            'name' => 'Active Borrowing Item',
            'category_id' => $this->category->id,
            'stock' => 2,
            'condition' => 'baik',
        ]);

        Borrowing::factory()->create([
            'item_id' => $item1->id,
            'user_id' => $this->staff->id,
            'quantity' => 1,
            'status' => 'dipinjam',
        ]);

        $item2 = $this->itemService->createItem([
            'name' => 'Free Item',
            'category_id' => $this->category->id,
            'stock' => 3,
            'condition' => 'baik',
        ]);

        $this->expectException(ItemException::class);
        $this->expectExceptionMessage('Some items have active borrowings and cannot be deleted');

        $this->itemService->bulkDeleteItems([$item1->id, $item2->id]);
    }

    public function test_whitebox_service_get_cached_items(): void
    {
        $this->itemService->createItem([
            'name' => 'Unique Search Item ABC',
            'category_id' => $this->category->id,
            'stock' => 5,
            'condition' => 'baik',
        ]);

        $results = $this->itemService->getCachedItems(['search' => 'ABC']);
        $this->assertEquals(1, $results->total());
        $this->assertEquals('Unique Search Item ABC', $results->items()[0]->name);
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (API Endpoints through Injected ItemService)
     * ========================================================================= */

    public function test_blackbox_store_item_endpoint_with_image(): void
    {
        Sanctum::actingAs($this->admin);

        $fakeImage = UploadedFile::fake()->image('router.png', 1000, 800);

        $response = $this->postJson('/api/items', [
            'name' => 'Router Cisco 2901',
            'category_id' => $this->category->id,
            'stock' => 4,
            'condition' => 'baik',
            'description' => 'Gigabit Router',
            'image' => $fakeImage,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Item created successfully',
                'data' => [
                    'name' => 'Router Cisco 2901',
                    'stock' => 4,
                    'available_stock' => 4,
                    'condition' => 'baik',
                ],
            ]);

        $createdImage = $response->json('data.image');
        $this->assertNotNull($createdImage);
        Storage::disk('public')->assertExists($createdImage);
    }

    public function test_blackbox_update_item_endpoint_with_image_replacement(): void
    {
        Sanctum::actingAs($this->admin);

        $item = $this->itemService->createItem([
            'name' => 'Switch D-Link 24 Port',
            'category_id' => $this->category->id,
            'stock' => 6,
            'condition' => 'baik',
            'image' => UploadedFile::fake()->image('old_switch.jpg', 600, 600),
        ]);

        $oldImage = $item->image;

        $newImage = UploadedFile::fake()->image('new_switch.png', 800, 800);

        $response = $this->postJson("/api/items/{$item->id}", [
            'name' => 'Switch D-Link 24 Port Gigabit (Updated)',
            'category_id' => $this->category->id,
            'stock' => 8,
            'condition' => 'baik',
            'image' => $newImage,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Item updated successfully',
                'data' => [
                    'name' => 'Switch D-Link 24 Port Gigabit (Updated)',
                    'stock' => 8,
                    'available_stock' => 8,
                ],
            ]);

        $newImagePath = $response->json('data.image');
        Storage::disk('public')->assertExists($newImagePath);
        Storage::disk('public')->assertMissing($oldImage);
    }

    public function test_blackbox_destroy_item_endpoint_blocked_by_active_borrowing(): void
    {
        Sanctum::actingAs($this->admin);

        $item = $this->itemService->createItem([
            'name' => 'iPad Pro 11 Inch',
            'category_id' => $this->category->id,
            'stock' => 2,
            'condition' => 'baik',
        ]);

        Borrowing::factory()->create([
            'item_id' => $item->id,
            'user_id' => $this->staff->id,
            'quantity' => 1,
            'status' => 'dipinjam',
        ]);

        $response = $this->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete item with active borrowings',
            ]);
    }

    public function test_blackbox_destroy_item_endpoint_success_when_free(): void
    {
        Sanctum::actingAs($this->admin);

        $item = $this->itemService->createItem([
            'name' => 'Tripod Kamera',
            'category_id' => $this->category->id,
            'stock' => 3,
            'condition' => 'baik',
        ]);

        $response = $this->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Item deleted successfully',
            ]);

        $this->assertSoftDeleted('items', ['id' => $item->id]);
    }

    public function test_blackbox_bulk_delete_endpoint_success(): void
    {
        Sanctum::actingAs($this->admin);

        $item1 = $this->itemService->createItem([
            'name' => 'Item To Bulk 1',
            'category_id' => $this->category->id,
            'stock' => 1,
            'condition' => 'baik',
        ]);

        $item2 = $this->itemService->createItem([
            'name' => 'Item To Bulk 2',
            'category_id' => $this->category->id,
            'stock' => 1,
            'condition' => 'baik',
        ]);

        $response = $this->deleteJson('/api/items/bulk-delete', [
            'ids' => [$item1->id, $item2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '2 items deleted successfully',
            ]);

        $this->assertSoftDeleted('items', ['id' => $item1->id]);
        $this->assertSoftDeleted('items', ['id' => $item2->id]);
    }

    public function test_blackbox_bulk_delete_endpoint_fails_when_item_borrowed(): void
    {
        Sanctum::actingAs($this->admin);

        $item1 = $this->itemService->createItem([
            'name' => 'Item Borrowed',
            'category_id' => $this->category->id,
            'stock' => 1,
            'condition' => 'baik',
        ]);

        Borrowing::factory()->create([
            'item_id' => $item1->id,
            'user_id' => $this->staff->id,
            'quantity' => 1,
            'status' => 'dipinjam',
        ]);

        $item2 = $this->itemService->createItem([
            'name' => 'Item Not Borrowed',
            'category_id' => $this->category->id,
            'stock' => 1,
            'condition' => 'baik',
        ]);

        $response = $this->deleteJson('/api/items/bulk-delete', [
            'ids' => [$item1->id, $item2->id],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Some items have active borrowings and cannot be deleted',
                'items' => ['Item Borrowed'],
            ]);

        $this->assertNotSoftDeleted('items', ['id' => $item1->id]);
        $this->assertNotSoftDeleted('items', ['id' => $item2->id]);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Database Integrity, Storage Lifecycle, and Soft Deletes)
     * ========================================================================= */

    public function test_greybox_database_persistence_and_soft_delete_integrity(): void
    {
        $item = $this->itemService->createItem([
            'name' => 'Microphone Shure SM58',
            'category_id' => $this->category->id,
            'stock' => 4,
            'condition' => 'baik',
        ]);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'name' => 'Microphone Shure SM58',
            'deleted_at' => null,
        ]);

        $this->itemService->deleteItem($item);

        // Record still exists in database with deleted_at timestamp
        $this->assertSoftDeleted('items', [
            'id' => $item->id,
        ]);
    }

    public function test_greybox_image_storage_lifecycle_create_update_delete(): void
    {
        // 1. Create with Image
        $img1 = UploadedFile::fake()->image('step1.png', 1200, 900);
        $item = $this->itemService->createItem([
            'name' => 'Headset Audio Technica',
            'category_id' => $this->category->id,
            'stock' => 5,
            'condition' => 'baik',
            'image' => $img1,
        ]);

        $path1 = $item->image;
        Storage::disk('public')->assertExists($path1);

        // 2. Update with New Image
        $img2 = UploadedFile::fake()->image('step2.png', 1000, 800);
        $updated = $this->itemService->updateItem($item, [
            'image' => $img2,
        ]);

        $path2 = $updated->image;
        Storage::disk('public')->assertExists($path2);
        Storage::disk('public')->assertMissing($path1);

        // 3. Delete Item
        $this->itemService->deleteItem($updated);
        Storage::disk('public')->assertMissing($path2);
    }
}
