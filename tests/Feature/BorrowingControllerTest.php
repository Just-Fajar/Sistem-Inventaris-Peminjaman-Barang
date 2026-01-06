<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $staff;
    protected $item;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->item = Item::factory()->create(['stock' => 10, 'available_stock' => 10]);
    }

    public function test_user_can_list_their_borrowings(): void
    {
        Borrowing::factory()->count(3)->create(['user_id' => $this->staff->id]);
        Borrowing::factory()->count(2)->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->staff)
            ->getJson('/api/borrowings');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_see_all_borrowings(): void
    {
        Borrowing::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/borrowings');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_user_can_create_borrowing_request(): void
    {
        $response = $this->actingAs($this->staff)
            ->postJson('/api/borrowings', [
                'item_id' => $this->item->id,
                'quantity' => 2,
                'borrow_date' => Carbon::today()->toDateString(),
                'due_date' => Carbon::today()->addDays(7)->toDateString(),
                'notes' => 'Test borrowing',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('borrowings', [
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_borrow_more_than_available_stock(): void
    {
        $response = $this->actingAs($this->staff)
            ->postJson('/api/borrowings', [
                'item_id' => $this->item->id,
                'quantity' => 15,
                'borrow_date' => Carbon::today()->toDateString(),
                'due_date' => Carbon::today()->addDays(7)->toDateString(),
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_approve_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/borrowings/{$borrowing->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);
    }

    public function test_staff_cannot_approve_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staff)
            ->putJson("/api/borrowings/{$borrowing->id}/approve");

        $response->assertStatus(403);
    }

    public function test_stock_decreases_on_approval(): void
    {
        $initialStock = $this->item->available_stock;
        
        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'quantity' => 3,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/borrowings/{$borrowing->id}/approve");

        $this->item->refresh();
        $this->assertEquals($initialStock - 3, $this->item->available_stock);
    }

    public function test_user_can_return_borrowed_item(): void
    {
        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'quantity' => 2,
            'status' => 'approved',
        ]);

        $this->item->update(['available_stock' => 8]);

        $response = $this->actingAs($this->staff)
            ->putJson("/api/borrowings/{$borrowing->id}/return", [
                'return_condition' => 'good',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'returned',
        ]);
    }

    public function test_stock_increases_on_return(): void
    {
        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'quantity' => 3,
            'status' => 'approved',
        ]);

        $this->item->update(['available_stock' => 7]);
        $stockBeforeReturn = $this->item->available_stock;

        $this->actingAs($this->staff)
            ->putJson("/api/borrowings/{$borrowing->id}/return", [
                'return_condition' => 'good',
            ]);

        $this->item->refresh();
        $this->assertEquals($stockBeforeReturn + 3, $this->item->available_stock);
    }

    public function test_can_extend_due_date(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'status' => 'approved',
            'due_date' => Carbon::today()->addDays(7),
        ]);

        $newDueDate = Carbon::today()->addDays(14)->toDateString();

        $response = $this->actingAs($this->staff)
            ->putJson("/api/borrowings/{$borrowing->id}/extend", [
                'due_date' => $newDueDate,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'due_date' => $newDueDate,
        ]);
    }

    public function test_can_search_borrowings_by_code(): void
    {
        $borrowing = Borrowing::factory()->create([
            'code' => 'BRW-2024-001',
            'user_id' => $this->admin->id,
        ]);
        Borrowing::factory()->create([
            'code' => 'BRW-2024-002',
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?search=BRW-2024-001');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_borrowings_by_status(): void
    {
        Borrowing::factory()->create(['status' => 'pending', 'user_id' => $this->admin->id]);
        Borrowing::factory()->create(['status' => 'approved', 'user_id' => $this->admin->id]);
        Borrowing::factory()->create(['status' => 'returned', 'user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?status=approved');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_detect_overdue_borrowings(): void
    {
        $overdueBorrowing = Borrowing::factory()->create([
            'status' => 'approved',
            'due_date' => Carbon::yesterday(),
            'user_id' => $this->admin->id,
        ]);

        $normalBorrowing = Borrowing::factory()->create([
            'status' => 'approved',
            'due_date' => Carbon::tomorrow(),
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?overdue=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_approve_already_approved_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/borrowings/{$borrowing->id}/approve");

        $response->assertStatus(400);
    }

    public function test_admin_can_reject_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/borrowings/{$borrowing->id}/reject");

        $response->assertStatus(200);
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'rejected',
        ]);
    }
}
