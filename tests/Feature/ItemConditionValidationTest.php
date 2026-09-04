<?php

namespace Tests\Feature;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemConditionValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->category = Category::factory()->create();
    }

    // ==========================================
    // 1. BLACK-BOX TESTING (API & Input Contract)
    // ==========================================

    public function test_blackbox_admin_can_create_item_with_hilang_condition(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Proyektor Ruang Rapat',
            'category_id' => $this->category->id,
            'description' => 'Barang dilaporkan hilang saat inventarisasi',
            'stock' => 1,
            'condition' => 'hilang',
        ];

        $response = $this->postJson('/api/items', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Item created successfully',
                'data' => [
                    'name' => 'Proyektor Ruang Rapat',
                    'condition' => 'hilang',
                ],
            ]);
    }

    public function test_blackbox_admin_can_update_item_condition_to_hilang(): void
    {
        Sanctum::actingAs($this->admin);

        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'condition' => 'baik',
        ]);

        $response = $this->putJson("/api/items/{$item->id}", [
            'condition' => 'hilang',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Item updated successfully',
                'data' => [
                    'id' => $item->id,
                    'condition' => 'hilang',
                ],
            ]);
    }

    public function test_blackbox_admin_cannot_create_item_with_invalid_condition(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Barang Tes',
            'category_id' => $this->category->id,
            'stock' => 5,
            'condition' => 'hancur_lebur',
        ];

        $response = $this->postJson('/api/items', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['condition']);
    }

    public function test_blackbox_can_create_item_with_all_valid_conditions(): void
    {
        Sanctum::actingAs($this->admin);

        $conditions = ['baik', 'rusak', 'hilang'];

        foreach ($conditions as $index => $condition) {
            $response = $this->postJson('/api/items', [
                'name' => "Item Kondisi {$condition}",
                'category_id' => $this->category->id,
                'stock' => 2,
                'condition' => $condition,
            ]);

            $response->assertStatus(201)
                ->assertJsonPath('data.condition', $condition);
        }
    }

    // ==========================================
    // 2. WHITE-BOX TESTING (Rules & Logic Verification)
    // ==========================================

    public function test_whitebox_store_item_request_rules_include_hilang(): void
    {
        $request = new StoreItemRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('condition', $rules);
        $this->assertEquals('required|in:baik,rusak,hilang', $rules['condition']);
    }

    public function test_whitebox_update_item_request_rules_include_hilang(): void
    {
        $request = new UpdateItemRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('condition', $rules);
        $this->assertEquals('sometimes|required|in:baik,rusak,hilang', $rules['condition']);
    }

    public function test_whitebox_is_available_returns_false_for_hilang_condition(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 5,
            'condition' => 'hilang',
        ]);

        // Even with available_stock > 0, an item marked as 'hilang' must NOT be available for borrowing
        $this->assertFalse($item->isAvailable(1));
    }

    // ==========================================
    // 3. GREY-BOX TESTING (Database Persistence & Factory State)
    // ==========================================

    public function test_greybox_item_with_hilang_condition_persists_in_database(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/items', [
            'name' => 'Mouse Wireless Hilang',
            'category_id' => $this->category->id,
            'stock' => 1,
            'condition' => 'hilang',
        ]);

        $response->assertStatus(201);
        $itemId = $response->json('data.id');

        $this->assertDatabaseHas('items', [
            'id' => $itemId,
            'name' => 'Mouse Wireless Hilang',
            'condition' => 'hilang',
        ]);
    }

    public function test_greybox_item_factory_lost_state_persists_as_hilang(): void
    {
        $item = Item::factory()->lost()->create([
            'category_id' => $this->category->id,
        ]);

        $this->assertEquals('hilang', $item->condition);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'condition' => 'hilang',
        ]);
    }
}
