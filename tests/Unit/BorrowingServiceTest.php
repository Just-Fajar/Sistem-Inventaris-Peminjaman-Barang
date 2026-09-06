<?php

namespace Tests\Unit;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use App\Models\Item;
use App\Models\User;
use App\Services\BorrowingService;
use App\Services\ItemService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BorrowingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $borrowingService;
    protected $item;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $itemService = new ItemService();
        $this->borrowingService = new BorrowingService($itemService);
        $this->item = Item::factory()->create(['stock' => 10, 'available_stock' => 10]);
        $this->user = User::factory()->create();
    }

    public function test_can_generate_unique_borrowing_code(): void
    {
        $code1 = $this->borrowingService->generateBorrowingCode();
        Borrowing::factory()->create(['borrow_code' => $code1]);
        $code2 = $this->borrowingService->generateBorrowingCode();

        $this->assertStringStartsWith('BRW-', $code1);
        $this->assertNotEquals($code1, $code2);
    }

    public function test_can_create_borrowing_request(): void
    {
        $data = [
            'user_id' => $this->user->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(7)->toDateString(),
            'notes' => 'Test borrowing',
        ];

        $borrowing = $this->borrowingService->createBorrowing($data, $this->user->id);

        $this->assertEquals(BorrowingStatus::Pending, $borrowing->status);
        $this->assertEquals(2, $borrowing->quantity);
        $this->assertStringStartsWith('BRW-', $borrowing->code);
    }

    public function test_can_approve_borrowing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'quantity' => 3,
            'status' => 'pending',
        ]);

        $approved = $this->borrowingService->approveBorrowing($borrowing, $admin->id);

        $this->assertEquals(BorrowingStatus::Dipinjam, $approved->status);
        $this->assertEquals($admin->id, $approved->approved_by);
    }

    public function test_stock_decreases_when_approved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $initialStock = $this->item->available_stock;
        
        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'quantity' => 4,
            'status' => 'pending',
        ]);

        $this->borrowingService->approveBorrowing($borrowing, $admin->id);
        $this->item->refresh();

        $this->assertEquals($initialStock - 4, $this->item->available_stock);
    }

    public function test_can_return_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => 'dipinjam',
        ]);

        $this->item->update(['available_stock' => 8]);

        $returned = $this->borrowingService->returnBorrowing($borrowing);

        $this->assertEquals(BorrowingStatus::Dikembalikan, $returned->status);
        $this->assertNotNull($returned->return_date);
    }

    public function test_stock_increases_when_returned(): void
    {
        $borrowing = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'quantity' => 3,
            'status' => 'dipinjam',
        ]);

        $this->item->update(['available_stock' => 7]);
        $stockBeforeReturn = $this->item->available_stock;

        $this->borrowingService->returnBorrowing($borrowing);
        $this->item->refresh();

        $this->assertEquals($stockBeforeReturn + 3, $this->item->available_stock);
    }

    public function test_can_extend_due_date(): void
    {
        $borrowing = Borrowing::factory()->create([
            'status' => 'dipinjam',
            'due_date' => Carbon::today()->addDays(7),
        ]);

        $newDueDate = Carbon::today()->addDays(14)->toDateString();
        $extended = $this->borrowingService->extendBorrowing($borrowing, $newDueDate);

        $this->assertEquals($newDueDate, $extended->due_date->toDateString());
    }

    public function test_can_check_overdue_borrowings(): void
    {
        Borrowing::factory()->create([
            'status' => 'dipinjam',
            'due_date' => Carbon::yesterday(),
        ]);

        Borrowing::factory()->create([
            'status' => 'dipinjam',
            'due_date' => Carbon::tomorrow(),
        ]);

        $count = $this->borrowingService->checkOverdueBorrowings();

        $this->assertEquals(1, $count);
    }
}
