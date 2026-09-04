<?php

namespace Tests\Feature;

use App\Jobs\SendBorrowingNotification;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Category $category;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->category = Category::factory()->create();

        $this->item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 10,
            'available_stock' => 10,
            'condition' => 'baik',
        ]);
    }

    // ==========================================
    // 1. BLACK-BOX TESTING (API & Authorization)
    // ==========================================

    public function test_blackbox_admin_can_approve_pending_borrowing(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 2,
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing approved successfully',
                'data' => [
                    'id' => $borrowing->id,
                    'status' => 'dipinjam',
                    'approved_by' => $this->admin->id,
                ],
            ]);

        $this->assertNotNull($response->json('data.approved_at'));
    }

    public function test_blackbox_staff_cannot_approve_borrowing(): void
    {
        Sanctum::actingAs($this->staff);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/approve");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized. Admin access required.',
            ]);
    }

    public function test_blackbox_cannot_approve_already_approved_borrowing(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Using PUT for compatibility
        $response = $this->putJson("/api/borrowings/{$borrowing->id}/approve");

        $response->assertStatus(400);
    }

    public function test_blackbox_admin_can_reject_pending_borrowing(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'pending',
        ]);

        $response = $this->putJson("/api/borrowings/{$borrowing->id}/reject");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing rejected successfully',
                'data' => [
                    'id' => $borrowing->id,
                    'status' => 'rejected',
                ],
            ]);
    }

    public function test_blackbox_staff_cannot_reject_borrowing(): void
    {
        Sanctum::actingAs($this->staff);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject");

        $response->assertStatus(403);
    }

    public function test_blackbox_cannot_reject_already_processed_borrowing(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject");

        $response->assertStatus(400);
    }

    // ==========================================
    // 2. WHITE-BOX TESTING (Internal Logic & Stock)
    // ==========================================

    public function test_whitebox_approval_deducts_item_stock_within_transaction(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 4,
            'status' => 'pending',
        ]);

        $initialStock = $this->item->available_stock; // 10

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/approve");
        $response->assertStatus(200);

        $this->assertEquals($initialStock - 4, $this->item->fresh()->available_stock); // 6
    }

    public function test_whitebox_approval_fails_safely_if_stock_insufficient(): void
    {
        Sanctum::actingAs($this->admin);

        // Reduce available stock to 1
        $this->item->update(['available_stock' => 1]);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 3, // Needs 3, only 1 available
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Stok barang tidak mencukupi untuk disetujui.',
                'available_stock' => 1,
            ]);

        // State integrity: borrowing remains pending and stock untouched
        $this->assertEquals('pending', $borrowing->fresh()->status);
        $this->assertNull($borrowing->fresh()->approved_by);
        $this->assertEquals(1, $this->item->fresh()->available_stock);
    }

    public function test_whitebox_rejection_does_not_modify_item_stock(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 3,
            'status' => 'pending',
        ]);

        $stockBefore = $this->item->available_stock;

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject");
        $response->assertStatus(200);

        $this->assertEquals($stockBefore, $this->item->fresh()->available_stock);
    }

    // ==========================================
    // 3. GREY-BOX TESTING (DB State & Events)
    // ==========================================

    public function test_greybox_database_state_transitions_for_approval_lifecycle(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 2,
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'pending',
            'approved_by' => null,
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/approve");
        $response->assertStatus(200);

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'dipinjam',
            'approved_by' => $this->admin->id,
        ]);

        $freshBorrowing = Borrowing::find($borrowing->id);
        $this->assertNotNull($freshBorrowing->approved_at);
    }

    public function test_greybox_notification_dispatched_upon_approval(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/approve");
        $response->assertStatus(200);

        Queue::assertPushed(SendBorrowingNotification::class, function ($job) use ($borrowing) {
            return $job->borrowing->id === $borrowing->id && $job->notificationType === 'approved';
        });
    }

    public function test_greybox_both_post_and_put_work_for_approve_and_reject(): void
    {
        Sanctum::actingAs($this->admin);

        // Test POST approve
        $b1 = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);
        $this->postJson("/api/borrowings/{$b1->id}/approve")->assertStatus(200);

        // Test PUT approve
        $b2 = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);
        $this->putJson("/api/borrowings/{$b2->id}/approve")->assertStatus(200);

        // Test POST reject
        $b3 = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);
        $this->postJson("/api/borrowings/{$b3->id}/reject")->assertStatus(200);

        // Test PUT reject
        $b4 = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);
        $this->putJson("/api/borrowings/{$b4->id}/reject")->assertStatus(200);
    }
}
