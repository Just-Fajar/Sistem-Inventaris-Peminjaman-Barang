<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Category $categoryElectronics;
    protected Category $categoryFurniture;
    protected Item $itemLaptop;
    protected Item $itemChair;
    protected Item $itemProjector;
    protected ReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->staff = User::factory()->create([
            'name' => 'Staff User',
            'role' => 'staff',
            'email' => 'staff@example.com',
        ]);

        $this->categoryElectronics = Category::factory()->create([
            'name' => 'Elektronik',
        ]);

        $this->categoryFurniture = Category::factory()->create([
            'name' => 'Furniture',
        ]);

        $this->itemLaptop = Item::factory()->create([
            'category_id' => $this->categoryElectronics->id,
            'name' => 'Laptop Dell Latitude',
            'code' => 'ITM-2026-0001',
            'stock' => 10,
            'available_stock' => 7,
            'condition' => 'baik',
        ]);

        $this->itemChair = Item::factory()->create([
            'category_id' => $this->categoryFurniture->id,
            'name' => 'Kursi Ergonomis',
            'code' => 'ITM-2026-0002',
            'stock' => 20,
            'available_stock' => 20,
            'condition' => 'baik',
        ]);

        $this->itemProjector = Item::factory()->create([
            'category_id' => $this->categoryElectronics->id,
            'name' => 'Projector InFocus',
            'code' => 'ITM-2026-0003',
            'stock' => 2,
            'available_stock' => 0, // out of stock
            'condition' => 'rusak',
        ]);

        $this->reportService = app(ReportService::class);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Direct ReportService Logic & Aggregations)
     * ========================================================================= */

    public function test_whitebox_get_borrowing_report_filters_and_calculates_statistics(): void
    {
        // 1. Create active borrowing
        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'status' => 'dipinjam',
            'quantity' => 2,
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-10',
        ]);

        // 2. Create returned borrowing
        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemChair->id,
            'status' => 'dikembalikan',
            'quantity' => 3,
            'borrow_date' => '2026-08-15',
            'due_date' => '2026-08-25',
            'return_date' => '2026-08-24',
        ]);

        // 3. Create overdue borrowing
        Borrowing::factory()->create([
            'user_id' => $this->admin->id,
            'item_id' => $this->itemProjector->id,
            'status' => 'terlambat',
            'quantity' => 1,
            'borrow_date' => '2026-08-01',
            'due_date' => '2026-08-10',
        ]);

        // All borrowings
        $report = $this->reportService->getBorrowingReport();

        $this->assertCount(3, $report['data']);
        $this->assertEquals(3, $report['statistics']['total_borrowings']);
        $this->assertEquals(1, $report['statistics']['active_borrowings']);
        $this->assertEquals(1, $report['statistics']['returned_borrowings']);
        $this->assertEquals(1, $report['statistics']['overdue_borrowings']);
        $this->assertEquals(6, $report['statistics']['total_items_borrowed']); // 2 + 3 + 1

        // Filter by date range (only September)
        $septemberReport = $this->reportService->getBorrowingReport([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
        $this->assertCount(1, $septemberReport['data']);
        $this->assertEquals(1, $septemberReport['statistics']['total_borrowings']);

        // Filter by status (dikembalikan)
        $returnedReport = $this->reportService->getBorrowingReport(['status' => 'dikembalikan']);
        $this->assertCount(1, $returnedReport['data']);
        $this->assertEquals('dikembalikan', $returnedReport['data']->first()->status);

        // Filter by user
        $staffReport = $this->reportService->getBorrowingReport(['user_id' => $this->staff->id]);
        $this->assertCount(2, $staffReport['data']);

        // Filter by item
        $laptopReport = $this->reportService->getBorrowingReport(['item_id' => $this->itemLaptop->id]);
        $this->assertCount(1, $laptopReport['data']);
    }

    public function test_whitebox_get_item_report_calculates_stock_and_active_borrowings(): void
    {
        // Add active borrowing on Laptop
        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'status' => 'dipinjam',
            'quantity' => 2,
        ]);

        // Add returned borrowing on Laptop (should not count as active)
        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'status' => 'dikembalikan',
            'quantity' => 1,
        ]);

        $report = $this->reportService->getItemReport();

        $this->assertCount(3, $report['data']);
        $this->assertEquals(3, $report['statistics']['total_items']);
        $this->assertEquals(2, $report['statistics']['available_items']); // laptop (7) and chair (20)
        $this->assertEquals(1, $report['statistics']['out_of_stock']); // projector (0)
        $this->assertEquals(32, $report['statistics']['total_stock']); // 10 + 20 + 2
        $this->assertEquals(27, $report['statistics']['available_stock']); // 7 + 20 + 0

        $laptopData = $report['data']->firstWhere('id', $this->itemLaptop->id);
        $this->assertNotNull($laptopData);
        $this->assertEquals(1, $laptopData->active_borrowings_count);
        $this->assertEquals(2, $laptopData->borrowings_count);
    }

    public function test_whitebox_get_overdue_report_performs_atomic_batch_update(): void
    {
        // 1. Borrowing with past due date and status 'dipinjam' (should be updated to 'terlambat')
        $pastBorrowing1 = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'status' => 'dipinjam',
            'due_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        // 2. Another borrowing with past due date and status 'dipinjam'
        $pastBorrowing2 = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemChair->id,
            'status' => 'dipinjam',
            'due_date' => Carbon::today()->subDays(2)->toDateString(),
        ]);

        // 3. Already 'terlambat' borrowing
        $alreadyOverdue = Borrowing::factory()->create([
            'user_id' => $this->admin->id,
            'item_id' => $this->itemProjector->id,
            'status' => 'terlambat',
            'due_date' => Carbon::today()->subDays(10)->toDateString(),
        ]);

        // 4. Future borrowing with status 'dipinjam' (must NOT be updated)
        $futureBorrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'status' => 'dipinjam',
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        $report = $this->reportService->getOverdueReport();

        $this->assertEquals(3, $report['total']);
        $this->assertCount(3, $report['data']);

        // Check DB state: past borrowings must now have status 'terlambat'
        $this->assertDatabaseHas('borrowings', [
            'id' => $pastBorrowing1->id,
            'status' => 'terlambat',
        ]);
        $this->assertDatabaseHas('borrowings', [
            'id' => $pastBorrowing2->id,
            'status' => 'terlambat',
        ]);
        $this->assertDatabaseHas('borrowings', [
            'id' => $futureBorrowing->id,
            'status' => 'dipinjam',
        ]);
    }

    public function test_whitebox_get_monthly_report_generates_daily_breakdown(): void
    {
        // 2 borrowings on 2026-09-05
        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'borrow_date' => '2026-09-05',
            'quantity' => 2,
            'status' => 'dipinjam',
        ]);
        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemChair->id,
            'borrow_date' => '2026-09-05',
            'quantity' => 3,
            'status' => 'dipinjam',
        ]);

        // 1 borrowing on 2026-09-12
        Borrowing::factory()->create([
            'user_id' => $this->admin->id,
            'item_id' => $this->itemProjector->id,
            'borrow_date' => '2026-09-12',
            'quantity' => 1,
            'status' => 'dikembalikan',
        ]);

        // 1 borrowing in August (should not be included)
        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'borrow_date' => '2026-08-30',
            'quantity' => 1,
            'status' => 'dikembalikan',
        ]);

        $report = $this->reportService->getMonthlyReport(2026, 9);

        $this->assertEquals(2026, $report['period']['year']);
        $this->assertEquals(9, $report['period']['month']);
        $this->assertEquals('2026-09-01', $report['period']['start_date']);
        $this->assertEquals('2026-09-30', $report['period']['end_date']);

        $this->assertEquals(3, $report['statistics']['total_borrowings']);
        $this->assertEquals(2, $report['statistics']['active_borrowings']);
        $this->assertEquals(1, $report['statistics']['returned_borrowings']);
        $this->assertEquals(6, $report['statistics']['total_items_borrowed']);

        // Check daily breakdown (September has 30 days)
        $this->assertCount(30, $report['daily_breakdown']);

        $day5 = collect($report['daily_breakdown'])->firstWhere('date', '2026-09-05');
        $this->assertNotNull($day5);
        $this->assertEquals(2, $day5['count']);
        $this->assertEquals(5, $day5['quantity']);

        $day12 = collect($report['daily_breakdown'])->firstWhere('date', '2026-09-12');
        $this->assertNotNull($day12);
        $this->assertEquals(1, $day12['count']);
        $this->assertEquals(1, $day12['quantity']);

        $day1 = collect($report['daily_breakdown'])->firstWhere('date', '2026-09-01');
        $this->assertNotNull($day1);
        $this->assertEquals(0, $day1['count']);
        $this->assertEquals(0, $day1['quantity']);
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (HTTP Endpoints, Auth, Validation & Response Formats)
     * ========================================================================= */

    public function test_blackbox_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/reports/borrowings')->assertStatus(401);
        $this->getJson('/api/reports/items')->assertStatus(401);
        $this->getJson('/api/reports/overdue')->assertStatus(401);
        $this->getJson('/api/reports/monthly')->assertStatus(401);
        $this->getJson('/api/reports/export/borrowings/pdf')->assertStatus(401);
        $this->getJson('/api/reports/export/borrowings/excel')->assertStatus(401);
    }

    public function test_blackbox_authenticated_user_can_get_borrowings_report(): void
    {
        Sanctum::actingAs($this->admin);

        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'status' => 'dipinjam',
        ]);

        $response = $this->getJson('/api/reports/borrowings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'borrow_code',
                        'status',
                        'user',
                        'item',
                    ],
                ],
                'statistics' => [
                    'total_borrowings',
                    'active_borrowings',
                    'returned_borrowings',
                    'overdue_borrowings',
                    'total_items_borrowed',
                ],
                'filters',
            ]);
    }

    public function test_blackbox_borrowings_report_validates_inputs(): void
    {
        Sanctum::actingAs($this->admin);

        // Invalid date order: end_date < start_date
        $response = $this->getJson('/api/reports/borrowings?start_date=2026-09-15&end_date=2026-09-01');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);

        // Invalid status enum
        $response2 = $this->getJson('/api/reports/borrowings?status=batal');
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Invalid user_id
        $response3 = $this->getJson('/api/reports/borrowings?user_id=99999');
        $response3->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_blackbox_authenticated_user_can_get_items_report(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'stock',
                        'available_stock',
                        'category',
                        'borrowings_count',
                        'active_borrowings_count',
                    ],
                ],
                'statistics' => [
                    'total_items',
                    'available_items',
                    'out_of_stock',
                    'total_stock',
                    'available_stock',
                ],
            ]);
    }

    public function test_blackbox_authenticated_user_can_get_overdue_report(): void
    {
        Sanctum::actingAs($this->admin);

        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'status' => 'dipinjam',
            'due_date' => Carbon::today()->subDays(3)->toDateString(),
        ]);

        $response = $this->getJson('/api/reports/overdue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'borrow_code',
                        'status',
                        'user',
                        'item',
                    ],
                ],
                'total',
            ]);

        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('terlambat', $response->json('data.0.status'));
    }

    public function test_blackbox_authenticated_user_can_get_monthly_report(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/monthly?year=2026&month=9');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period' => [
                    'year',
                    'month',
                    'start_date',
                    'end_date',
                ],
                'statistics' => [
                    'total_borrowings',
                    'active_borrowings',
                    'returned_borrowings',
                    'overdue_borrowings',
                    'total_items_borrowed',
                ],
                'daily_breakdown' => [
                    '*' => [
                        'date',
                        'count',
                        'quantity',
                    ],
                ],
                'borrowings',
            ]);
    }

    public function test_blackbox_monthly_report_validates_year_and_month(): void
    {
        Sanctum::actingAs($this->admin);

        // Invalid month (> 12)
        $response = $this->getJson('/api/reports/monthly?month=15');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['month']);

        // Invalid year (< 2000)
        $response2 = $this->getJson('/api/reports/monthly?year=1999');
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['year']);
    }

    public function test_blackbox_authenticated_user_can_export_borrowings_pdf(): void
    {
        Sanctum::actingAs($this->admin);

        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'status' => 'dipinjam',
        ]);

        $response = $this->get('/api/reports/export/borrowings/pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_blackbox_authenticated_user_can_export_borrowings_excel(): void
    {
        Sanctum::actingAs($this->admin);

        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->itemLaptop->id,
            'status' => 'dipinjam',
        ]);

        $response = $this->get('/api/reports/export/borrowings/excel');

        $response->assertStatus(200);
        $this->assertStringContainsString('vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Database Query Efficiency, Eager Loading & N+1 Prevention)
     * ========================================================================= */

    public function test_greybox_overdue_report_executes_single_batch_update_without_n_plus_one(): void
    {
        Sanctum::actingAs($this->admin);

        // Create 12 overdue records that need status transition
        for ($i = 0; $i < 12; $i++) {
            Borrowing::factory()->create([
                'user_id' => $this->staff->id,
                'item_id' => $this->itemLaptop->id,
                'status' => 'dipinjam',
                'due_date' => Carbon::today()->subDays($i + 1)->toDateString(),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/reports/overdue');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Extract UPDATE queries on borrowings table
        $updateQueries = array_filter($queries, function ($q) {
            return stripos($q['query'], 'update') !== false && stripos($q['query'], 'borrowings') !== false;
        });

        // Assert exactly 1 batch UPDATE query was executed (O(1) instead of 12 separate queries)
        $this->assertCount(1, $updateQueries, 'Expected exactly 1 atomic batch UPDATE query, found ' . count($updateQueries));

        // Ensure total queries is small and bounded (auth lookup + 1 batch update + 1 select with eager loading)
        $this->assertLessThanOrEqual(5, count($queries), 'Total queries exceeded threshold, possible query leak.');
    }

    public function test_greybox_borrowing_report_eager_loads_relations_without_n_plus_one(): void
    {
        Sanctum::actingAs($this->admin);

        // Create 10 borrowings
        for ($i = 0; $i < 10; $i++) {
            Borrowing::factory()->create([
                'user_id' => $this->staff->id,
                'item_id' => $this->itemLaptop->id,
                'status' => 'dipinjam',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/reports/borrowings');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Check that queries include eager loading for user, item, category, approver
        // Total queries should be constant: sanctum auth + borrowings select + users + items + categories + approvers
        $this->assertLessThanOrEqual(7, count($queries), 'Eager loading missing or leaking queries.');
    }

    public function test_greybox_backward_compatibility_v1_and_root_routes(): void
    {
        Sanctum::actingAs($this->admin);

        // Test root route /api/reports/borrowings
        $resRoot = $this->getJson('/api/reports/borrowings');
        $resRoot->assertStatus(200);

        // Test v1 prefix route /api/v1/reports/borrowings
        $resV1 = $this->getJson('/api/v1/reports/borrowings');
        $resV1->assertStatus(200);

        $this->assertEquals($resRoot->json('statistics'), $resV1->json('statistics'));
    }
}
