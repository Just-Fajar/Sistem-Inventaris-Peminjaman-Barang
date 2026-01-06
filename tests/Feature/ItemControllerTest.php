<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $staff;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    public function test_admin_can_list_all_items(): void
    {
        Item::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code', 'stock', 'available_stock']
                ]
            ]);
    }

    public function test_admin_can_create_item(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/items', [
                'name' => 'Test Item',
                'category_id' => $category->id,
                'stock' => 10,
                'condition' => 'good',
                'description' => 'Test description',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('items', [
            'name' => 'Test Item',
            'stock' => 10,
        ]);
    }

    public function test_staff_cannot_create_item(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->staff)
            ->postJson('/api/items', [
                'name' => 'Test Item',
                'category_id' => $category->id,
                'stock' => 10,
                'condition' => 'good',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_item(): void
    {
        $item = Item::factory()->create(['stock' => 10]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/items/{$item->id}", [
                'name' => 'Updated Item',
                'category_id' => $item->category_id,
                'stock' => 15,
                'condition' => 'good',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'name' => 'Updated Item',
            'stock' => 15,
        ]);
    }

    public function test_admin_can_delete_item(): void
    {
        $item = Item::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_can_search_items_by_name(): void
    {
        Item::factory()->create(['name' => 'Laptop HP']);
        Item::factory()->create(['name' => 'Mouse Logitech']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/items?search=Laptop');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_items_by_category(): void
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        Item::factory()->create(['category_id' => $category1->id]);
        Item::factory()->create(['category_id' => $category2->id]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/items?category_id={$category1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_items_by_stock_range(): void
    {
        Item::factory()->create(['stock' => 5]);
        Item::factory()->create(['stock' => 15]);
        Item::factory()->create(['stock' => 25]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/items?min_stock=10&max_stock=20');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_out_of_stock_items(): void
    {
        Item::factory()->create(['stock' => 10, 'available_stock' => 0]);
        Item::factory()->create(['stock' => 10, 'available_stock' => 5]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/items?out_of_stock=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_image_upload_validation(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $file = UploadedFile::fake()->image('item.jpg');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/items', [
                'name' => 'Test Item',
                'category_id' => $category->id,
                'stock' => 10,
                'condition' => 'good',
                'image' => $file,
            ]);

        $response->assertStatus(201);
        
        $item = Item::where('name', 'Test Item')->first();
        $this->assertNotNull($item->image);
    }

    public function test_available_stock_equals_stock_on_creation(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/items', [
                'name' => 'Test Item',
                'category_id' => $category->id,
                'stock' => 20,
                'condition' => 'good',
            ]);

        $response->assertStatus(201);
        
        $item = Item::where('name', 'Test Item')->first();
        $this->assertEquals(20, $item->available_stock);
    }
}
