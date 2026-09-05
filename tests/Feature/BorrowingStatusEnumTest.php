<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ValueError;

class BorrowingStatusEnumTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin_enum@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff_enum@example.com',
        ]);

        $category = Category::factory()->create();
        $this->item = Item::factory()->create([
            'category_id' => $category->id,
            'stock' => 10,
            'available_stock' => 10,
        ]);
    }

    // ==========================================
    // ⚪ WHITE-BOX TESTING: Enum Structure & Logic
    // ==========================================

    public function test_whitebox_enum_cases_and_backing_values(): void
    {
        $expectedValues = [
            'pending',
            'dipinjam',
            'dikembalikan',
            'terlambat',
            'ditolak',
            'approved',
            'rejected',
        ];

        $actualValues = BorrowingStatus::values();

        $this->assertSame($expectedValues, $actualValues);
        $this->assertSame('pending', BorrowingStatus::Pending->value);
        $this->assertSame('dipinjam', BorrowingStatus::Dipinjam->value);
        $this->assertSame('dikembalikan', BorrowingStatus::Dikembalikan->value);
        $this->assertSame('terlambat', BorrowingStatus::Terlambat->value);
        $this->assertSame('ditolak', BorrowingStatus::Ditolak->value);
        $this->assertSame('approved', BorrowingStatus::Approved->value);
        $this->assertSame('rejected', BorrowingStatus::Rejected->value);

        // Test from and tryFrom
        $this->assertSame(BorrowingStatus::Dipinjam, BorrowingStatus::from('dipinjam'));
        $this->assertNull(BorrowingStatus::tryFrom('non_existent_status'));

        $this->expectException(ValueError::class);
        BorrowingStatus::from('invalid_value');
    }

    public function test_whitebox_enum_helper_methods(): void
    {
        // Test labels
        $this->assertSame('Menunggu Persetujuan', BorrowingStatus::Pending->label());
        $this->assertSame('Sedang Dipinjam', BorrowingStatus::Dipinjam->label());
        $this->assertSame('Dikembalikan', BorrowingStatus::Dikembalikan->label());
        $this->assertSame('Terlambat', BorrowingStatus::Terlambat->label());
        $this->assertSame('Ditolak', BorrowingStatus::Ditolak->label());

        // Test badge colors
        $this->assertSame('warning', BorrowingStatus::Pending->badgeColor());
        $this->assertSame('info', BorrowingStatus::Dipinjam->badgeColor());
        $this->assertSame('success', BorrowingStatus::Dikembalikan->badgeColor());
        $this->assertSame('danger', BorrowingStatus::Terlambat->badgeColor());
        $this->assertSame('secondary', BorrowingStatus::Ditolak->badgeColor());

        // Test state checkers
        $this->assertTrue(BorrowingStatus::Dipinjam->isActive());
        $this->assertTrue(BorrowingStatus::Terlambat->isActive());
        $this->assertFalse(BorrowingStatus::Pending->isActive());
        $this->assertFalse(BorrowingStatus::Dikembalikan->isActive());

        $this->assertTrue(BorrowingStatus::Dikembalikan->isFinal());
        $this->assertTrue(BorrowingStatus::Ditolak->isFinal());
        $this->assertTrue(BorrowingStatus::Rejected->isFinal());
        $this->assertFalse(BorrowingStatus::Dipinjam->isFinal());
        $this->assertFalse(BorrowingStatus::Pending->isFinal());

        $this->assertTrue(BorrowingStatus::Dipinjam->canBeReturned());
        $this->assertTrue(BorrowingStatus::Terlambat->canBeReturned());
        $this->assertFalse(BorrowingStatus::Dikembalikan->canBeReturned());

        $this->assertTrue(BorrowingStatus::Dipinjam->canBeExtended());
        $this->assertFalse(BorrowingStatus::Terlambat->canBeExtended());
        $this->assertFalse(BorrowingStatus::Dikembalikan->canBeExtended());

        $this->assertTrue(BorrowingStatus::Pending->canBeActioned());
        $this->assertFalse(BorrowingStatus::Dipinjam->canBeActioned());
    }

    // ==========================================
    // ⚫ BLACK-BOX TESTING: API Response Serialization
    // ==========================================

    public function test_blackbox_borrowing_api_returns_status_string_in_json(): void
    {
        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-TEST-001',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => now(),
            'due_date' => now()->addDays(3),
            'status' => BorrowingStatus::Dipinjam,
        ]);

        // Test unversioned
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/borrowings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'borrow_code',
                        'status',
                    ],
                ],
            ]);

        $this->assertSame('dipinjam', $response->json('data.0.status'));

        // Test versioned /v1
        $responseV1 = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/borrowings');

        $responseV1->assertOk();
        $this->assertSame('dipinjam', $responseV1->json('data.0.status'));
    }

    public function test_blackbox_update_borrowing_respects_status_restriction(): void
    {
        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-RET-001',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now()->subDays(5),
            'due_date' => now()->subDays(2),
            'return_date' => now(),
            'status' => BorrowingStatus::Dikembalikan,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/borrowings/{$borrowing->id}", [
                'notes' => 'Attempting to edit returned item',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot update returned borrowing',
            ]);
    }

    // ==========================================
    // 🔘 GREY-BOX TESTING: Eloquent Casting & Persistence
    // ==========================================

    public function test_greybox_eloquent_casts_status_attribute_to_enum(): void
    {
        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-CAST-001',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'dipinjam',
        ]);

        // Fetch fresh instance from DB
        $fresh = Borrowing::findOrFail($borrowing->id);

        $this->assertInstanceOf(BorrowingStatus::class, $fresh->status);
        $this->assertSame(BorrowingStatus::Dipinjam, $fresh->status);
        $this->assertSame('dipinjam', $fresh->status->value);
    }

    public function test_greybox_assigning_enum_persists_raw_string_in_database(): void
    {
        $borrowing = new Borrowing();
        $borrowing->borrow_code = 'BRW-ENUM-002';
        $borrowing->user_id = $this->staff->id;
        $borrowing->item_id = $this->item->id;
        $borrowing->quantity = 1;
        $borrowing->borrow_date = now();
        $borrowing->due_date = now()->addDays(5);
        $borrowing->status = BorrowingStatus::Pending;
        $borrowing->save();

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'pending',
        ]);

        // Transition status using Enum
        $borrowing->status = BorrowingStatus::Dikembalikan;
        $borrowing->save();

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'dikembalikan',
        ]);
    }

    public function test_greybox_query_scopes_work_seamlessly_with_enum(): void
    {
        // Create records with various statuses
        Borrowing::create([
            'borrow_code' => 'BRW-ACT-001',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(3),
            'status' => BorrowingStatus::Dipinjam,
        ]);

        Borrowing::create([
            'borrow_code' => 'BRW-OVD-001',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now()->subDays(10),
            'due_date' => now()->subDays(3),
            'status' => BorrowingStatus::Terlambat,
        ]);

        Borrowing::create([
            'borrow_code' => 'BRW-RET-002',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now()->subDays(5),
            'due_date' => now()->subDays(1),
            'return_date' => now(),
            'status' => BorrowingStatus::Dikembalikan,
        ]);

        $this->assertCount(1, Borrowing::active()->get());
        $this->assertCount(1, Borrowing::overdue()->get());
        $this->assertCount(1, Borrowing::returned()->get());

        $this->assertSame('BRW-ACT-001', Borrowing::active()->first()->borrow_code);
        $this->assertSame('BRW-OVD-001', Borrowing::overdue()->first()->borrow_code);
        $this->assertSame('BRW-RET-002', Borrowing::returned()->first()->borrow_code);
    }

    public function test_greybox_process_return_uses_enum_and_restores_stock(): void
    {
        $this->item->update(['available_stock' => 8]);

        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-RET-003',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => now()->subDays(2),
            'due_date' => now()->addDays(2),
            'status' => BorrowingStatus::Dipinjam,
        ]);

        $result = $borrowing->processReturn();

        $this->assertTrue($result);
        $this->assertSame(BorrowingStatus::Dikembalikan, $borrowing->fresh()->status);
        $this->assertSame(10, $this->item->fresh()->available_stock);

        // Attempting second return should fail
        $this->assertFalse($borrowing->processReturn());
    }
}
