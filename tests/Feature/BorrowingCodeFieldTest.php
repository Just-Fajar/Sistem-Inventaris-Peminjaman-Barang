<?php

namespace Tests\Feature;

use App\Exports\BorrowingsExport;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\BorrowingService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowingCodeFieldTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);

        $category = Category::factory()->create();
        $this->item = Item::factory()->create([
            'category_id' => $category->id,
            'stock' => 10,
            'available_stock' => 10,
            'condition' => 'baik',
        ]);
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (API Search, JSON Contract & Status Codes)
     * ========================================================================= */

    public function test_blackbox_search_borrowing_by_code_does_not_crash(): void
    {
        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-20260904-0001',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'dipinjam',
        ]);

        // Search using borrow_code prefix
        $response = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?search=BRW-20260904');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.borrow_code', 'BRW-20260904-0001')
            ->assertJsonPath('data.0.code', 'BRW-20260904-0001');
    }

    public function test_blackbox_borrowing_response_contains_both_code_and_borrow_code(): void
    {
        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-20260904-0002',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'dipinjam',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/borrowings/{$borrowing->id}");

        $response->assertStatus(200);

        $json = $response->json();
        // Support either wrapped resource or direct model JSON
        $data = $json['data'] ?? $json;

        $this->assertEquals('BRW-20260904-0002', $data['borrow_code']);
        $this->assertEquals('BRW-20260904-0002', $data['code']);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Model Accessor, Serialization & Logic)
     * ========================================================================= */

    public function test_whitebox_borrowing_model_code_accessor_returns_borrow_code(): void
    {
        $borrowing = new Borrowing([
            'borrow_code' => 'BRW-20260904-9999',
        ]);

        // Direct property access via Eloquent accessor getCodeAttribute()
        $this->assertEquals('BRW-20260904-9999', $borrowing->code);
        $this->assertEquals('BRW-20260904-9999', $borrowing->borrow_code);
    }

    public function test_whitebox_borrowing_appends_code_in_array_and_json(): void
    {
        $borrowing = new Borrowing([
            'borrow_code' => 'BRW-20260904-5555',
        ]);

        $array = $borrowing->toArray();
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('borrow_code', $array);
        $this->assertEquals('BRW-20260904-5555', $array['code']);
    }

    public function test_whitebox_borrowing_service_generate_code_reads_borrow_code(): void
    {
        Borrowing::create([
            'borrow_code' => 'BRW-' . now()->format('Ymd') . '-0005',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'dipinjam',
        ]);

        $service = app(BorrowingService::class);
        $newCode = $service->generateBorrowingCode();

        $expectedCode = 'BRW-' . now()->format('Ymd') . '-0006';
        $this->assertEquals($expectedCode, $newCode);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Database Integrity, Search Query & Report/Export Mapping)
     * ========================================================================= */

    public function test_greybox_search_matches_exact_database_borrow_code(): void
    {
        // Insert 2 records with distinct codes directly in database
        Borrowing::create([
            'borrow_code' => 'BRW-ALPHA-1234',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'dipinjam',
        ]);

        Borrowing::create([
            'borrow_code' => 'BRW-BETA-5678',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'dipinjam',
        ]);

        // Search for ALPHA
        $response = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?search=ALPHA');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.borrow_code', 'BRW-ALPHA-1234');

        $this->assertDatabaseHas('borrowings', [
            'borrow_code' => 'BRW-ALPHA-1234',
        ]);
    }

    public function test_greybox_export_and_report_service_contain_borrow_code(): void
    {
        $borrowing = Borrowing::create([
            'borrow_code' => 'BRW-EXP-001',
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'dipinjam',
        ]);

        // Test BorrowingsExport mapping
        $export = new BorrowingsExport([]);
        $mapped = $export->map($borrowing);

        $this->assertEquals('BRW-EXP-001', $mapped[0]);

        // Test ReportService
        $reportService = new ReportService();
        $overdueData = $reportService->getOverdueBorrowings();

        // Ensure report service didn't throw and structure is correct
        $this->assertIsArray($overdueData);
    }
}
