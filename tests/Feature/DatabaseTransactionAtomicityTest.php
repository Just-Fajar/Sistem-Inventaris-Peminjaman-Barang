<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\ItemCondition;
use App\Exceptions\BorrowingException;
use App\Exceptions\ItemException;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\BorrowingService;
use App\Services\ItemService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseTransactionAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Category $category;
    protected ItemService $itemService;
    protected BorrowingService $borrowingService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->category = Category::create([
            'name' => 'Elektronik & Multimedia',
            'description' => 'Kategori perangkat elektronik',
        ]);

        $this->itemService = app(ItemService::class);
        $this->borrowingService = app(BorrowingService::class);
    }

    /*
    |--------------------------------------------------------------------------
    | White-Box Testing
    |--------------------------------------------------------------------------
    */

    public function test_whitebox_bulk_delete_rolls_back_entire_batch_and_preserves_images_on_exception(): void
    {
        $image1 = UploadedFile::fake()->image('item1.jpg');
        $image2 = UploadedFile::fake()->image('item2.jpg');
        $image3 = UploadedFile::fake()->image('item3.jpg');

        $item1 = $this->itemService->createItem([
            'name' => 'Barang A',
            'category_id' => $this->category->id,
            'stock' => 5,
            'condition' => ItemCondition::Baik,
            'image' => $image1,
        ]);

        $item2 = $this->itemService->createItem([
            'name' => 'Barang B',
            'category_id' => $this->category->id,
            'stock' => 5,
            'condition' => ItemCondition::Baik,
            'image' => $image2,
        ]);

        $item3 = $this->itemService->createItem([
            'name' => 'Barang C',
            'category_id' => $this->category->id,
            'stock' => 5,
            'condition' => ItemCondition::Baik,
            'image' => $image3,
        ]);

        $path1 = $item1->image;
        $path2 = $item2->image;
        $path3 = $item3->image;

        Storage::disk('public')->assertExists($path1);
        Storage::disk('public')->assertExists($path2);
        Storage::disk('public')->assertExists($path3);

        // Simulate an unexpected error occurring during the deletion of the second item
        Item::deleting(function ($model) use ($item2) {
            if ($model->id === $item2->id) {
                throw new \RuntimeException('Simulated unexpected failure during deletion of item 2');
            }
        });

        $exceptionThrown = false;
        try {
            $this->itemService->bulkDeleteItems([$item1->id, $item2->id, $item3->id]);
        } catch (\RuntimeException $e) {
            $exceptionThrown = true;
            $this->assertSame('Simulated unexpected failure during deletion of item 2', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Expected RuntimeException was not thrown.');

        // Verify that database transaction rolled back: none of the items are soft deleted
        $this->assertDatabaseHas('items', ['id' => $item1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('items', ['id' => $item2->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('items', ['id' => $item3->id, 'deleted_at' => null]);

        // Verify that storage images were preserved and not deleted prematurely
        Storage::disk('public')->assertExists($path1);
        Storage::disk('public')->assertExists($path2);
        Storage::disk('public')->assertExists($path3);
    }

    public function test_whitebox_extend_borrowing_runs_inside_transaction_and_updates_due_date(): void
    {
        $item = Item::create([
            'code' => 'ITM-001',
            'name' => 'Proyektor',
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 4,
            'condition' => ItemCondition::Baik,
        ]);

        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-2026-001',
            'user_id' => $this->staff->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'borrow_date' => Carbon::now()->subDays(2),
            'due_date' => Carbon::now()->addDays(2),
            'status' => BorrowingStatus::Dipinjam,
        ]);

        $newDueDate = Carbon::now()->addDays(7)->format('Y-m-d H:i:s');
        $extended = $this->borrowingService->extendBorrowing($borrowing, $newDueDate);

        $this->assertEquals(Carbon::parse($newDueDate)->format('Y-m-d H:i:s'), $extended->due_date->format('Y-m-d H:i:s'));
        $this->assertEquals(BorrowingStatus::Dipinjam, $extended->status);
    }

    public function test_whitebox_cancel_borrowing_runs_inside_transaction_and_deletes_pending(): void
    {
        $item = Item::create([
            'code' => 'ITM-002',
            'name' => 'Kamera DSLR',
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 5,
            'condition' => ItemCondition::Baik,
        ]);

        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-2026-002',
            'user_id' => $this->staff->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'borrow_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(3),
            'status' => BorrowingStatus::Pending,
        ]);

        $result = $this->borrowingService->cancelBorrowing($borrowing);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('borrowings', ['id' => $borrowing->id]);
    }

    public function test_whitebox_delete_borrowing_runs_inside_transaction_for_returned_borrowing(): void
    {
        $item = Item::create([
            'code' => 'ITM-003',
            'name' => 'Microphone',
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 5,
            'condition' => ItemCondition::Baik,
        ]);

        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-2026-003',
            'user_id' => $this->staff->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'borrow_date' => Carbon::now()->subDays(5),
            'due_date' => Carbon::now()->subDays(2),
            'return_date' => Carbon::now()->subDays(2),
            'status' => BorrowingStatus::Dikembalikan,
        ]);

        $result = $this->borrowingService->deleteBorrowing($borrowing);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('borrowings', ['id' => $borrowing->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Black-Box Testing
    |--------------------------------------------------------------------------
    */

    public function test_blackbox_bulk_delete_items_api_succeeds_for_valid_batch(): void
    {
        $item1 = Item::create([
            'code' => 'ITM-BK-1',
            'name' => 'Monitor 24 inch',
            'category_id' => $this->category->id,
            'stock' => 3,
            'available_stock' => 3,
            'condition' => ItemCondition::Baik,
        ]);

        $item2 = Item::create([
            'code' => 'ITM-BK-2',
            'name' => 'Monitor 27 inch',
            'category_id' => $this->category->id,
            'stock' => 2,
            'available_stock' => 2,
            'condition' => ItemCondition::Baik,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/items/bulk-delete', [
                'ids' => [$item1->id, $item2->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $this->assertSoftDeleted('items', ['id' => $item1->id]);
        $this->assertSoftDeleted('items', ['id' => $item2->id]);
    }

    public function test_blackbox_bulk_delete_items_api_aborts_with_422_when_one_item_is_active(): void
    {
        $itemFree = Item::create([
            'code' => 'ITM-FREE-1',
            'name' => 'Speaker Bebas',
            'category_id' => $this->category->id,
            'stock' => 2,
            'available_stock' => 2,
            'condition' => ItemCondition::Baik,
        ]);

        $itemBorrowed = Item::create([
            'code' => 'ITM-BRW-1',
            'name' => 'Speaker Terpinjam',
            'category_id' => $this->category->id,
            'stock' => 2,
            'available_stock' => 1,
            'condition' => ItemCondition::Baik,
        ]);

        Borrowing::create([
            'borrow_code' => 'BRW-ACT-001',
            'user_id' => $this->staff->id,
            'item_id' => $itemBorrowed->id,
            'quantity' => 1,
            'borrow_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(2),
            'status' => BorrowingStatus::Dipinjam,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/items/bulk-delete', [
                'ids' => [$itemFree->id, $itemBorrowed->id],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'items' => [$itemBorrowed->name],
            ]);

        // Neither item should be deleted
        $this->assertDatabaseHas('items', ['id' => $itemFree->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('items', ['id' => $itemBorrowed->id, 'deleted_at' => null]);
    }

    public function test_blackbox_extend_borrowing_api_updates_due_date(): void
    {
        $item = Item::create([
            'code' => 'ITM-EXT-1',
            'name' => 'Tripod Kamera',
            'category_id' => $this->category->id,
            'stock' => 2,
            'available_stock' => 1,
            'condition' => ItemCondition::Baik,
        ]);

        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-EXT-001',
            'user_id' => $this->staff->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'borrow_date' => Carbon::now()->subDay(),
            'due_date' => Carbon::now()->addDays(2),
            'status' => BorrowingStatus::Dipinjam,
        ]);

        $newDueDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        $response = $this->actingAs($this->staff)
            ->postJson("/api/borrowings/{$borrowing->id}/extend", [
                'new_due_date' => $newDueDate,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.due_date', Carbon::parse($newDueDate)->format('Y-m-d'));
    }

    public function test_blackbox_user_delete_endpoint_blocked_if_user_has_active_borrowings(): void
    {
        $item = Item::create([
            'code' => 'ITM-USR-1',
            'name' => 'Tablet Grafis',
            'category_id' => $this->category->id,
            'stock' => 2,
            'available_stock' => 1,
            'condition' => ItemCondition::Baik,
        ]);

        $borrower = User::factory()->create(['role' => 'staff']);

        Borrowing::create([
            'borrow_code' => 'BRW-USR-001',
            'user_id' => $borrower->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'borrow_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(3),
            'status' => BorrowingStatus::Dipinjam,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$borrower->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Tidak dapat menghapus user yang masih memiliki peminjaman aktif',
            ]);

        $this->assertDatabaseHas('users', ['id' => $borrower->id]);
    }

    public function test_blackbox_user_delete_endpoint_succeeds_when_no_active_borrowings(): void
    {
        $cleanUser = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$cleanUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('users', ['id' => $cleanUser->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Grey-Box Testing
    |--------------------------------------------------------------------------
    */

    public function test_greybox_bulk_delete_cleans_up_images_only_after_commit(): void
    {
        $image1 = UploadedFile::fake()->image('del1.jpg');
        $image2 = UploadedFile::fake()->image('del2.jpg');

        $item1 = $this->itemService->createItem([
            'name' => 'Item Delete 1',
            'category_id' => $this->category->id,
            'stock' => 1,
            'condition' => ItemCondition::Baik,
            'image' => $image1,
        ]);

        $item2 = $this->itemService->createItem([
            'name' => 'Item Delete 2',
            'category_id' => $this->category->id,
            'stock' => 1,
            'condition' => ItemCondition::Baik,
            'image' => $image2,
        ]);

        $path1 = $item1->image;
        $path2 = $item2->image;

        Storage::disk('public')->assertExists($path1);
        Storage::disk('public')->assertExists($path2);

        $result = $this->itemService->bulkDeleteItems([$item1->id, $item2->id]);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['count']);

        $this->assertSoftDeleted('items', ['id' => $item1->id]);
        $this->assertSoftDeleted('items', ['id' => $item2->id]);

        // After successful transaction commit, images are removed
        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertMissing($path2);
    }

    public function test_greybox_single_item_delete_inside_transaction_cleans_up_image_on_success(): void
    {
        $image = UploadedFile::fake()->image('single.jpg');

        $item = $this->itemService->createItem([
            'name' => 'Single Item Delete',
            'category_id' => $this->category->id,
            'stock' => 1,
            'condition' => ItemCondition::Baik,
            'image' => $image,
        ]);

        $path = $item->image;
        Storage::disk('public')->assertExists($path);

        $deleted = $this->itemService->deleteItem($item);

        $this->assertTrue($deleted);
        $this->assertSoftDeleted('items', ['id' => $item->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_greybox_item_create_cleans_up_uploaded_image_if_db_fails(): void
    {
        $image = UploadedFile::fake()->image('fail_item.jpg');

        // Pass an invalid category_id which triggers foreign key constraint / DB error
        $failed = false;
        try {
            // Using DB raw to force failure or invalid non-existent category
            $this->itemService->createItem([
                'name' => 'Fail Item',
                'category_id' => 999999,
                'stock' => 5,
                'condition' => ItemCondition::Baik,
                'image' => $image,
            ]);
        } catch (\Throwable $e) {
            $failed = true;
        }

        $this->assertTrue($failed);
        // Ensure no stray files are left in public disk
        $files = Storage::disk('public')->files('items');
        $this->assertEmpty($files, 'Uploaded image should be cleaned up if database insert fails.');
    }
}
