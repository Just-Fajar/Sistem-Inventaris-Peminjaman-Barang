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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingRejectWithNoteTest extends TestCase
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
        Queue::fake();

        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->category = Category::factory()->create(['name' => 'Elektronik']);
        $this->item = Item::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Lenovo ThinkPad X1',
            'code' => 'ITM-TEST-002',
            'stock' => 10,
            'available_stock' => 10,
            'condition' => ItemCondition::Baik,
        ]);

        $this->borrowingService = app(BorrowingService::class);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Direct Service Logic, Rejection Notes & Boundaries)
     * ========================================================================= */

    public function test_whitebox_reject_borrowing_saves_rejection_note(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => BorrowingStatus::Pending,
        ]);

        $rejectionReason = 'Barang sedang dipersiapkan untuk audit tahunan.';
        $rejected = $this->borrowingService->rejectBorrowing($borrowing, $rejectionReason);

        $this->assertInstanceOf(Borrowing::class, $rejected);
        $this->assertEquals(BorrowingStatus::Rejected, $rejected->status);
        $this->assertEquals($rejectionReason, $rejected->rejection_note);

        // Stock must remain unchanged
        $this->assertEquals(10, $this->item->fresh()->available_stock);
    }

    public function test_whitebox_reject_borrowing_without_note_sets_rejection_note_null(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Pending,
        ]);

        $rejected = $this->borrowingService->rejectBorrowing($borrowing);

        $this->assertEquals(BorrowingStatus::Rejected, $rejected->status);
        $this->assertNull($rejected->rejection_note);
        $this->assertEquals(10, $this->item->fresh()->available_stock);
    }

    public function test_whitebox_reject_borrowing_throws_when_already_processed(): void
    {
        $alreadyApproved = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Dipinjam,
            'approved_by' => $this->admin->id,
        ]);

        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Peminjaman sudah diproses.');

        $this->borrowingService->rejectBorrowing($alreadyApproved, 'Tidak bisa ditolak karena sudah disetujui');
    }

    public function test_whitebox_reject_borrowing_throws_when_already_rejected(): void
    {
        $alreadyRejected = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Rejected,
            'rejection_note' => 'Penolakan pertama',
        ]);

        $this->expectException(BorrowingException::class);
        $this->expectExceptionMessage('Peminjaman sudah diproses.');

        $this->borrowingService->rejectBorrowing($alreadyRejected, 'Penolakan kedua');
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (API HTTP Endpoints, Validation & Permissions)
     * ========================================================================= */

    public function test_blackbox_admin_can_reject_borrowing_with_rejection_note(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => BorrowingStatus::Pending,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject", [
            'rejection_note' => 'Unit tidak tersedia untuk peminjaman luar kota.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing rejected successfully',
                'data' => [
                    'id' => $borrowing->id,
                    'status' => 'rejected',
                    'rejection_note' => 'Unit tidak tersedia untuk peminjaman luar kota.',
                ],
            ]);
    }

    public function test_blackbox_admin_can_reject_borrowing_without_rejection_note(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Pending,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject", []);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing rejected successfully',
                'data' => [
                    'id' => $borrowing->id,
                    'status' => 'rejected',
                    'rejection_note' => null,
                ],
            ]);
    }

    public function test_blackbox_staff_cannot_reject_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Pending,
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject", [
            'rejection_note' => 'Staff coba menolak',
        ]);

        $response->assertStatus(403);
    }

    public function test_blackbox_unauthenticated_request_cannot_reject(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Pending,
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject", [
            'rejection_note' => 'Guest coba menolak',
        ]);

        $response->assertStatus(401);
    }

    public function test_blackbox_reject_validates_rejection_note_max_length(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Pending,
        ]);

        Sanctum::actingAs($this->admin);

        $longNote = str_repeat('A', 501);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject", [
            'rejection_note' => $longNote,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_note']);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Database State & Invariance Verification)
     * ========================================================================= */

    public function test_greybox_database_persistence_and_stock_invariance_on_rejection(): void
    {
        $initialStock = $this->item->available_stock;

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 3,
            'status' => BorrowingStatus::Pending,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject", [
            'rejection_note' => 'Alasan spesifik penolakan barang.',
        ]);

        $response->assertStatus(200);

        // Verify database persistence
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'rejected',
            'rejection_note' => 'Alasan spesifik penolakan barang.',
        ]);

        // Stock in database MUST NOT change
        $this->assertDatabaseHas('items', [
            'id' => $this->item->id,
            'available_stock' => $initialStock,
        ]);
    }

    public function test_greybox_cannot_approve_already_rejected_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => BorrowingStatus::Rejected,
            'rejection_note' => 'Sudah ditolak sebelumnya.',
        ]);

        Sanctum::actingAs($this->admin);

        $approveResponse = $this->postJson("/api/borrowings/{$borrowing->id}/approve");

        $approveResponse->assertStatus(400)
            ->assertJson([
                'message' => 'Peminjaman sudah diproses atau sudah disetujui.',
            ]);

        // Status remains rejected
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'rejected',
            'rejection_note' => 'Sudah ditolak sebelumnya.',
        ]);
    }

    public function test_greybox_cannot_reject_twice_via_api(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Pending,
        ]);

        Sanctum::actingAs($this->admin);

        // First reject: 200
        $res1 = $this->postJson("/api/borrowings/{$borrowing->id}/reject", [
            'rejection_note' => 'Pertama',
        ]);
        $res1->assertStatus(200);

        // Second reject: 400
        $res2 = $this->postJson("/api/borrowings/{$borrowing->id}/reject", [
            'rejection_note' => 'Kedua',
        ]);
        $res2->assertStatus(400)
            ->assertJson([
                'message' => 'Peminjaman sudah diproses.',
            ]);
    }
}
