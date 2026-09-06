<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingStockRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->category = Category::factory()->create();
    }

    // ==========================================
    // 1. BLACK-BOX TESTING (API & Functional)
    // ==========================================

    public function test_blackbox_user_can_create_borrowing_when_stock_is_sufficient(): void
    {
        Sanctum::actingAs($this->user);

        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 10,
            'available_stock' => 5,
            'condition' => 'baik',
        ]);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $item->id,
            'quantity' => 2,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Peminjaman untuk keperluan testing',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Borrowing created successfully',
                'data' => [
                    'status' => 'pending',
                ],
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'borrow_code',
                    'user_id',
                    'item_id',
                    'quantity',
                    'status',
                ],
            ]);

        $this->assertEquals(5, $item->fresh()->available_stock);
    }

    public function test_blackbox_user_cannot_borrow_more_than_available_stock(): void
    {
        Sanctum::actingAs($this->user);

        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 2,
            'condition' => 'baik',
        ]);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $item->id,
            'quantity' => 3,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Item is not available in requested quantity',
                'available_stock' => 2,
            ]);

        $this->assertEquals(2, $item->fresh()->available_stock);
    }

    public function test_blackbox_user_cannot_borrow_item_with_damaged_condition(): void
    {
        Sanctum::actingAs($this->user);

        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 5,
            'condition' => 'rusak',
        ]);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $item->id,
            'quantity' => 1,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Item is not available in requested quantity',
                'available_stock' => 5,
            ]);
    }

    public function test_blackbox_user_can_return_active_borrowing(): void
    {
        Sanctum::actingAs($this->user);

        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 3,
            'condition' => 'baik',
        ]);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->user->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'status' => 'dipinjam',
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/return");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Item returned successfully',
                'data' => [
                    'id' => $borrowing->id,
                    'status' => 'dikembalikan',
                ],
            ]);

        $this->assertEquals(5, $item->fresh()->available_stock);
    }

    public function test_blackbox_user_cannot_return_already_returned_borrowing(): void
    {
        Sanctum::actingAs($this->user);

        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 5,
            'condition' => 'baik',
        ]);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->user->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'status' => 'dikembalikan',
            'return_date' => now(),
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/return");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Item already returned',
            ]);

        $this->assertEquals(5, $item->fresh()->available_stock);
    }

    // ==========================================
    // 2. WHITE-BOX TESTING (Internal Logic)
    // ==========================================

    public function test_whitebox_decrease_stock_deducts_model_available_stock_correctly(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 10,
            'available_stock' => 10,
            'condition' => 'baik',
        ]);

        $item->decreaseStock(4);

        $this->assertEquals(6, $item->fresh()->available_stock);
    }

    public function test_whitebox_decrease_stock_throws_exception_on_zero_or_negative_quantity(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'available_stock' => 5,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero.');

        $item->decreaseStock(0);
    }

    public function test_whitebox_decrease_stock_throws_exception_when_quantity_exceeds_available_stock(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'available_stock' => 2,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot decrease stock below zero.');

        $item->decreaseStock(3);
    }

    public function test_whitebox_increase_stock_restores_available_stock_correctly(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'available_stock' => 3,
        ]);

        $item->increaseStock(2);

        $this->assertEquals(5, $item->fresh()->available_stock);
    }

    public function test_whitebox_increase_stock_throws_exception_on_invalid_quantity(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'available_stock' => 3,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero.');

        $item->increaseStock(-1);
    }

    public function test_whitebox_transaction_rollback_prevents_partial_commit_on_failure(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'available_stock' => 5,
            'condition' => 'baik',
        ]);

        $initialStock = $item->available_stock;

        try {
            DB::transaction(function () use ($item) {
                $lockedItem = Item::lockForUpdate()->findOrFail($item->id);
                $lockedItem->decreaseStock(2);

                // Simulate an unexpected error occurring after stock reduction
                throw new \RuntimeException('Simulated unexpected failure during processing');
            });
        } catch (\RuntimeException $e) {
            // Expected exception
        }

        // Verify transaction rolled back completely
        $this->assertEquals($initialStock, $item->fresh()->available_stock);
    }

    // ==========================================
    // 3. GREY-BOX TESTING (State & Concurrency Simulation)
    // ==========================================

    public function test_greybox_simulated_concurrent_borrowings_prevent_negative_stock(): void
    {
        Sanctum::actingAs($this->user);

        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 1,
            'available_stock' => 1,
            'condition' => 'baik',
        ]);

        // Request 1: Pending borrowing created
        $response1 = $this->postJson('/api/borrowings', [
            'item_id' => $item->id,
            'quantity' => 1,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);
        $response1->assertStatus(201);
        $borrowing1Id = $response1->json('data.id');

        // Request 2: Another pending borrowing created
        $response2 = $this->postJson('/api/borrowings', [
            'item_id' => $item->id,
            'quantity' => 1,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);
        $response2->assertStatus(201);
        $borrowing2Id = $response2->json('data.id');

        // Admin approves Request 1: succeeds and decreases stock from 1 to 0
        $admin = User::factory()->create(['role' => 'admin']);
        $approve1 = $this->actingAs($admin)->postJson("/api/borrowings/{$borrowing1Id}/approve");
        $approve1->assertStatus(200);

        // Admin approves Request 2: rejected because stock is now 0
        $approve2 = $this->actingAs($admin)->postJson("/api/borrowings/{$borrowing2Id}/approve");
        $approve2->assertStatus(422)
            ->assertJson([
                'message' => 'Stok barang tidak mencukupi untuk disetujui.',
                'available_stock' => 0,
            ]);

        // Database assertion: stock must NOT become -1
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'available_stock' => 0,
        ]);

        // Only one borrowing has status dipinjam
        $this->assertEquals(1, Borrowing::where('item_id', $item->id)->where('status', 'dipinjam')->count());
    }

    public function test_greybox_simulated_double_return_does_not_inflate_stock(): void
    {
        Sanctum::actingAs($this->user);

        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 3,
            'condition' => 'baik',
        ]);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->user->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'status' => 'dipinjam',
        ]);

        // First return attempt: succeeds
        $res1 = $this->postJson("/api/borrowings/{$borrowing->id}/return");
        $res1->assertStatus(200);

        // Immediate second return attempt: must be rejected
        $res2 = $this->postJson("/api/borrowings/{$borrowing->id}/return");
        $res2->assertStatus(422);

        // Database assertion: stock should be 5, not inflated to 7
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'available_stock' => 5,
        ]);

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'dikembalikan',
        ]);
    }

    public function test_greybox_database_state_consistency_through_full_borrow_and_return_cycle(): void
    {
        Sanctum::actingAs($this->user);

        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 10,
            'available_stock' => 10,
            'condition' => 'baik',
        ]);

        // 1. Borrow 4 items (creates pending borrowing)
        $borrowResponse = $this->postJson('/api/borrowings', [
            'item_id' => $item->id,
            'quantity' => 4,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $borrowResponse->assertStatus(201);
        $borrowingId = $borrowResponse->json('data.id');

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'available_stock' => 10,
        ]);

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowingId,
            'status' => 'pending',
            'quantity' => 4,
        ]);

        // 1b. Admin approves the borrowing -> stock decreases to 6, status becomes dipinjam
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson("/api/borrowings/{$borrowingId}/approve")->assertStatus(200);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'available_stock' => 6,
        ]);

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowingId,
            'status' => 'dipinjam',
            'quantity' => 4,
        ]);

        // 2. Return the 4 items
        $returnResponse = $this->postJson("/api/borrowings/{$borrowingId}/return");
        $returnResponse->assertStatus(200);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'available_stock' => 10,
        ]);

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowingId,
            'status' => 'dikembalikan',
        ]);
    }
}
