<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\ItemCondition;
use App\Exceptions\BorrowingException;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\BorrowingService;
use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingStorePendingTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $admin;
    protected Category $category;
    protected Item $item;
    protected BorrowingService $borrowingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->category = Category::factory()->create(['name' => 'Elektronik']);
        $this->item = Item::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'MacBook Pro M3',
            'code' => 'ITM-TEST-001',
            'stock' => 10,
            'available_stock' => 10,
            'condition' => ItemCondition::Baik,
        ]);

        $this->borrowingService = app(BorrowingService::class);
        Queue::fake();
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Direct Service Logic & Stock Preservation)
     * ========================================================================= */

    public function test_whitebox_create_borrowing_defaults_to_pending_and_preserves_stock(): void
    {
        $payload = [
            'item_id' => $this->item->id,
            'quantity' => 3,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'notes' => 'Whitebox pending store default test',
        ];

        $borrowing = $this->borrowingService->createBorrowing($payload, $this->staff->id);

        $this->assertInstanceOf(Borrowing::class, $borrowing);
        $this->assertEquals(BorrowingStatus::Pending, $borrowing->status);
        $this->assertEquals(3, $borrowing->quantity);
        $this->assertNull($borrowing->approved_by);
        $this->assertNull($borrowing->approved_at);

        // Stock must remain unchanged
        $this->assertEquals(10, $this->item->fresh()->available_stock);
    }

    public function test_whitebox_create_borrowing_with_explicit_dipinjam_status_deducts_stock(): void
    {
        $payload = [
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => BorrowingStatus::Dipinjam->value,
        ];

        $borrowing = $this->borrowingService->createBorrowing($payload, $this->staff->id);

        $this->assertEquals(BorrowingStatus::Dipinjam, $borrowing->status);
        // Stock must be deducted for direct dipinjam status
        $this->assertEquals(8, $this->item->fresh()->available_stock);
    }

    public function test_whitebox_create_borrowing_fails_when_stock_insufficient(): void
    {
        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Item is not available in requested quantity');

        $this->borrowingService->createBorrowing([
            'item_id' => $this->item->id,
            'quantity' => 15,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ], $this->staff->id);
    }

    public function test_whitebox_create_borrowing_fails_when_item_condition_is_rusak(): void
    {
        $damagedItem = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 5,
            'condition' => ItemCondition::Rusak,
        ]);

        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Item is not available in requested quantity');

        $this->borrowingService->createBorrowing([
            'item_id' => $damagedItem->id,
            'quantity' => 1,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ], $this->staff->id);
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (API HTTP Endpoints)
     * ========================================================================= */

    public function test_blackbox_staff_store_borrowing_returns_201_with_pending_status(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'notes' => 'Permintaan peminjaman laptop untuk dinas',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Borrowing created successfully',
                'data' => [
                    'quantity' => 2,
                    'status' => 'pending',
                ],
            ]);

        // Stock in database remains 10
        $this->assertEquals(10, $this->item->fresh()->available_stock);
    }

    public function test_blackbox_store_borrowing_rejected_when_stock_insufficient(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $this->item->id,
            'quantity' => 25,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Item is not available in requested quantity',
                'available_stock' => 10,
            ]);
    }

    public function test_blackbox_store_borrowing_rejected_when_item_damaged(): void
    {
        Sanctum::actingAs($this->staff);

        $damagedItem = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 2,
            'available_stock' => 2,
            'condition' => ItemCondition::Rusak,
        ]);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $damagedItem->id,
            'quantity' => 1,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Item is not available in requested quantity',
            ]);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Database State & State Transitions Workflow)
     * ========================================================================= */

    public function test_greybox_database_state_on_store_pending_and_subsequent_approval(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->staff);

        // 1. Submit borrowing request
        $response = $this->postJson('/api/borrowings', [
            'item_id' => $this->item->id,
            'quantity' => 4,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Greybox store pending test',
        ]);

        $response->assertStatus(201);
        $borrowingId = $response->json('data.id');

        // Verify borrowing is in DB with pending status
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowingId,
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 4,
            'status' => 'pending',
            'approved_by' => null,
        ]);

        // Verify available_stock is NOT deducted
        $this->assertDatabaseHas('items', [
            'id' => $this->item->id,
            'available_stock' => 10,
        ]);

        // 2. Admin approves the borrowing
        Sanctum::actingAs($this->admin);
        $approveResponse = $this->postJson("/api/borrowings/{$borrowingId}/approve");

        $approveResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing approved successfully',
                'data' => [
                    'id' => $borrowingId,
                    'status' => 'dipinjam',
                    'approved_by' => $this->admin->id,
                ],
            ]);

        // Verify DB now reflects deducted stock and approved status
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowingId,
            'status' => 'dipinjam',
            'approved_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('items', [
            'id' => $this->item->id,
            'available_stock' => 6,
        ]);
    }

    public function test_greybox_database_state_on_store_pending_and_subsequent_rejection(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->staff);

        // 1. Submit borrowing request
        $response = $this->postJson('/api/borrowings', [
            'item_id' => $this->item->id,
            'quantity' => 3,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $response->assertStatus(201);
        $borrowingId = $response->json('data.id');

        // 2. Admin rejects the borrowing
        Sanctum::actingAs($this->admin);
        $rejectResponse = $this->postJson("/api/borrowings/{$borrowingId}/reject");

        $rejectResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing rejected successfully',
                'data' => [
                    'id' => $borrowingId,
                    'status' => 'rejected',
                ],
            ]);

        // Verify DB status is rejected and stock is intact
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowingId,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('items', [
            'id' => $this->item->id,
            'available_stock' => 10,
        ]);
    }

    public function test_greybox_approval_fails_if_stock_became_insufficient_after_pending_creation(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->staff);

        // Set stock to 2
        $this->item->update(['available_stock' => 2]);

        // Staff creates pending borrowing for 2 items
        $response = $this->postJson('/api/borrowings', [
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);
        $response->assertStatus(201);
        $borrowingId = $response->json('data.id');

        // Meanwhile, someone else or an admin directly reduced stock to 0
        $this->item->update(['available_stock' => 0]);

        // Admin attempts to approve
        Sanctum::actingAs($this->admin);
        $approveResponse = $this->postJson("/api/borrowings/{$borrowingId}/approve");

        $approveResponse->assertStatus(422)
            ->assertJson([
                'message' => 'Stok barang tidak mencukupi untuk disetujui.',
                'available_stock' => 0,
            ]);

        // Borrowing status remains pending, stock remains 0 (not negative)
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowingId,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('items', [
            'id' => $this->item->id,
            'available_stock' => 0,
        ]);
    }
}
