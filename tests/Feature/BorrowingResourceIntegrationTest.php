<?php

namespace Tests\Feature;

use App\Http\Resources\BorrowingResource;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingResourceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Category $category;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Manager',
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->staff = User::factory()->create([
            'name' => 'Staff Member',
            'role' => 'staff',
            'email' => 'staff@example.com',
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Alat Presentasi',
        ]);

        $this->item = Item::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Pointer Wireless Logitech',
            'code' => 'ITM-2026-0500',
            'stock' => 10,
            'available_stock' => 10,
            'condition' => 'baik',
        ]);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Direct Resource Transformation, Overdue Flags & Relations)
     * ========================================================================= */

    public function test_whitebox_borrowing_resource_transforms_attributes_and_dates_correctly(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-10',
            'return_date' => '2026-09-09',
            'status' => 'dikembalikan',
            'notes' => 'Keperluan rapat kerja',
            'approved_by' => $this->admin->id,
            'approved_at' => '2026-09-01 08:30:00',
        ]);

        $resource = new BorrowingResource($borrowing);
        $data = $resource->toArray(Request::create('/api/borrowings'));

        $this->assertEquals($borrowing->id, $data['id']);
        $this->assertEquals($borrowing->borrow_code, $data['borrow_code']);
        $this->assertEquals($borrowing->borrow_code, $data['code']);
        $this->assertEquals($this->staff->id, $data['user_id']);
        $this->assertEquals($this->item->id, $data['item_id']);
        $this->assertEquals(2, $data['quantity']);
        $this->assertEquals('2026-09-01', $data['borrow_date']);
        $this->assertEquals('2026-09-10', $data['due_date']);
        $this->assertEquals('2026-09-09', $data['return_date']);
        $this->assertEquals('dikembalikan', $data['status']);
        $this->assertEquals('Keperluan rapat kerja', $data['notes']);
        $this->assertEquals($this->admin->id, $data['approved_by']);
        $this->assertEquals('2026-09-01 08:30:00', $data['approved_at']);
        $this->assertFalse($data['is_overdue']);
    }

    public function test_whitebox_borrowing_resource_calculates_overdue_flags(): void
    {
        // 1. Status terlambat
        $overdueBorrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'terlambat',
            'due_date' => Carbon::today()->subDays(4)->toDateString(),
        ]);

        $res1 = new BorrowingResource($overdueBorrowing);
        $data1 = $res1->toArray(Request::create('/'));
        $this->assertTrue($data1['is_overdue']);
        $this->assertGreaterThanOrEqual(4, $data1['days_overdue']);

        // 2. Status dipinjam with future due_date
        $activeBorrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        $res2 = new BorrowingResource($activeBorrowing);
        $data2 = $res2->toArray(Request::create('/'));
        $this->assertFalse($data2['is_overdue']);
        $this->assertInstanceOf(MissingValue::class, $data2['days_overdue']);
    }

    public function test_whitebox_borrowing_resource_handles_conditional_relationships(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'approved_by' => $this->admin->id,
            'status' => 'dipinjam',
        ]);

        // When relations NOT loaded
        $resWithoutRelations = new BorrowingResource($borrowing);
        $dataWithout = $resWithoutRelations->toArray(Request::create('/'));

        $this->assertInstanceOf(MissingValue::class, $dataWithout['user']->resource);
        $this->assertInstanceOf(MissingValue::class, $dataWithout['item']->resource);
        $this->assertInstanceOf(MissingValue::class, $dataWithout['approver']->resource);

        // When relations ARE loaded
        $borrowing->load(['user', 'item.category', 'approver']);
        $resWithRelations = new BorrowingResource($borrowing);
        $dataWith = $resWithRelations->toArray(Request::create('/'));

        $this->assertEquals($this->staff->name, $dataWith['user']->name);
        $this->assertEquals($this->item->name, $dataWith['item']->name);
        $this->assertEquals($this->category->name, $dataWith['item']->category->name);
        $this->assertEquals($this->admin->name, $dataWith['approver']->name);
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (All Controller Endpoints Returning BorrowingResource)
     * ========================================================================= */

    public function test_blackbox_index_returns_paginated_resource_collection(): void
    {
        Sanctum::actingAs($this->admin);

        Borrowing::factory()->count(3)->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
        ]);

        $response = $this->getJson('/api/borrowings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'borrow_code',
                        'code',
                        'user_id',
                        'item_id',
                        'quantity',
                        'status',
                        'user',
                        'item',
                    ],
                ],
                'per_page',
                'total',
            ]);
    }

    public function test_blackbox_store_returns_borrowing_resource_with_message(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'notes' => 'Testing resource on store',
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
                    'code',
                    'quantity',
                    'status',
                    'user',
                    'item',
                ],
            ]);
    }

    public function test_blackbox_show_returns_borrowing_resource(): void
    {
        Sanctum::actingAs($this->staff);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
        ]);

        $response = $this->getJson("/api/borrowings/{$borrowing->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'borrow_code',
                    'code',
                    'status',
                    'user',
                    'item',
                ],
            ]);
    }

    public function test_blackbox_update_returns_borrowing_resource_with_message(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-05',
        ]);

        $response = $this->putJson("/api/borrowings/{$borrowing->id}", [
            'notes' => 'Catatan update via resource',
            'due_date' => '2026-09-15',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing updated successfully',
            ])
            ->assertJsonPath('data.notes', 'Catatan update via resource')
            ->assertJsonPath('data.due_date', '2026-09-15');
    }

    public function test_blackbox_return_returns_borrowing_resource_with_message(): void
    {
        Sanctum::actingAs($this->staff);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
            'quantity' => 1,
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/return", [
            'return_date' => now()->toDateString(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Item returned successfully',
            ])
            ->assertJsonPath('data.status', 'dikembalikan');
    }

    public function test_blackbox_approve_returns_borrowing_resource_with_message(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'pending',
            'quantity' => 1,
            'approved_by' => null,
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing approved successfully',
            ])
            ->assertJsonPath('data.status', 'dipinjam')
            ->assertJsonPath('data.approved_by', $this->admin->id);
    }

    public function test_blackbox_reject_returns_borrowing_resource_with_message(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'pending',
            'quantity' => 1,
            'approved_by' => null,
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing rejected successfully',
            ])
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_blackbox_extend_returns_borrowing_resource_with_message(): void
    {
        Sanctum::actingAs($this->staff);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $newDueDate = now()->addDays(7)->toDateString();
        $response = $this->postJson("/api/borrowings/{$borrowing->id}/extend", [
            'new_due_date' => $newDueDate,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing extended successfully',
            ])
            ->assertJsonPath('data.due_date', $newDueDate);
    }

    public function test_blackbox_my_borrowings_returns_resource_collection(): void
    {
        Sanctum::actingAs($this->staff);

        Borrowing::factory()->count(2)->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
        ]);

        $response = $this->getJson('/api/borrowings/my/list');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'borrow_code',
                        'code',
                        'status',
                        'user',
                        'item',
                    ],
                ],
                'per_page',
                'total',
            ]);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Query Efficiency, Eager-Loading & Contract Integrity)
     * ========================================================================= */

    public function test_greybox_borrowing_resource_does_not_cause_n_plus_one_on_index(): void
    {
        Sanctum::actingAs($this->admin);

        // Create 8 borrowings
        for ($i = 0; $i < 8; $i++) {
            Borrowing::factory()->create([
                'user_id' => $this->staff->id,
                'item_id' => $this->item->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/borrowings?per_page=8');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Total queries should be strictly bounded:
        // auth lookup + count + batch overdue update + borrowings select + eager loaded users + items + approvers
        $this->assertLessThanOrEqual(7, count($queries), 'Query count leak detected during resource transformation');
    }
}
