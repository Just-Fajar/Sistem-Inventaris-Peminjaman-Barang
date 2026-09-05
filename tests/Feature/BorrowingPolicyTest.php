<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\ItemCondition;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Policies\BorrowingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BorrowingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private User $intruder;
    private Item $item;
    private Borrowing $borrowing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin_policy@example.com',
        ]);

        $this->owner = User::factory()->create([
            'role' => 'staff',
            'email' => 'owner_policy@example.com',
        ]);

        $this->intruder = User::factory()->create([
            'role' => 'staff',
            'email' => 'intruder_policy@example.com',
        ]);

        $category = Category::factory()->create();
        $this->item = Item::factory()->create([
            'category_id' => $category->id,
            'stock' => 10,
            'available_stock' => 8,
            'condition' => ItemCondition::Baik,
        ]);

        $this->borrowing = Borrowing::create([
            'borrow_code' => 'BRW-POL-001',
            'user_id' => $this->owner->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => now(),
            'due_date' => now()->addDays(5),
            'status' => BorrowingStatus::Dipinjam,
        ]);
    }

    // ==========================================
    // ⚪ WHITE-BOX TESTING: Direct Policy Unit Tests
    // ==========================================

    public function test_whitebox_policy_rules_for_admin(): void
    {
        $policy = new BorrowingPolicy();

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $this->borrowing));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $this->borrowing));
        $this->assertTrue($policy->delete($this->admin, $this->borrowing));
        $this->assertTrue($policy->approve($this->admin, $this->borrowing));
        $this->assertTrue($policy->reject($this->admin, $this->borrowing));
        $this->assertTrue($policy->return($this->admin, $this->borrowing));
        $this->assertTrue($policy->extend($this->admin, $this->borrowing));
    }

    public function test_whitebox_policy_rules_for_owner(): void
    {
        $policy = new BorrowingPolicy();

        // Allowed for owner
        $this->assertTrue($policy->viewAny($this->owner));
        $this->assertTrue($policy->view($this->owner, $this->borrowing));
        $this->assertTrue($policy->create($this->owner));
        $this->assertTrue($policy->update($this->owner, $this->borrowing));
        $this->assertTrue($policy->return($this->owner, $this->borrowing));
        $this->assertTrue($policy->extend($this->owner, $this->borrowing));

        // Disallowed for owner (Admin only)
        $this->assertFalse($policy->delete($this->owner, $this->borrowing));
        $this->assertFalse($policy->approve($this->owner, $this->borrowing));
        $this->assertFalse($policy->reject($this->owner, $this->borrowing));
    }

    public function test_whitebox_policy_rules_for_intruder(): void
    {
        $policy = new BorrowingPolicy();

        $this->assertTrue($policy->viewAny($this->intruder));
        $this->assertTrue($policy->create($this->intruder));

        // Disallowed for non-owner
        $this->assertFalse($policy->view($this->intruder, $this->borrowing));
        $this->assertFalse($policy->update($this->intruder, $this->borrowing));
        $this->assertFalse($policy->delete($this->intruder, $this->borrowing));
        $this->assertFalse($policy->approve($this->intruder, $this->borrowing));
        $this->assertFalse($policy->reject($this->intruder, $this->borrowing));
        $this->assertFalse($policy->return($this->intruder, $this->borrowing));
        $this->assertFalse($policy->extend($this->intruder, $this->borrowing));
    }

    // ==========================================
    // ⚫ BLACK-BOX TESTING: API Controller Authorization
    // ==========================================

    public function test_blackbox_staff_cannot_approve_or_reject_borrowing(): void
    {
        $pendingBorrowing = Borrowing::create([
            'borrow_code' => 'BRW-POL-002',
            'user_id' => $this->owner->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(3),
            'status' => BorrowingStatus::Pending,
        ]);

        // Attempt approve as staff
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/borrowings/{$pendingBorrowing->id}/approve");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. Admin access required.']);

        // Attempt reject as staff
        $responseReject = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/borrowings/{$pendingBorrowing->id}/reject");

        $responseReject->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. Admin access required.']);
    }

    public function test_blackbox_intruder_cannot_return_or_extend_borrowing(): void
    {
        // Intruder tries to return owner's borrowing
        $responseReturn = $this->actingAs($this->intruder, 'sanctum')
            ->postJson("/api/borrowings/{$this->borrowing->id}/return");

        $responseReturn->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. You can only return your own borrowings.']);

        // Intruder tries to extend owner's borrowing
        $responseExtend = $this->actingAs($this->intruder, 'sanctum')
            ->postJson("/api/borrowings/{$this->borrowing->id}/extend", [
                'new_due_date' => now()->addDays(10)->format('Y-m-d'),
            ]);

        $responseExtend->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. You can only extend your own borrowings.']);
    }

    public function test_blackbox_intruder_cannot_view_or_update_or_delete_borrowing(): void
    {
        // View
        $responseView = $this->actingAs($this->intruder, 'sanctum')
            ->getJson("/api/borrowings/{$this->borrowing->id}");

        $responseView->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. You can only view your own borrowings.']);

        // Update
        $responseUpdate = $this->actingAs($this->intruder, 'sanctum')
            ->putJson("/api/borrowings/{$this->borrowing->id}", [
                'notes' => 'Hacked notes',
            ]);

        $responseUpdate->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. You cannot update this borrowing.']);

        // Delete
        $responseDelete = $this->actingAs($this->intruder, 'sanctum')
            ->deleteJson("/api/borrowings/{$this->borrowing->id}");

        $responseDelete->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. Admin access required to delete borrowings.']);
    }

    public function test_blackbox_owner_can_view_and_return_own_borrowing(): void
    {
        // Owner views
        $responseView = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/borrowings/{$this->borrowing->id}");

        $responseView->assertOk()
            ->assertJsonPath('data.id', $this->borrowing->id);

        // Owner returns
        $responseReturn = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/borrowings/{$this->borrowing->id}/return");

        $responseReturn->assertOk()
            ->assertJson(['message' => 'Item returned successfully']);
    }

    public function test_blackbox_admin_can_perform_all_borrowing_actions(): void
    {
        $pendingBorrowing = Borrowing::create([
            'borrow_code' => 'BRW-POL-003',
            'user_id' => $this->owner->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => now(),
            'due_date' => now()->addDays(3),
            'status' => BorrowingStatus::Pending,
        ]);

        // Admin approves
        $responseApprove = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/borrowings/{$pendingBorrowing->id}/approve");

        $responseApprove->assertOk()
            ->assertJson(['message' => 'Borrowing approved successfully']);

        // Admin extends
        $responseExtend = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/borrowings/{$pendingBorrowing->id}/extend", [
                'new_due_date' => now()->addDays(12)->format('Y-m-d'),
            ]);

        $responseExtend->assertOk()
            ->assertJson(['message' => 'Borrowing extended successfully']);

        // Admin views
        $responseView = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/borrowings/{$pendingBorrowing->id}");

        $responseView->assertOk();
    }

    // ==========================================
    // 🔘 GREY-BOX TESTING: Gate & Model Helper Resolution
    // ==========================================

    public function test_greybox_gate_policy_resolution(): void
    {
        $resolvedPolicy = Gate::getPolicyFor(Borrowing::class);

        $this->assertInstanceOf(BorrowingPolicy::class, $resolvedPolicy);
    }

    public function test_greybox_user_can_and_cannot_gate_checks(): void
    {
        $this->assertTrue($this->admin->can('approve', $this->borrowing));
        $this->assertTrue($this->admin->can('delete', $this->borrowing));

        $this->assertTrue($this->owner->cannot('approve', $this->borrowing));
        $this->assertTrue($this->owner->cannot('delete', $this->borrowing));
        $this->assertTrue($this->owner->can('return', $this->borrowing));
        $this->assertTrue($this->owner->can('extend', $this->borrowing));

        $this->assertTrue($this->intruder->cannot('return', $this->borrowing));
        $this->assertTrue($this->intruder->cannot('extend', $this->borrowing));
        $this->assertTrue($this->intruder->cannot('view', $this->borrowing));
    }
}
