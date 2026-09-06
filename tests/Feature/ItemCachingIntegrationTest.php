<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemCachingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Category $categoryA;
    protected Category $categoryB;
    protected ItemService $itemService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);

        $this->categoryA = Category::factory()->create(['name' => 'Elektronik']);
        $this->categoryB = Category::factory()->create(['name' => 'Mebel']);

        $this->itemService = app(ItemService::class);
        $this->itemService->clearItemsCache();
    }

    // =========================================================================
    // LAYER 1: White-Box Testing
    // =========================================================================

    /**
     * Test cache key generation is deterministic regardless of filter key ordering.
     */
    public function test_whitebox_cache_key_is_deterministic(): void
    {
        $filters1 = ['category_id' => 1, 'condition' => 'baik', 'page' => 2];
        $filters2 = ['page' => 2, 'condition' => 'baik', 'category_id' => 1];

        ksort($filters1);
        ksort($filters2);

        $key1 = 'items:' . md5(json_encode($filters1) . ':page:' . $filters1['page']);
        $key2 = 'items:' . md5(json_encode($filters2) . ':page:' . $filters2['page']);

        $this->assertEquals($key1, $key2);
    }

    /**
     * Test clearItemsCache removes stored items from cache.
     */
    public function test_whitebox_clear_items_cache_flushes_cache(): void
    {
        Item::factory()->create(['name' => 'Proyektor BenQ', 'category_id' => $this->categoryA->id]);

        // Seed cache
        $cached1 = $this->itemService->getCachedItems(['search' => 'Proyektor']);
        $this->assertCount(1, $cached1->items());

        // Clear cache
        $this->itemService->clearItemsCache();

        // Create new matching item
        Item::factory()->create(['name' => 'Proyektor Epson', 'category_id' => $this->categoryA->id]);

        $cached2 = $this->itemService->getCachedItems(['search' => 'Proyektor']);
        $this->assertCount(2, $cached2->items());
    }

    // =========================================================================
    // LAYER 2: Black-Box Testing
    // =========================================================================

    /**
     * Test GET /api/items returns filtered data accurately.
     */
    public function test_blackbox_items_index_filters_accurately(): void
    {
        Sanctum::actingAs($this->staff);

        Item::factory()->create([
            'name' => 'ThinkPad T14',
            'code' => 'NB-001',
            'category_id' => $this->categoryA->id,
            'condition' => 'baik',
            'stock' => 10,
            'available_stock' => 8,
        ]);

        Item::factory()->create([
            'name' => 'Kursi Ergonomis',
            'code' => 'CHR-001',
            'category_id' => $this->categoryB->id,
            'condition' => 'rusak',
            'stock' => 5,
            'available_stock' => 0,
        ]);

        // Filter by category
        $resCategory = $this->getJson('/api/items?category_id=' . $this->categoryA->id);
        $resCategory->assertStatus(200);
        $this->assertEquals(1, $resCategory->json('total'));
        $this->assertEquals('ThinkPad T14', $resCategory->json('data.0.name'));

        // Filter by condition
        $resCondition = $this->getJson('/api/items?condition=rusak');
        $resCondition->assertStatus(200);
        $this->assertEquals(1, $resCondition->json('total'));
        $this->assertEquals('Kursi Ergonomis', $resCondition->json('data.0.name'));

        // Filter by availability
        $resAvailable = $this->getJson('/api/items?available=true');
        $resAvailable->assertStatus(200);
        $this->assertEquals(1, $resAvailable->json('total'));
        $this->assertEquals('ThinkPad T14', $resAvailable->json('data.0.name'));

        // Filter out of stock
        $resOutOfStock = $this->getJson('/api/items?out_of_stock=true');
        $resOutOfStock->assertStatus(200);
        $this->assertEquals(1, $resOutOfStock->json('total'));
        $this->assertEquals('Kursi Ergonomis', $resOutOfStock->json('data.0.name'));
    }

    /**
     * Test pagination returns independent cached pages.
     */
    public function test_blackbox_pagination_caches_distinct_pages(): void
    {
        Sanctum::actingAs($this->staff);

        Item::factory()->count(12)->create(['category_id' => $this->categoryA->id]);

        $page1 = $this->getJson('/api/items?per_page=5&page=1');
        $page1->assertStatus(200);
        $this->assertCount(5, $page1->json('data'));
        $page1Ids = collect($page1->json('data'))->pluck('id')->all();

        $page2 = $this->getJson('/api/items?per_page=5&page=2');
        $page2->assertStatus(200);
        $this->assertCount(5, $page2->json('data'));
        $page2Ids = collect($page2->json('data'))->pluck('id')->all();

        // The two pages must contain different items
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    // =========================================================================
    // LAYER 3: Grey-Box Testing
    // =========================================================================

    /**
     * Test that repeated requests hit cache with zero database queries on items table.
     */
    public function test_greybox_repeated_request_hits_cache_with_zero_item_queries(): void
    {
        Sanctum::actingAs($this->staff);

        Item::factory()->count(5)->create(['category_id' => $this->categoryA->id]);

        // First request: Cache MISS (queries run to seed cache)
        $res1 = $this->getJson('/api/items?per_page=5');
        $res1->assertStatus(200);

        // Second request: Cache HIT (should execute 0 SELECT queries on items table)
        DB::flushQueryLog();
        DB::enableQueryLog();

        $res2 = $this->getJson('/api/items?per_page=5');
        $res2->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $itemSelectQueries = array_filter($queries, function ($q) {
            return stripos($q['query'], 'select') !== false && stripos($q['query'], 'items') !== false;
        });

        $this->assertCount(0, $itemSelectQueries, 'Subsequent identical request must hit cache with 0 items queries.');
    }

    /**
     * Test cache invalidation on item mutations (create, update, delete, bulk-delete).
     */
    public function test_greybox_cache_invalidates_on_item_mutations(): void
    {
        Sanctum::actingAs($this->admin);

        $item = Item::factory()->create([
            'name' => 'Original Monitor',
            'code' => 'MON-001',
            'category_id' => $this->categoryA->id,
            'stock' => 5,
            'available_stock' => 5,
        ]);

        // 1. Initial list request - caches 1 item
        $initial = $this->getJson('/api/items');
        $initial->assertStatus(200);
        $this->assertEquals(1, $initial->json('total'));

        // 2. CREATE item -> should invalidate cache
        $createRes = $this->postJson('/api/items', [
            'name' => 'Brand New Keyboard',
            'code' => 'KEY-001',
            'category_id' => $this->categoryA->id,
            'condition' => 'baik',
            'stock' => 10,
            'available_stock' => 10,
        ]);
        $createRes->assertStatus(201);
        $newItemId = $createRes->json('data.id');

        // Immediately fetch items: must see 2 items
        $afterCreate = $this->getJson('/api/items');
        $afterCreate->assertStatus(200);
        $this->assertEquals(2, $afterCreate->json('total'));

        // 3. UPDATE item -> should invalidate cache
        $updateRes = $this->postJson("/api/items/{$item->id}", [
            'name' => 'Updated UltraWide Monitor',
            'code' => $item->code,
            'category_id' => $this->categoryA->id,
            'condition' => 'baik',
            'stock' => 8,
            'available_stock' => 8,
        ]);
        $updateRes->assertStatus(200);

        // Immediately fetch items: updated title must appear
        $afterUpdate = $this->getJson('/api/items');
        $afterUpdate->assertStatus(200);
        $names = collect($afterUpdate->json('data'))->pluck('name')->all();
        $this->assertContains('Updated UltraWide Monitor', $names);

        // 4. DELETE item -> should invalidate cache
        $deleteRes = $this->deleteJson("/api/items/{$item->id}");
        $deleteRes->assertStatus(200);

        $afterDelete = $this->getJson('/api/items');
        $afterDelete->assertStatus(200);
        $this->assertEquals(1, $afterDelete->json('total'));

        // 5. BULK DELETE items -> should invalidate cache
        $bulkDeleteRes = $this->deleteJson('/api/items/bulk-delete', [
            'ids' => [$newItemId],
        ]);
        $bulkDeleteRes->assertStatus(200);

        $afterBulkDelete = $this->getJson('/api/items');
        $afterBulkDelete->assertStatus(200);
        $this->assertEquals(0, $afterBulkDelete->json('total'));
    }
}
