<?php

namespace Tests\Feature;

use App\Enums\ItemCondition;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Policies\ItemPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ItemPolicyTest extends TestCase
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
            'email' => 'admin_item_policy@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff_item_policy@example.com',
        ]);

        $this->category = Category::factory()->create();

        $this->item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 10,
            'available_stock' => 10,
            'condition' => ItemCondition::Baik,
        ]);
    }

    // ==========================================
    // ⚪ WHITE-BOX TESTING: Direct Policy Unit Tests
    // ==========================================

    public function test_whitebox_item_policy_rules_for_admin(): void
    {
        $policy = new ItemPolicy();

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->view($this->admin, $this->item));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->update($this->admin, $this->item));
        $this->assertTrue($policy->delete($this->admin, $this->item));
        $this->assertTrue($policy->bulkDelete($this->admin));
    }

    public function test_whitebox_item_policy_rules_for_staff(): void
    {
        $policy = new ItemPolicy();

        // Allowed for staff (read-only)
        $this->assertTrue($policy->viewAny($this->staff));
        $this->assertTrue($policy->view($this->staff, $this->item));

        // Disallowed for staff (mutations)
        $this->assertFalse($policy->create($this->staff));
        $this->assertFalse($policy->update($this->staff, $this->item));
        $this->assertFalse($policy->delete($this->staff, $this->item));
        $this->assertFalse($policy->bulkDelete($this->staff));
    }

    // ==========================================
    // ⚫ BLACK-BOX TESTING: API Controller & Requests
    // ==========================================

    public function test_blackbox_staff_cannot_create_item(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/items', [
                'name' => 'Unauthorized Item',
                'category_id' => $this->category->id,
                'stock' => 5,
                'condition' => 'baik',
            ]);

        $response->assertStatus(403);
    }

    public function test_blackbox_staff_cannot_update_item(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/items/{$this->item->id}", [
                'name' => 'Tampered Item Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_blackbox_staff_cannot_delete_item(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->deleteJson("/api/items/{$this->item->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. Admin access required.']);
    }

    public function test_blackbox_staff_cannot_bulk_delete_items(): void
    {
        $secondItem = Item::factory()->create(['category_id' => $this->category->id]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->deleteJson('/api/items/bulk-delete', [
                'ids' => [$this->item->id, $secondItem->id],
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. Admin access required.']);
    }

    public function test_blackbox_staff_can_view_items_list_and_detail(): void
    {
        // View list
        $responseList = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/items');

        $responseList->assertOk();

        // View detail
        $responseDetail = $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/items/{$this->item->id}");

        $responseDetail->assertOk()
            ->assertJsonPath('data.id', $this->item->id);
    }

    public function test_blackbox_admin_can_perform_all_item_actions(): void
    {
        // Admin creates
        $responseCreate = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/items', [
                'name' => 'Admin Created Item',
                'category_id' => $this->category->id,
                'stock' => 10,
                'condition' => 'baik',
            ]);

        $responseCreate->assertStatus(201);
        $createdId = $responseCreate->json('data.id');

        // Admin updates
        $responseUpdate = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/items/{$createdId}", [
                'name' => 'Admin Updated Item',
            ]);

        $responseUpdate->assertOk()
            ->assertJsonPath('data.name', 'Admin Updated Item');

        // Admin deletes
        $responseDelete = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/items/{$createdId}");

        $responseDelete->assertOk()
            ->assertJson(['message' => 'Item deleted successfully']);
    }

    // ==========================================
    // 🔘 GREY-BOX TESTING: Gate Resolution & Helpers
    // ==========================================

    public function test_greybox_gate_resolves_item_policy(): void
    {
        $resolvedPolicy = Gate::getPolicyFor(Item::class);

        $this->assertInstanceOf(ItemPolicy::class, $resolvedPolicy);
    }

    public function test_greybox_user_can_and_cannot_helpers_for_item(): void
    {
        $this->assertTrue($this->admin->can('create', Item::class));
        $this->assertTrue($this->admin->can('update', $this->item));
        $this->assertTrue($this->admin->can('delete', $this->item));
        $this->assertTrue($this->admin->can('bulkDelete', Item::class));

        $this->assertTrue($this->staff->cannot('create', Item::class));
        $this->assertTrue($this->staff->cannot('update', $this->item));
        $this->assertTrue($this->staff->cannot('delete', $this->item));
        $this->assertTrue($this->staff->cannot('bulkDelete', Item::class));
        $this->assertTrue($this->staff->can('view', $this->item));
        $this->assertTrue($this->staff->can('viewAny', Item::class));
    }
}
