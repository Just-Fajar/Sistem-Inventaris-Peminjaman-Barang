<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RegisterSecurityTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // LAYER 1: White-Box Testing
    // =========================================================================

    /**
     * Test that 'role' is protected from mass assignment on User model.
     */
    public function test_user_model_role_is_not_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Mass Assign Attempt',
            'email' => 'massassign@example.com',
            'password' => 'P@ssword123!',
            'role' => 'admin',
        ]);

        // Model mass assignment must not assign 'admin'; it should default to 'staff'
        $this->assertEquals('staff', $user->fresh()->role);
    }

    /**
     * Test validation fails when password does not meet StrongPassword rule requirements.
     */
    public function test_registration_validation_fails_on_weak_password(): void
    {
        // Missing uppercase and special char
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Weak Pass User',
            'email' => 'weak@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test validation fails when password confirmation is missing or mismatch.
     */
    public function test_registration_validation_fails_on_password_confirmation_mismatch(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'P@ssword123!',
            'password_confirmation' => 'DifferentP@ss123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // =========================================================================
    // LAYER 2: Black-Box Testing
    // =========================================================================

    /**
     * Test privilege escalation attempt by injecting 'role' field is rejected with 422.
     */
    public function test_registration_rejects_payload_containing_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'P@ssword123!',
            'password_confirmation' => 'P@ssword123!',
            'role' => 'admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', [
            'email' => 'attacker@example.com',
        ]);
    }

    /**
     * Test normal valid registration succeeds with 201 and assigns staff role.
     */
    public function test_successful_registration_returns_201_and_staff_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Staff Member',
            'email' => 'newstaff@example.com',
            'password' => 'P@ssword123!',
            'password_confirmation' => 'P@ssword123!',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User registered successfully',
                'user' => [
                    'name' => 'New Staff Member',
                    'email' => 'newstaff@example.com',
                    'role' => 'staff',
                ],
            ])
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);
    }

    /**
     * Test registration fails with 422 when email is already registered.
     */
    public function test_registration_fails_when_email_already_exists(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate User',
            'email' => 'existing@example.com',
            'password' => 'P@ssword123!',
            'password_confirmation' => 'P@ssword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration is blocked with 403 when registration is disabled via config.
     */
    public function test_registration_returns_403_when_disabled(): void
    {
        Config::set('auth.registration_enabled', false);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Blocked Registration',
            'email' => 'blocked@example.com',
            'password' => 'P@ssword123!',
            'password_confirmation' => 'P@ssword123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Registration is currently disabled. Contact administrator.',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    // =========================================================================
    // LAYER 3: Grey-Box Testing
    // =========================================================================

    /**
     * Test registered user in DB strictly has role 'staff' and token can access protected /api/auth/me.
     */
    public function test_registered_user_persisted_as_staff_and_token_grants_access(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Greybox Verified User',
            'email' => 'greybox@example.com',
            'password' => 'P@ssword123!',
            'password_confirmation' => 'P@ssword123!',
        ]);

        $response->assertStatus(201);
        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // Verify DB state
        $user = User::where('email', 'greybox@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('staff', $user->role);

        // Verify Bearer token authorization
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'email' => 'greybox@example.com',
                    'role' => 'staff',
                ],
            ]);
    }
}
