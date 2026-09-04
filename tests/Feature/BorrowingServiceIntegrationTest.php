<?php

namespace Tests\Feature;

use App\Exceptions\BorrowingException;
use App\Jobs\SendBorrowingNotification;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\BorrowingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Category $category;
    protected Item $item;
    protected BorrowingService $borrowingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff@example.com',
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Elektronik',
        ]);

        $this->item = Item::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Laptop Dell Latitude',
            'code' => 'ITM-2026-0001',
            'stock' => 10,
            'available_stock' => 10,
            'condition' => 'baik',
        ]);

        $this->borrowingService = app(BorrowingService::class);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Direct Service Logic, Exceptions, and Boundaries)
     * ========================================================================= */

    public function test_whitebox_service_create_borrowing_direct(): void
    {
        $payload = [
            'item_id' => $this->item->id,
            'quantity' => 3,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'notes' => 'Whitebox service direct call',
        ];

        $borrowing = $this->borrowingService->createBorrowing($payload, $this->staff->id);

        $this->assertInstanceOf(Borrowing::class, $borrowing);
        $this->assertEquals('dipinjam', $borrowing->status);
        $this->assertEquals(3, $borrowing->quantity);
        $this->assertStringStartsWith('BRW-', $borrowing->borrow_code);
        $this->assertEquals(7, $this->item->fresh()->available_stock);
    }

    public function test_whitebox_service_create_borrowing_pending_does_not_deduct_stock_immediately(): void
    {
        $payload = [
            'item_id' => $this->item->id,
            'quantity' => 4,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pending',
        ];

        $borrowing = $this->borrowingService->createBorrowing($payload, $this->staff->id);

        $this->assertEquals('pending', $borrowing->status);
        $this->assertEquals(10, $this->item->fresh()->available_stock);
    }

    public function test_whitebox_service_throws_on_insufficient_stock(): void
    {
        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Item is not available in requested quantity');

        $this->borrowingService->createBorrowing([
            'item_id' => $this->item->id,
            'quantity' => 999,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ], $this->staff->id);
    }

    public function test_whitebox_service_approve_pending_borrowing(): void
    {
        Queue::fake();

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $approved = $this->borrowingService->approveBorrowing($borrowing, $this->admin->id);

        $this->assertEquals('dipinjam', $approved->status);
        $this->assertEquals($this->admin->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertEquals(8, $this->item->fresh()->available_stock);
        Queue::assertPushed(SendBorrowingNotification::class);
    }

    public function test_whitebox_service_throws_when_approving_non_pending_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => 'dipinjam',
            'approved_by' => $this->admin->id,
        ]);

        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Peminjaman sudah diproses atau sudah disetujui.');

        $this->borrowingService->approveBorrowing($borrowing, $this->admin->id);
    }

    public function test_whitebox_service_reject_pending_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);

        $rejected = $this->borrowingService->rejectBorrowing($borrowing);

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals(10, $this->item->fresh()->available_stock);
    }

    public function test_whitebox_service_throws_when_rejecting_already_processed_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => 'rejected',
        ]);

        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Peminjaman sudah diproses.');

        $this->borrowingService->rejectBorrowing($borrowing);
    }

    public function test_whitebox_service_return_borrowing_restores_stock(): void
    {
        $this->item->update(['available_stock' => 8]);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => 'dipinjam',
            'due_date' => now()->addDays(3),
        ]);

        $returned = $this->borrowingService->returnBorrowing($borrowing);

        $this->assertEquals('dikembalikan', $returned->status);
        $this->assertNotNull($returned->return_date);
        $this->assertEquals(10, $this->item->fresh()->available_stock);
    }

    public function test_whitebox_service_throws_when_returning_already_returned(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => 'dikembalikan',
            'return_date' => now()->subDay(),
        ]);

        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Item already returned');

        $this->borrowingService->returnBorrowing($borrowing);
    }

    public function test_whitebox_service_extend_due_date(): void
    {
        $dueDate = now()->addDays(5);
        $newDueDate = now()->addDays(10)->toDateString();

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => 'dipinjam',
            'due_date' => $dueDate,
        ]);

        $extended = $this->borrowingService->extendBorrowing($borrowing, $newDueDate);

        $this->assertEquals(Carbon::parse($newDueDate)->toDateString(), $extended->due_date->toDateString());
    }

    public function test_whitebox_service_throws_when_extending_with_earlier_due_date(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => 'dipinjam',
            'due_date' => now()->addDays(5),
        ]);

        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Tanggal perpanjangan harus setelah tanggal jatuh tempo saat ini');

        $this->borrowingService->extendBorrowing($borrowing, now()->addDays(2)->toDateString());
    }

    public function test_whitebox_service_delete_active_borrowing_throws(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
        ]);

        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Cannot delete active borrowing. Please return the item first.');

        $this->borrowingService->deleteBorrowing($borrowing);
    }

    public function test_whitebox_service_cancel_pending_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'pending',
        ]);

        $result = $this->borrowingService->cancelBorrowing($borrowing);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('borrowings', ['id' => $borrowing->id]);
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (API Endpoints through Injected Service)
     * ========================================================================= */

    public function test_blackbox_store_endpoint_uses_service_and_returns_201(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'notes' => 'API store test',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Borrowing created successfully',
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'borrow_code',
                    'quantity',
                    'status',
                    'item',
                    'user',
                ],
            ]);

        $this->assertEquals(8, $this->item->fresh()->available_stock);
    }

    public function test_blackbox_store_endpoint_handles_service_exception_with_422(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $this->item->id,
            'quantity' => 50,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Item is not available in requested quantity',
                'available_stock' => 10,
            ]);
    }

    public function test_blackbox_return_endpoint_uses_service_and_returns_200(): void
    {
        Sanctum::actingAs($this->staff);

        $this->item->update(['available_stock' => 7]);
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 3,
            'status' => 'dipinjam',
            'due_date' => now()->addDays(3),
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

        $this->assertEquals(10, $this->item->fresh()->available_stock);
    }

    public function test_blackbox_approve_and_reject_endpoints_via_service(): void
    {
        Sanctum::actingAs($this->admin);

        // Test Approve
        $pending1 = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);

        $approveRes = $this->postJson("/api/borrowings/{$pending1->id}/approve");
        $approveRes->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing approved successfully',
                'data' => [
                    'id' => $pending1->id,
                    'status' => 'dipinjam',
                ],
            ]);

        // Test Reject
        $pending2 = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);

        $rejectRes = $this->postJson("/api/borrowings/{$pending2->id}/reject");
        $rejectRes->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing rejected successfully',
                'data' => [
                    'id' => $pending2->id,
                    'status' => 'rejected',
                ],
            ]);
    }

    public function test_blackbox_extend_endpoint_via_service(): void
    {
        Sanctum::actingAs($this->staff);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
            'due_date' => now()->addDays(2),
        ]);

        $newDate = now()->addDays(7)->toDateString();

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/extend", [
            'new_due_date' => $newDate,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing extended successfully',
            ]);
    }

    public function test_blackbox_delete_endpoint_via_service(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'dikembalikan',
        ]);

        $response = $this->deleteJson("/api/borrowings/{$borrowing->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing deleted successfully',
            ]);

        $this->assertDatabaseMissing('borrowings', ['id' => $borrowing->id]);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Database States, Transaction Rollback, Queue Dispatch)
     * ========================================================================= */

    public function test_greybox_full_borrowing_lifecycle_state_transitions(): void
    {
        Queue::fake();

        // 1. Create Pending
        $borrowing = $this->borrowingService->createBorrowing([
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pending',
        ], $this->staff->id);

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'pending',
        ]);
        $this->assertEquals(10, $this->item->fresh()->available_stock);

        // 2. Approve
        $approved = $this->borrowingService->approveBorrowing($borrowing, $this->admin->id);
        $this->assertDatabaseHas('borrowings', [
            'id' => $approved->id,
            'status' => 'dipinjam',
            'approved_by' => $this->admin->id,
        ]);
        $this->assertEquals(8, $this->item->fresh()->available_stock);
        Queue::assertPushed(SendBorrowingNotification::class);

        // 3. Extend
        $extendedDate = now()->addDays(12)->toDateString();
        $extended = $this->borrowingService->extendBorrowing($approved, $extendedDate);
        $this->assertEquals(8, $this->item->fresh()->available_stock);

        // 4. Return
        $returned = $this->borrowingService->returnBorrowing($extended);
        $this->assertDatabaseHas('borrowings', [
            'id' => $returned->id,
            'status' => 'dikembalikan',
        ]);
        $this->assertEquals(10, $this->item->fresh()->available_stock);
    }

    public function test_greybox_transaction_rollback_preserves_stock_on_failure(): void
    {
        $initialStock = $this->item->available_stock;

        try {
            $this->borrowingService->createBorrowing([
                'item_id' => $this->item->id,
                'quantity' => 15, // Exceeds available stock
                'borrow_date' => now()->toDateString(),
                'due_date' => now()->addDays(3)->toDateString(),
            ], $this->staff->id);
        } catch (\Throwable) {
            // Expected exception
        }

        // Verify stock has not changed and no orphaned record is inserted
        $this->assertEquals($initialStock, $this->item->fresh()->available_stock);
        $this->assertDatabaseMissing('borrowings', [
            'user_id' => $this->staff->id,
            'quantity' => 15,
        ]);
    }
}
