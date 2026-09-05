<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteParityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin_parity@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff_parity@example.com',
        ]);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Route Collection & Endpoint Registration Parity)
     * ========================================================================= */

    public function test_whitebox_routes_registered_with_perfect_parity(): void
    {
        $routes = Route::getRoutes();

        $unversionedApiRoutes = [];
        $versionedApiRoutes = [];

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Exclude health check and swagger docs from version parity check
            if ($uri === 'api/health' || str_starts_with($uri, 'api/documentation') || str_starts_with($uri, 'api/oauth2')) {
                continue;
            }

            if (str_starts_with($uri, 'api/v1/')) {
                $relativeUri = substr($uri, strlen('api/v1/'));
                $method = implode('|', $route->methods());
                $versionedApiRoutes[$method . ' ' . $relativeUri] = true;
            } elseif (str_starts_with($uri, 'api/')) {
                $relativeUri = substr($uri, strlen('api/'));
                $method = implode('|', $route->methods());
                $unversionedApiRoutes[$method . ' ' . $relativeUri] = true;
            }
        }

        $this->assertNotEmpty($unversionedApiRoutes);
        $this->assertNotEmpty($versionedApiRoutes);

        // Every unversioned route must exist in v1
        foreach (array_keys($unversionedApiRoutes) as $routeSignature) {
            $this->assertArrayHasKey(
                $routeSignature,
                $versionedApiRoutes,
                "Route {$routeSignature} exists in unversioned API but missing in /api/v1/"
            );
        }

        // Every v1 route must exist in unversioned API
        foreach (array_keys($versionedApiRoutes) as $routeSignature) {
            $this->assertArrayHasKey(
                $routeSignature,
                $unversionedApiRoutes,
                "Route {$routeSignature} exists in /api/v1/ but missing in unversioned API"
            );
        }

        $this->assertCount(count($unversionedApiRoutes), $versionedApiRoutes);
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (HTTP Request Parity between /api and /api/v1)
     * ========================================================================= */

    public function test_blackbox_health_endpoint_is_accessible(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'timestamp']);
    }

    public function test_blackbox_auth_validation_parity(): void
    {
        // Unversioned
        $resUnversioned = $this->postJson('/api/auth/register', []);
        $resUnversioned->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        // Versioned
        $resVersioned = $this->postJson('/api/v1/auth/register', []);
        $resVersioned->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_blackbox_categories_parity(): void
    {
        Category::factory()->count(2)->create();

        // 1. GET unversioned
        $resUnversioned = $this->actingAs($this->admin)->getJson('/api/categories?all=true');
        $resUnversioned->assertStatus(200)
            ->assertJsonStructure(['data' => ['*' => ['id', 'name']]]);

        // 2. GET versioned
        $resVersioned = $this->actingAs($this->admin)->getJson('/api/v1/categories?all=true');
        $resVersioned->assertStatus(200)
            ->assertJsonStructure(['data' => ['*' => ['id', 'name']]]);

        $this->assertCount(count($resUnversioned->json('data')), $resVersioned->json('data'));
    }

    public function test_blackbox_items_parity(): void
    {
        $category = Category::factory()->create();
        Item::factory()->count(3)->create(['category_id' => $category->id]);

        // Unversioned
        $resUnversioned = $this->actingAs($this->staff)->getJson('/api/items');
        $resUnversioned->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total']);

        // Versioned
        $resVersioned = $this->actingAs($this->staff)->getJson('/api/v1/items');
        $resVersioned->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total']);

        $this->assertEquals($resUnversioned->json('total'), $resVersioned->json('total'));
    }

    public function test_blackbox_dashboard_parity(): void
    {
        // Unversioned
        $resUnversioned = $this->actingAs($this->admin)->getJson('/api/dashboard');
        $resUnversioned->assertStatus(200)
            ->assertJsonStructure(['items', 'borrowings', 'recent_borrowings']);

        // Versioned
        $resVersioned = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');
        $resVersioned->assertStatus(200)
            ->assertJsonStructure(['items', 'borrowings', 'recent_borrowings']);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Middleware Enforcement Parity & Protection)
     * ========================================================================= */

    public function test_greybox_unauthenticated_requests_blocked_identically(): void
    {
        // Unversioned protected endpoint
        $this->getJson('/api/items')->assertStatus(401);
        $this->getJson('/api/borrowings')->assertStatus(401);
        $this->getJson('/api/dashboard')->assertStatus(401);

        // Versioned protected endpoint
        $this->getJson('/api/v1/items')->assertStatus(401);
        $this->getJson('/api/v1/borrowings')->assertStatus(401);
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
    }

    public function test_greybox_admin_middleware_enforced_identically_across_prefixes(): void
    {
        // Staff accessing Admin routes on unversioned
        $this->actingAs($this->staff)->getJson('/api/users')->assertStatus(403);
        $this->actingAs($this->staff)->getJson('/api/activity-logs')->assertStatus(403);

        // Staff accessing Admin routes on versioned
        $this->actingAs($this->staff)->getJson('/api/v1/users')->assertStatus(403);
        $this->actingAs($this->staff)->getJson('/api/v1/activity-logs')->assertStatus(403);

        // Admin accessing Admin routes on unversioned
        $this->actingAs($this->admin)->getJson('/api/users')->assertStatus(200);
        $this->actingAs($this->admin)->getJson('/api/activity-logs')->assertStatus(200);

        // Admin accessing Admin routes on versioned
        $this->actingAs($this->admin)->getJson('/api/v1/users')->assertStatus(200);
        $this->actingAs($this->admin)->getJson('/api/v1/activity-logs')->assertStatus(200);
    }
}
