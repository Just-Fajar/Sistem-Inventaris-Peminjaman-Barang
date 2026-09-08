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

class BorrowingQueryFilterAndMappingTest extends TestCase
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
            'email' => 'admin_test@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff_test@example.com',
        ]);

        $category = Category::factory()->create();
        $this->item = Item::factory()->create([
            'category_id' => $category->id,
            'stock' => 20,
            'available_stock' => 20,
        ]);
    }

    public function test_empty_string_parameters_do_not_empty_the_borrowings_list(): void
    {
        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => BorrowingStatus::Dipinjam,
        ]);

        Borrowing::factory()->create([
            'user_id' => $this->admin->id,
            'item_id' => $this->item->id,
            'status' => BorrowingStatus::Dikembalikan,
        ]);

        // Simulating the exact query sent by frontend when default filter state is empty strings
        $response = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?status=&search=&start_date=&end_date=');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_status_filter_accepts_enum_and_alias_mappings(): void
    {
        $borrowing1 = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => BorrowingStatus::Dipinjam,
        ]);

        $borrowing2 = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => BorrowingStatus::Dikembalikan,
        ]);

        // Test with Indonesian enum value 'dipinjam'
        $resDipinjam = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?status=dipinjam');
        $resDipinjam->assertStatus(200);
        $this->assertCount(1, $resDipinjam->json('data'));
        $this->assertEquals($borrowing1->id, $resDipinjam->json('data.0.id'));

        // Test with English alias 'approved'
        $resApproved = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?status=approved');
        $resApproved->assertStatus(200);
        $this->assertCount(1, $resApproved->json('data'));
        $this->assertEquals($borrowing1->id, $resApproved->json('data.0.id'));

        // Test with Indonesian enum value 'dikembalikan'
        $resDikembalikan = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?status=dikembalikan');
        $resDikembalikan->assertStatus(200);
        $this->assertCount(1, $resDikembalikan->json('data'));
        $this->assertEquals($borrowing2->id, $resDikembalikan->json('data.0.id'));

        // Test with English alias 'returned'
        $resReturned = $this->actingAs($this->admin)
            ->getJson('/api/borrowings?status=returned');
        $resReturned->assertStatus(200);
        $this->assertCount(1, $resReturned->json('data'));
        $this->assertEquals($borrowing2->id, $resReturned->json('data.0.id'));
    }

    public function test_date_range_filter_with_start_date_and_end_date_aliases(): void
    {
        $oldBorrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'borrow_date' => Carbon::now()->subDays(30),
            'due_date' => Carbon::now()->subDays(20),
        ]);

        $recentBorrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'borrow_date' => Carbon::now()->subDays(2),
            'due_date' => Carbon::now()->addDays(5),
        ]);

        $startDate = Carbon::now()->subDays(5)->format('Y-m-d');
        $endDate = Carbon::now()->addDays(1)->format('Y-m-d');

        // Filter using start_date & end_date parameters
        $response = $this->actingAs($this->admin)
            ->getJson("/api/borrowings?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($recentBorrowing->id, $response->json('data.0.id'));
    }

    public function test_my_borrowings_endpoint_respects_empty_filters_and_status_alias(): void
    {
        Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => BorrowingStatus::Dipinjam,
        ]);

        Borrowing::factory()->create([
            'user_id' => $this->admin->id,
            'item_id' => $this->item->id,
            'status' => BorrowingStatus::Dipinjam,
        ]);

        // Empty string query should not filter out the user's borrowings
        $response = $this->actingAs($this->staff)
            ->getJson('/api/borrowings/my/list?status=');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Alias 'approved' maps to 'dipinjam'
        $responseAlias = $this->actingAs($this->staff)
            ->getJson('/api/borrowings/my/list?status=approved');

        $responseAlias->assertStatus(200);
        $this->assertCount(1, $responseAlias->json('data'));
    }

    public function test_both_post_and_put_work_for_return_and_extend(): void
    {
        $borrowing1 = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Dipinjam,
        ]);

        // Test POST return
        $postReturn = $this->actingAs($this->admin)
            ->postJson("/api/borrowings/{$borrowing1->id}/return");
        $postReturn->assertStatus(200);

        $borrowing2 = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Dipinjam,
        ]);

        // Test PUT return
        $putReturn = $this->actingAs($this->admin)
            ->putJson("/api/borrowings/{$borrowing2->id}/return");
        $putReturn->assertStatus(200);

        $borrowing3 = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Dipinjam,
            'due_date' => Carbon::now()->addDays(3),
        ]);

        // Test POST extend
        $newDueDate = Carbon::now()->addDays(10)->format('Y-m-d');
        $postExtend = $this->actingAs($this->staff)
            ->postJson("/api/borrowings/{$borrowing3->id}/extend", [
                'new_due_date' => $newDueDate,
            ]);
        $postExtend->assertStatus(200);

        // Test PUT extend
        $furtherDueDate = Carbon::now()->addDays(15)->format('Y-m-d');
        $putExtend = $this->actingAs($this->staff)
            ->putJson("/api/borrowings/{$borrowing3->id}/extend", [
                'new_due_date' => $furtherDueDate,
            ]);
        $putExtend->assertStatus(200);
    }
}
