<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingIndexNPlusOneTest extends TestCase
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
            'stock' => 20,
            'available_stock' => 20,
            'condition' => 'baik',
        ]);
    }

    // ==========================================
    // 1. BLACK-BOX TESTING (API & Contract)
    // ==========================================

    public function test_blackbox_user_can_paginate_borrowings_normally(): void
    {
        Sanctum::actingAs($this->admin);

        Borrowing::factory()->count(10)->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
        ]);

        $response = $this->getJson('/api/borrowings?per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'borrow_code',
                        'status',
                        'user',
                        'item',
                    ],
                ],
                'per_page',
                'total',
            ]);

        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(10, $response->json('total'));
    }

    public function test_blackbox_overdue_filter_returns_all_overdue_borrowings(): void
    {
        Sanctum::actingAs($this->admin);

        // 1 overdue borrowing (due yesterday)
        $overdue = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
            'borrow_date' => Carbon::today()->subDays(5)->toDateString(),
            'due_date' => Carbon::today()->subDays(1)->toDateString(),
        ]);

        // 1 on-time active borrowing (due in 5 days)
        $active = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
            'borrow_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        $response = $this->getJson('/api/borrowings?overdue=true');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($overdue->id, $ids);
        $this->assertNotContains($active->id, $ids);
    }

    public function test_blackbox_pagination_respects_custom_per_page(): void
    {
        Sanctum::actingAs($this->admin);

        Borrowing::factory()->count(8)->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
        ]);

        $response = $this->getJson('/api/borrowings?per_page=3');

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
        $this->assertEquals(8, $response->json('total'));
        $this->assertEquals(3, $response->json('last_page'));
    }

    // ==========================================
    // 2. WHITE-BOX TESTING (Query Performance & Count)
    // ==========================================

    public function test_whitebox_index_query_count_is_constant_regardless_of_overdue_items(): void
    {
        Sanctum::actingAs($this->admin);

        // Setup: Create 15 overdue borrowings
        for ($i = 0; $i < 15; $i++) {
            Borrowing::factory()->create([
                'item_id' => $this->item->id,
                'user_id' => $this->staff->id,
                'status' => 'dipinjam',
                'borrow_date' => Carbon::today()->subDays(10)->toDateString(),
                'due_date' => Carbon::today()->subDays(2)->toDateString(),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/borrowings?per_page=15');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Check the queries executed:
        // In F11, mutation was removed from index() and delegated to scheduled command.
        // Index GET request is pure read-only with ZERO UPDATE queries!
        $updateQueries = array_filter($queries, function ($q) {
            return stripos($q['query'], 'update') !== false && stripos($q['query'], 'borrowings') !== false;
        });

        // Assert 0 update queries during index read
        $this->assertCount(0, $updateQueries, 'Expected 0 UPDATE queries during index read, found ' . count($updateQueries));

        // Ensure total queries is small and constant (count + select limit + eager loads)
        $this->assertLessThanOrEqual(5, count($queries), 'Total queries exceeded threshold, possible query leak.');
    }

    public function test_whitebox_artisan_command_updates_overdue_borrowings(): void
    {
        Borrowing::factory()->count(3)->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
            'borrow_date' => Carbon::today()->subDays(7)->toDateString(),
            'due_date' => Carbon::today()->subDays(1)->toDateString(),
        ]);

        // Test check-overdue signature
        $this->artisan('borrowings:check-overdue')
            ->expectsOutputToContain('Total borrowings updated to \'terlambat\': 3')
            ->assertSuccessful();

        $this->assertEquals(0, Borrowing::where('status', 'dipinjam')->where('due_date', '<', Carbon::today())->count());
        $this->assertEquals(3, Borrowing::where('status', 'terlambat')->count());

        // Test alias update-overdue signature
        Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
            'borrow_date' => Carbon::today()->subDays(5)->toDateString(),
            'due_date' => Carbon::today()->subDays(2)->toDateString(),
        ]);

        $this->artisan('borrowings:update-overdue')
            ->expectsOutputToContain('Total borrowings updated to \'terlambat\': 1')
            ->assertSuccessful();
    }

    // ==========================================
    // 3. GREY-BOX TESTING (Database State & Transition Integrity)
    // ==========================================

    public function test_greybox_batch_overdue_update_only_affects_eligible_records(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Eligible overdue borrowing (status = dipinjam, past due)
        $eligible = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
            'borrow_date' => Carbon::today()->subDays(10)->toDateString(),
            'due_date' => Carbon::today()->subDays(3)->toDateString(),
        ]);

        // 2. Active non-overdue borrowing (status = dipinjam, future due date)
        $notOverdue = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
            'borrow_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        // 3. Already returned borrowing (status = dikembalikan, past due date)
        $returned = Borrowing::factory()->create([
            'item_id' => $this->item->id,
            'user_id' => $this->staff->id,
            'status' => 'dikembalikan',
            'borrow_date' => Carbon::today()->subDays(10)->toDateString(),
            'due_date' => Carbon::today()->subDays(3)->toDateString(),
            'return_date' => Carbon::today()->subDays(2)->toDateString(),
        ]);

        // 1. Trigger index action - pure read (assert is_overdue flag is true in resource, but DB record not mutated by GET)
        $response = $this->getJson('/api/borrowings');
        $response->assertStatus(200);

        $eligibleData = collect($response->json('data'))->firstWhere('id', $eligible->id);
        $this->assertTrue($eligibleData['is_overdue']);

        // Before scheduled command runs, DB status remains 'dipinjam' (CQS separation)
        $this->assertDatabaseHas('borrowings', [
            'id' => $eligible->id,
            'status' => 'dipinjam',
        ]);

        // 2. Execute the scheduled Artisan command
        $this->artisan('borrowings:check-overdue')->assertSuccessful();

        // Direct database assertions after scheduled command execution
        $this->assertDatabaseHas('borrowings', [
            'id' => $eligible->id,
            'status' => 'terlambat',
        ]);

        $this->assertDatabaseHas('borrowings', [
            'id' => $notOverdue->id,
            'status' => 'dipinjam',
        ]);

        $this->assertDatabaseHas('borrowings', [
            'id' => $returned->id,
            'status' => 'dikembalikan',
        ]);
    }
}
