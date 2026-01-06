<?php

namespace Tests\Unit;

use App\Models\Borrowing;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrowing_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $borrowing = Borrowing::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $borrowing->user);
        $this->assertEquals($user->id, $borrowing->user->id);
    }

    public function test_borrowing_belongs_to_item(): void
    {
        $item = Item::factory()->create();
        $borrowing = Borrowing::factory()->create(['item_id' => $item->id]);

        $this->assertInstanceOf(Item::class, $borrowing->item);
        $this->assertEquals($item->id, $borrowing->item->id);
    }

    public function test_borrowing_belongs_to_approver(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $borrowing = Borrowing::factory()->create(['approved_by' => $admin->id]);

        $this->assertInstanceOf(User::class, $borrowing->approver);
        $this->assertEquals($admin->id, $borrowing->approver->id);
    }

    public function test_is_overdue_returns_true_when_past_due_date(): void
    {
        $borrowing = Borrowing::factory()->create([
            'status' => 'approved',
            'due_date' => Carbon::yesterday(),
        ]);

        $this->assertTrue($borrowing->isOverdue());
    }

    public function test_is_overdue_returns_false_when_not_past_due_date(): void
    {
        $borrowing = Borrowing::factory()->create([
            'status' => 'approved',
            'due_date' => Carbon::tomorrow(),
        ]);

        $this->assertFalse($borrowing->isOverdue());
    }

    public function test_is_overdue_returns_false_when_returned(): void
    {
        $borrowing = Borrowing::factory()->create([
            'status' => 'returned',
            'due_date' => Carbon::yesterday(),
        ]);

        $this->assertFalse($borrowing->isOverdue());
    }

    public function test_days_until_due_returns_correct_count(): void
    {
        $borrowing = Borrowing::factory()->create([
            'due_date' => Carbon::today()->addDays(5),
        ]);

        $this->assertEquals(5, $borrowing->daysUntilDue());
    }

    public function test_days_until_due_returns_negative_when_overdue(): void
    {
        $borrowing = Borrowing::factory()->create([
            'due_date' => Carbon::today()->subDays(3),
        ]);

        $this->assertEquals(-3, $borrowing->daysUntilDue());
    }

    public function test_scope_pending_filters_pending_borrowings(): void
    {
        Borrowing::factory()->create(['status' => 'pending']);
        Borrowing::factory()->create(['status' => 'approved']);
        Borrowing::factory()->create(['status' => 'pending']);

        $pending = Borrowing::pending()->get();

        $this->assertEquals(2, $pending->count());
    }

    public function test_scope_approved_filters_approved_borrowings(): void
    {
        Borrowing::factory()->create(['status' => 'approved']);
        Borrowing::factory()->create(['status' => 'pending']);
        Borrowing::factory()->create(['status' => 'approved']);

        $approved = Borrowing::approved()->get();

        $this->assertEquals(2, $approved->count());
    }

    public function test_scope_overdue_filters_overdue_borrowings(): void
    {
        Borrowing::factory()->create([
            'status' => 'approved',
            'due_date' => Carbon::yesterday(),
        ]);
        Borrowing::factory()->create([
            'status' => 'approved',
            'due_date' => Carbon::tomorrow(),
        ]);
        Borrowing::factory()->create([
            'status' => 'approved',
            'due_date' => Carbon::today()->subDays(5),
        ]);

        $overdue = Borrowing::overdue()->get();

        $this->assertEquals(2, $overdue->count());
    }

    public function test_casts_dates_correctly(): void
    {
        $borrowing = Borrowing::factory()->create([
            'borrow_date' => '2024-01-15',
            'due_date' => '2024-01-22',
        ]);

        $this->assertInstanceOf(Carbon::class, $borrowing->borrow_date);
        $this->assertInstanceOf(Carbon::class, $borrowing->due_date);
    }

    public function test_fillable_attributes_are_mass_assignable(): void
    {
        $data = [
            'code' => 'BRW-2024-001',
            'user_id' => 1,
            'item_id' => 1,
            'quantity' => 2,
            'status' => 'pending',
            'borrow_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(7),
        ];

        $borrowing = new Borrowing($data);

        $this->assertEquals('BRW-2024-001', $borrowing->code);
        $this->assertEquals(2, $borrowing->quantity);
        $this->assertEquals('pending', $borrowing->status);
    }
}
