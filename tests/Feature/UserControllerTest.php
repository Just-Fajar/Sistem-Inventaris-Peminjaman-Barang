<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
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
     * ⚫ BLACK-BOX TESTING (API Contract, Status Codes & Authorization)
     * ========================================================================= */

    public function test_blackbox_guest_cannot_delete_user(): void
    {
        $targetUser = User::factory()->create(['role' => 'staff']);

        $response = $this->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(401);
    }

    public function test_blackbox_staff_cannot_delete_user(): void
    {
        $targetUser = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($this->staff)
            ->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(403);
    }

    public function test_blackbox_admin_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$this->admin->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Tidak dapat menghapus user sendiri',
            ]);
    }

    public function test_blackbox_delete_user_with_active_borrowing_returns_400(): void
    {
        $targetUser = User::factory()->create(['role' => 'staff']);

        Borrowing::factory()->create([
            'user_id' => $targetUser->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Tidak dapat menghapus user yang masih memiliki peminjaman aktif',
            ]);
    }

    public function test_blackbox_delete_user_without_borrowings_returns_200(): void
    {
        $targetUser = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User berhasil dihapus',
            ]);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Branch Coverage & Internal Logic Paths)
     * ========================================================================= */

    public function test_whitebox_branch_active_status_dipinjam_blocks_deletion(): void
    {
        $targetUser = User::factory()->create(['role' => 'staff']);

        // Triggers whereIn('status', ['dipinjam', 'terlambat']) -> 'dipinjam' branch
        Borrowing::factory()->create([
            'user_id' => $targetUser->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(400);
    }

    public function test_whitebox_branch_active_status_terlambat_blocks_deletion(): void
    {
        $targetUser = User::factory()->create(['role' => 'staff']);

        // Triggers whereIn('status', ['dipinjam', 'terlambat']) -> 'terlambat' branch
        Borrowing::factory()->create([
            'user_id' => $targetUser->id,
            'item_id' => $this->item->id,
            'status' => 'terlambat',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(400);
    }

    public function test_whitebox_branch_completed_borrowing_dikembalikan_allows_deletion(): void
    {
        $targetUser = User::factory()->create(['role' => 'staff']);

        // User has borrowings, but all are returned ('dikembalikan') -> condition evaluates to false
        Borrowing::factory()->create([
            'user_id' => $targetUser->id,
            'item_id' => $this->item->id,
            'status' => 'dikembalikan',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(200);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Database Integrity, State & Side Effects)
     * ========================================================================= */

    public function test_greybox_user_retained_in_database_when_deletion_is_blocked(): void
    {
        $targetUser = User::factory()->create(['role' => 'staff']);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $targetUser->id,
            'item_id' => $this->item->id,
            'status' => 'dipinjam',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$targetUser->id}");

        // Ensure database state was not altered
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'email' => $targetUser->email,
        ]);

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'dipinjam',
        ]);
    }

    public function test_greybox_user_removed_from_database_on_successful_deletion(): void
    {
        $targetUser = User::factory()->create(['role' => 'staff']);

        $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$targetUser->id}");

        // Ensure record was removed from users table
        $this->assertDatabaseMissing('users', [
            'id' => $targetUser->id,
        ]);
    }
}
