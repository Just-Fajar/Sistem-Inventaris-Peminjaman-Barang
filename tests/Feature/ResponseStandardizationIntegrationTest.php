<?php

namespace Tests\Feature;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\UserResource;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResponseStandardizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Standard',
            'role' => 'admin',
            'email' => 'admin_standard@example.com',
        ]);

        $this->staff = User::factory()->create([
            'name' => 'Staff Standard',
            'role' => 'staff',
            'email' => 'staff_standard@example.com',
        ]);

        Cache::flush();
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Resource Transformation & Casting)
     * ========================================================================= */

    public function test_whitebox_category_resource_transforms_attributes_and_casts_count_correctly(): void
    {
        $category = Category::factory()->create([
            'name' => 'Alat Audio Visual',
            'description' => 'Mikrofon, proyektor, dan sound system',
        ]);

        Item::factory()->count(3)->create([
            'category_id' => $category->id,
            'stock' => 5,
            'available_stock' => 5,
        ]);

        // 1. Without withCount
        $resourceWithoutCount = new CategoryResource($category);
        $dataWithoutCount = $resourceWithoutCount->toArray(Request::create('/api/categories/' . $category->id));

        $this->assertEquals($category->id, $dataWithoutCount['id']);
        $this->assertEquals('Alat Audio Visual', $dataWithoutCount['name']);
        $this->assertEquals('Mikrofon, proyektor, dan sound system', $dataWithoutCount['description']);
        $this->assertEquals($category->created_at->format('Y-m-d H:i:s'), $dataWithoutCount['created_at']);
        $this->assertEquals($category->updated_at->format('Y-m-d H:i:s'), $dataWithoutCount['updated_at']);
        $this->assertInstanceOf(MissingValue::class, $dataWithoutCount['items_count']);

        // 2. With withCount
        $categoryWithCount = Category::withCount('items')->find($category->id);
        $resourceWithCount = new CategoryResource($categoryWithCount);
        $dataWithCount = $resourceWithCount->toArray(Request::create('/api/categories/' . $category->id));

        $this->assertSame(3, $dataWithCount['items_count']);
        $this->assertIsInt($dataWithCount['items_count']);

        // 3. Conditional items relation
        $this->assertInstanceOf(MissingValue::class, $dataWithCount['items']->resource);

        $categoryWithItems = Category::with('items')->find($category->id);
        $resourceWithItems = new CategoryResource($categoryWithItems);
        $dataWithItems = $resourceWithItems->toArray(Request::create('/api/categories/' . $category->id));

        $this->assertCount(3, $dataWithItems['items']);
        $this->assertInstanceOf(ItemResource::class, $dataWithItems['items']->first());
    }

    public function test_whitebox_user_resource_transforms_attributes_and_relations_correctly(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@example.com',
            'role' => 'staff',
        ]);

        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id]);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $user->id,
            'item_id' => $item->id,
            'status' => 'dipinjam',
        ]);

        // 1. Without borrowings loaded
        $resourceUnloaded = new UserResource($user);
        $dataUnloaded = $resourceUnloaded->toArray(Request::create('/api/users/' . $user->id));

        $this->assertEquals($user->id, $dataUnloaded['id']);
        $this->assertEquals('Budi Santoso', $dataUnloaded['name']);
        $this->assertEquals('budi.santoso@example.com', $dataUnloaded['email']);
        $this->assertEquals('staff', $dataUnloaded['role']);
        $this->assertEquals($user->created_at->format('Y-m-d H:i:s'), $dataUnloaded['created_at']);
        $this->assertEquals($user->updated_at->format('Y-m-d H:i:s'), $dataUnloaded['updated_at']);
        $this->assertInstanceOf(MissingValue::class, $dataUnloaded['borrowings']->resource);

        // 2. With borrowings loaded
        $userLoaded = User::with('borrowings')->find($user->id);
        $resourceLoaded = new UserResource($userLoaded);
        $dataLoaded = $resourceLoaded->toArray(Request::create('/api/users/' . $user->id));

        $this->assertCount(1, $dataLoaded['borrowings']);
        $this->assertEquals($borrowing->id, $dataLoaded['borrowings']->first()->id);
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (Standard HTTP Responses { data, message })
     * ========================================================================= */

    public function test_blackbox_categories_index_paginated_returns_standard_structure(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/categories?per_page=2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => ['id', 'name', 'description', 'created_at', 'updated_at', 'items_count'],
                ],
                'first_page_url',
                'from',
                'last_page',
                'per_page',
                'total',
            ]);

        $data = $response->json();
        $this->assertEquals(2, count($data['data']));
        $this->assertEquals(3, $data['total']);
    }

    public function test_blackbox_categories_index_all_returns_data_envelope_array(): void
    {
        Category::factory()->count(4)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/categories?all=true');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'created_at', 'updated_at', 'items_count'],
                ],
            ]);

        $data = $response->json();
        $this->assertEquals(4, count($data['data']));
    }

    public function test_blackbox_categories_store_returns_201_and_message(): void
    {
        $payload = [
            'name' => 'Peralatan Jaringan',
            'description' => 'Router, switch, crimping tool, dan kabel LAN',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/categories', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'description'],
            ])
            ->assertJson([
                'message' => 'Category created successfully',
                'data' => [
                    'name' => 'Peralatan Jaringan',
                    'description' => 'Router, switch, crimping tool, dan kabel LAN',
                ],
            ]);
    }

    public function test_blackbox_categories_show_returns_200_and_data_with_items(): void
    {
        $category = Category::factory()->create([
            'name' => 'Furnitur Kantor',
        ]);

        Item::factory()->create([
            'category_id' => $category->id,
            'name' => 'Meja Rapat Ergonomis',
            'stock' => 2,
            'available_stock' => 2,
            'condition' => 'baik',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'items' => [
                        '*' => ['id', 'name', 'stock', 'available_stock'],
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => 'Furnitur Kantor',
                ],
            ]);
    }

    public function test_blackbox_categories_update_returns_200_and_message(): void
    {
        $category = Category::factory()->create([
            'name' => 'Kategori Lama',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/categories/{$category->id}", [
                'name' => 'Kategori Baru Diperbarui',
                'description' => 'Deskripsi baru',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'description'],
            ])
            ->assertJson([
                'message' => 'Category updated successfully',
                'data' => [
                    'id' => $category->id,
                    'name' => 'Kategori Baru Diperbarui',
                    'description' => 'Deskripsi baru',
                ],
            ]);
    }

    public function test_blackbox_categories_delete_returns_200_or_422_when_has_items(): void
    {
        $categoryWithoutItems = Category::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/categories/{$categoryWithoutItems->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category deleted successfully',
            ]);

        $categoryWithItems = Category::factory()->create();
        Item::factory()->create([
            'category_id' => $categoryWithItems->id,
            'condition' => 'baik',
        ]);

        $conflictResponse = $this->actingAs($this->admin)
            ->deleteJson("/api/categories/{$categoryWithItems->id}");

        $conflictResponse->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete category with items',
            ]);
    }

    public function test_blackbox_users_endpoints_standard_responses(): void
    {
        // 1. Index
        $indexResponse = $this->actingAs($this->admin)
            ->getJson('/api/users');

        $indexResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'created_at'],
                ],
            ])
            ->assertJson(['success' => true]);

        // 2. Store
        $storeResponse = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'Staff Baru Lab',
                'email' => 'staff.baru.lab@example.com',
                'password' => 'SecureP@ssw0rd!123',
                'role' => 'staff',
            ]);

        $storeResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User berhasil ditambahkan',
                'data' => [
                    'name' => 'Staff Baru Lab',
                    'email' => 'staff.baru.lab@example.com',
                    'role' => 'staff',
                ],
            ]);

        $newUserId = $storeResponse->json('data.id');

        // 3. Show
        $showResponse = $this->actingAs($this->admin)
            ->getJson("/api/users/{$newUserId}");

        $showResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $newUserId,
                    'name' => 'Staff Baru Lab',
                ],
            ]);

        // 4. Update
        $updateResponse = $this->actingAs($this->admin)
            ->putJson("/api/users/{$newUserId}", [
                'name' => 'Staff Baru Terverifikasi',
                'email' => 'staff.baru.lab@example.com',
                'role' => 'staff',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User berhasil diperbarui',
                'data' => [
                    'id' => $newUserId,
                    'name' => 'Staff Baru Terverifikasi',
                ],
            ]);

        // 5. Delete
        $deleteResponse = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$newUserId}");

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User berhasil dihapus',
            ]);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Cache Invalidation & Query Count Bounds)
     * ========================================================================= */

    public function test_greybox_category_cache_is_invalidated_on_modifications(): void
    {
        // 1. Initial warm up cache
        $this->actingAs($this->admin)->getJson('/api/categories?all=true');
        $this->assertTrue(Cache::has('categories_all'));

        // 2. Store invalidates cache
        $this->actingAs($this->admin)->postJson('/api/categories', [
            'name' => 'Perangkat Jaringan Baru',
            'description' => 'Deskripsi',
        ]);
        $this->assertFalse(Cache::has('categories_all'));

        // 3. Warm up again
        $this->actingAs($this->admin)->getJson('/api/categories?all=true');
        $this->assertTrue(Cache::has('categories_all'));

        $category = Category::where('name', 'Perangkat Jaringan Baru')->first();

        // 4. Update invalidates cache
        $this->actingAs($this->admin)->putJson("/api/categories/{$category->id}", [
            'name' => 'Perangkat Jaringan Revisi',
        ]);
        $this->assertFalse(Cache::has('categories_all'));

        // 5. Warm up again
        $this->actingAs($this->admin)->getJson('/api/categories?all=true');
        $this->assertTrue(Cache::has('categories_all'));

        // 6. Delete invalidates cache
        $this->actingAs($this->admin)->deleteJson("/api/categories/{$category->id}");
        $this->assertFalse(Cache::has('categories_all'));
    }

    public function test_greybox_categories_index_query_count_is_bounded(): void
    {
        Category::factory()->count(10)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/categories?search=');

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Query count should be bounded (count query + paginated select query with subquery items_count)
        // rather than executing 10 separate queries for each category's items count
        $this->assertLessThanOrEqual(5, count($queries));
    }
}
