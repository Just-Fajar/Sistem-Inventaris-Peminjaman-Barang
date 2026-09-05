<?php

namespace Tests\Feature;

use App\Enums\ItemCondition;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ValueError;

class ItemConditionEnumTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin_cond@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff_cond@example.com',
        ]);

        $this->category = Category::factory()->create();
    }

    // ==========================================
    // ⚪ WHITE-BOX TESTING: Enum Structure & Logic
    // ==========================================

    public function test_whitebox_enum_cases_and_backing_values(): void
    {
        $expectedValues = ['baik', 'rusak', 'hilang'];

        $this->assertSame($expectedValues, ItemCondition::values());
        $this->assertSame('baik', ItemCondition::Baik->value);
        $this->assertSame('rusak', ItemCondition::Rusak->value);
        $this->assertSame('hilang', ItemCondition::Hilang->value);

        $this->assertSame(ItemCondition::Baik, ItemCondition::from('baik'));
        $this->assertSame(ItemCondition::Rusak, ItemCondition::from('rusak'));
        $this->assertSame(ItemCondition::Hilang, ItemCondition::from('hilang'));
        $this->assertNull(ItemCondition::tryFrom('invalid_condition'));

        $this->expectException(ValueError::class);
        ItemCondition::from('hancur');
    }

    public function test_whitebox_enum_helper_methods(): void
    {
        // Labels
        $this->assertSame('Baik', ItemCondition::Baik->label());
        $this->assertSame('Rusak', ItemCondition::Rusak->label());
        $this->assertSame('Hilang', ItemCondition::Hilang->label());

        // Badge colors
        $this->assertSame('success', ItemCondition::Baik->badgeColor());
        $this->assertSame('warning', ItemCondition::Rusak->badgeColor());
        $this->assertSame('danger', ItemCondition::Hilang->badgeColor());

        // Borrowability
        $this->assertTrue(ItemCondition::Baik->isBorrowable());
        $this->assertFalse(ItemCondition::Rusak->isBorrowable());
        $this->assertFalse(ItemCondition::Hilang->isBorrowable());
    }

    // ==========================================
    // ⚫ BLACK-BOX TESTING: API Input & Output
    // ==========================================

    public function test_blackbox_store_item_with_valid_condition(): void
    {
        $payload = [
            'name' => 'Monitor Dell 27 Inch',
            'category_id' => $this->category->id,
            'description' => 'Monitor kantor',
            'stock' => 5,
            'condition' => 'baik',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/items', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.condition', 'baik')
            ->assertJsonPath('data.name', 'Monitor Dell 27 Inch');

        $this->assertDatabaseHas('items', [
            'name' => 'Monitor Dell 27 Inch',
            'condition' => 'baik',
        ]);
    }

    public function test_blackbox_store_item_rejects_invalid_condition(): void
    {
        $payload = [
            'name' => 'Monitor Rusak Total',
            'category_id' => $this->category->id,
            'stock' => 5,
            'condition' => 'hancur_lebur',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/items', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['condition']);
    }

    public function test_blackbox_update_item_condition(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 3,
            'available_stock' => 3,
            'condition' => ItemCondition::Baik,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/items/{$item->id}", [
                'condition' => 'rusak',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.condition', 'rusak');

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'condition' => 'rusak',
        ]);
    }

    public function test_blackbox_items_index_and_v1_serialize_condition_as_string(): void
    {
        Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 2,
            'available_stock' => 2,
            'condition' => ItemCondition::Baik,
        ]);

        // Unversioned
        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/items');

        $response->assertOk();
        $this->assertSame('baik', $response->json('data.0.condition'));

        // Versioned /v1
        $responseV1 = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/items');

        $responseV1->assertOk();
        $this->assertSame('baik', $responseV1->json('data.0.condition'));
    }

    // ==========================================
    // 🔘 GREY-BOX TESTING: Eloquent Model & Persistence
    // ==========================================

    public function test_greybox_eloquent_casts_condition_attribute_to_enum(): void
    {
        $item = Item::create([
            'code' => 'ITM-TEST-001',
            'name' => 'Projector Epson',
            'category_id' => $this->category->id,
            'stock' => 4,
            'available_stock' => 4,
            'condition' => 'baik',
        ]);

        $fresh = Item::findOrFail($item->id);

        $this->assertInstanceOf(ItemCondition::class, $fresh->condition);
        $this->assertSame(ItemCondition::Baik, $fresh->condition);
        $this->assertSame('baik', $fresh->condition->value);
    }

    public function test_greybox_assigning_enum_persists_raw_string_in_database(): void
    {
        $item = new Item();
        $item->code = 'ITM-TEST-002';
        $item->name = 'Printer HP';
        $item->category_id = $this->category->id;
        $item->stock = 2;
        $item->available_stock = 2;
        $item->condition = ItemCondition::Rusak;
        $item->save();

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'condition' => 'rusak',
        ]);

        $item->condition = ItemCondition::Hilang;
        $item->save();

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'condition' => 'hilang',
        ]);
    }

    public function test_greybox_is_available_respects_condition_enum(): void
    {
        $goodItem = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 5,
            'condition' => ItemCondition::Baik,
        ]);

        $damagedItem = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 5,
            'condition' => ItemCondition::Rusak,
        ]);

        $lostItem = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 5,
            'condition' => ItemCondition::Hilang,
        ]);

        $this->assertTrue($goodItem->isAvailable(1));
        $this->assertTrue($goodItem->isAvailable(5));
        $this->assertFalse($goodItem->isAvailable(6)); // stock exceeded

        $this->assertFalse($damagedItem->isAvailable(1));
        $this->assertFalse($lostItem->isAvailable(1));
    }

    public function test_greybox_query_scopes_filter_by_condition_enum(): void
    {
        Item::factory()->create([
            'category_id' => $this->category->id,
            'condition' => ItemCondition::Baik,
        ]);
        Item::factory()->create([
            'category_id' => $this->category->id,
            'condition' => ItemCondition::Baik,
        ]);
        Item::factory()->create([
            'category_id' => $this->category->id,
            'condition' => ItemCondition::Rusak,
        ]);
        Item::factory()->create([
            'category_id' => $this->category->id,
            'condition' => ItemCondition::Hilang,
        ]);

        $this->assertSame(2, Item::good()->count());
        $this->assertSame(1, Item::damaged()->count());
        $this->assertSame(1, Item::lost()->count());
    }
}
