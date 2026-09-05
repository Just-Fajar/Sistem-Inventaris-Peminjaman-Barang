<?php

namespace Tests\Feature;

use App\Http\Resources\BorrowingResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ItemResource;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemResourceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Inventory',
            'role' => 'admin',
            'email' => 'admin_item_resource@example.com',
        ]);

        $this->staff = User::factory()->create([
            'name' => 'Staff Inventory',
            'role' => 'staff',
            'email' => 'staff_item_resource@example.com',
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Elektronik Laboratorium',
            'description' => 'Peralatan elektronik lab komputer',
        ]);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Direct ItemResource Transformation, Casts & Relations)
     * ========================================================================= */

    public function test_whitebox_item_resource_transforms_all_attributes_and_types_correctly(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Oscilloscope Digital Rigol',
            'code' => 'ITM-2026-9901',
            'description' => 'Oscilloscope dual-channel 100MHz',
            'stock' => 5,
            'available_stock' => 3,
            'condition' => 'baik',
            'image' => 'items/rigol_100.webp',
        ]);

        $resource = new ItemResource($item);
        $request = Request::create('/api/items/' . $item->id);
        $data = $resource->toArray($request);

        // Attribute presence and values
        $this->assertEquals($item->id, $data['id']);
        $this->assertEquals('ITM-2026-9901', $data['code']);
        $this->assertEquals('Oscilloscope Digital Rigol', $data['name']);
        $this->assertEquals('Oscilloscope dual-channel 100MHz', $data['description']);
        $this->assertEquals($this->category->id, $data['category_id']);
        $this->assertEquals('baik', $data['condition']);
        $this->assertEquals('items/rigol_100.webp', $data['image']);
        $this->assertEquals(asset('storage/items/rigol_100.webp'), $data['image_url']);

        // Type safety & integer casts
        $this->assertSame(5, $data['stock']);
        $this->assertSame(3, $data['available_stock']);
        $this->assertIsInt($data['stock']);
        $this->assertIsInt($data['available_stock']);

        // Date formats
        $this->assertEquals($item->created_at->format('Y-m-d H:i:s'), $data['created_at']);
        $this->assertEquals($item->updated_at->format('Y-m-d H:i:s'), $data['updated_at']);
    }

    public function test_whitebox_item_resource_handles_null_image_gracefully(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'image' => null,
        ]);

        $resource = new ItemResource($item);
        $data = $resource->toArray(Request::create('/api/items/' . $item->id));

        $this->assertNull($data['image']);
        $this->assertNull($data['image_url']);
    }

    public function test_whitebox_item_resource_conditionally_loads_category(): void
    {
        // 1. Without category loaded
        $itemUnloaded = Item::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $resourceUnloaded = new ItemResource($itemUnloaded);
        $dataUnloaded = $resourceUnloaded->toArray(Request::create('/api/items/' . $itemUnloaded->id));

        $this->assertInstanceOf(MissingValue::class, $dataUnloaded['category']->resource);

        // 2. With category loaded
        $itemLoaded = Item::with('category')->find($itemUnloaded->id);
        $resourceLoaded = new ItemResource($itemLoaded);
        $dataLoaded = $resourceLoaded->toArray(Request::create('/api/items/' . $itemLoaded->id));

        $this->assertInstanceOf(CategoryResource::class, $dataLoaded['category']);
        $categoryData = $dataLoaded['category']->toArray(Request::create('/api/categories/' . $this->category->id));
        $this->assertEquals($this->category->id, $categoryData['id']);
        $this->assertEquals('Elektronik Laboratorium', $categoryData['name']);
    }

    public function test_whitebox_item_resource_conditionally_loads_active_borrowings_and_borrowings(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 10,
            'available_stock' => 8,
        ]);

        $activeBorrowing = Borrowing::factory()->create([
            'item_id' => $item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
            'quantity' => 2,
        ]);

        $returnedBorrowing = Borrowing::factory()->create([
            'item_id' => $item->id,
            'user_id' => $this->staff->id,
            'status' => 'dikembalikan',
            'quantity' => 1,
        ]);

        // When relations are NOT loaded
        $unloadedResource = new ItemResource($item);
        $unloadedData = $unloadedResource->toArray(Request::create('/api/items/' . $item->id));
        $this->assertInstanceOf(MissingValue::class, $unloadedData['active_borrowings']->resource);
        $this->assertInstanceOf(MissingValue::class, $unloadedData['borrowings']->resource);

        // When activeBorrowings is loaded
        $itemWithActive = Item::with('activeBorrowings.user')->find($item->id);
        $loadedResource = new ItemResource($itemWithActive);
        $loadedData = $loadedResource->toArray(Request::create('/api/items/' . $item->id));

        $this->assertCount(1, $loadedData['active_borrowings']);
        $firstActive = $loadedData['active_borrowings']->first();
        $this->assertInstanceOf(BorrowingResource::class, $firstActive);
        $this->assertEquals($activeBorrowing->id, $firstActive->id);
    }

    public function test_whitebox_item_resource_includes_borrowings_count_when_present(): void
    {
        $item = Item::factory()->create(['category_id' => $this->category->id]);
        Borrowing::factory()->count(3)->create([
            'item_id' => $item->id,
            'user_id' => $this->staff->id,
        ]);

        // With count
        $itemWithCount = Item::withCount('borrowings')->find($item->id);
        $resourceWithCount = new ItemResource($itemWithCount);
        $dataWithCount = $resourceWithCount->toArray(Request::create('/api/items/' . $item->id));
        $this->assertEquals(3, $dataWithCount['borrowings_count']);

        // Without count
        $itemWithoutCount = Item::find($item->id);
        $resourceWithoutCount = new ItemResource($itemWithoutCount);
        $dataWithoutCount = $resourceWithoutCount->toArray(Request::create('/api/items/' . $item->id));
        $this->assertInstanceOf(MissingValue::class, $dataWithoutCount['borrowings_count']);
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (HTTP Endpoints index, store, show, update standard formatting)
     * ========================================================================= */

    public function test_blackbox_items_index_returns_paginated_item_resources_with_category(): void
    {
        Item::factory()->count(3)->create([
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->getJson('/api/items?per_page=2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'description',
                        'category_id',
                        'stock',
                        'available_stock',
                        'condition',
                        'image',
                        'image_url',
                        'created_at',
                        'updated_at',
                        'category' => [
                            'id',
                            'name',
                            'description',
                        ],
                    ],
                ],
                'first_page_url',
                'from',
                'last_page',
                'per_page',
                'total',
            ]);

        $responseData = $response->json();
        $this->assertEquals(2, count($responseData['data']));
        $this->assertEquals(3, $responseData['total']);
        $this->assertIsInt($responseData['data'][0]['stock']);
        $this->assertIsInt($responseData['data'][0]['available_stock']);
    }

    public function test_blackbox_items_store_returns_item_resource_with_201_and_message(): void
    {
        $payload = [
            'name' => 'Multimeter Digital Sanwa',
            'code' => 'ITM-2026-SANWA',
            'category_id' => $this->category->id,
            'stock' => 12,
            'condition' => 'baik',
            'description' => 'Precision multimeter for electronics lab',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/items', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'name',
                    'category_id',
                    'stock',
                    'available_stock',
                    'condition',
                    'category' => [
                        'id',
                        'name',
                    ],
                ],
            ]);

        $response->assertJson([
            'message' => 'Item created successfully',
            'data' => [
                'name' => 'Multimeter Digital Sanwa',
                'stock' => 12,
                'available_stock' => 12,
                'condition' => 'baik',
                'category' => [
                    'id' => $this->category->id,
                    'name' => 'Elektronik Laboratorium',
                ],
            ],
        ]);
    }

    public function test_blackbox_items_show_returns_item_resource_with_loaded_relations(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 5,
            'available_stock' => 4,
            'condition' => 'baik',
        ]);

        $activeBorrowing = Borrowing::factory()->create([
            'item_id' => $item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->staff)
            ->getJson("/api/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'name',
                    'category_id',
                    'stock',
                    'available_stock',
                    'condition',
                    'image',
                    'image_url',
                    'category' => [
                        'id',
                        'name',
                    ],
                    'active_borrowings' => [
                        '*' => [
                            'id',
                            'borrow_code',
                            'quantity',
                            'status',
                            'user' => [
                                'id',
                                'name',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertJson([
            'data' => [
                'id' => $item->id,
                'category' => [
                    'id' => $this->category->id,
                    'name' => 'Elektronik Laboratorium',
                ],
                'active_borrowings' => [
                    [
                        'id' => $activeBorrowing->id,
                        'status' => 'dipinjam',
                        'user' => [
                            'id' => $this->staff->id,
                            'name' => $this->staff->name,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_blackbox_items_update_returns_item_resource_with_200_and_message(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Soldering Station Hakko',
            'stock' => 4,
            'available_stock' => 4,
            'condition' => 'baik',
        ]);

        $payload = [
            'name' => 'Soldering Station Hakko FX-888D',
            'category_id' => $this->category->id,
            'stock' => 6,
            'condition' => 'baik',
            'description' => 'Updated temperature controlled soldering station',
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/items/{$item->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'stock',
                    'available_stock',
                    'category' => [
                        'id',
                        'name',
                    ],
                ],
            ]);

        $response->assertJson([
            'message' => 'Item updated successfully',
            'data' => [
                'id' => $item->id,
                'name' => 'Soldering Station Hakko FX-888D',
                'stock' => 6,
                'available_stock' => 6,
                'category' => [
                    'id' => $this->category->id,
                ],
            ],
        ]);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Query Log / N+1 Prevention & DB Consistency)
     * ========================================================================= */

    public function test_greybox_items_index_eager_loads_category_without_n_plus_one(): void
    {
        // Create 10 items in the same or multiple categories
        Item::factory()->count(10)->create([
            'category_id' => $this->category->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->staff)
            ->getJson('/api/items?per_page=10');

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // There should only be:
        // 1. Session / token auth lookup (if any)
        // 2. Count query for pagination (select count(*) as aggregate from items)
        // 3. Paginated items query (select * from items limit 10 offset 0)
        // 4. Eager-loaded category query (select * from categories where id in (...))
        // Total queries for items & relations should be bounded (<= 5), NOT 10+ queries
        $this->assertLessThanOrEqual(5, count($queries));
    }

    public function test_greybox_items_show_eager_loads_category_and_active_borrowings_with_user(): void
    {
        $item = Item::factory()->create([
            'category_id' => $this->category->id,
            'stock' => 10,
            'available_stock' => 8,
        ]);

        Borrowing::factory()->count(3)->create([
            'item_id' => $item->id,
            'user_id' => $this->staff->id,
            'status' => 'dipinjam',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->staff)
            ->getJson("/api/items/{$item->id}");

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Bounded queries: route binding item + load category + load activeBorrowings + load user
        $this->assertLessThanOrEqual(5, count($queries));
    }
}
