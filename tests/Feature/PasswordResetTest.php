<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // LAYER 1: White-Box Testing
    // =========================================================================

    /**
     * Test ResetPasswordNotification mail formatting and frontend reset URL generation.
     */
    public function test_reset_password_notification_renders_proper_mail_and_url(): void
    {
        $user = User::factory()->create([
            'name' => 'John Reset',
            'email' => 'john.reset@example.com',
        ]);

        $notification = new ResetPasswordNotification('test-token-xyz-123');
        $this->assertTrue($notification instanceof \Illuminate\Contracts\Queue\ShouldQueue);
        $this->assertEquals(['mail'], $notification->via($user));

        $mailMessage = $notification->toMail($user);

        $this->assertStringContainsString('Permintaan Reset Password', $mailMessage->subject);
        $this->assertStringContainsString('Halo John Reset,', $mailMessage->greeting);
        $this->assertEquals('Reset Password', $mailMessage->actionText);
        $this->assertStringContainsString('token=test-token-xyz-123', $mailMessage->actionUrl);
        $this->assertStringContainsString('email=john.reset%40example.com', $mailMessage->actionUrl);
    }

    /**
     * Test password reset rejects weak passwords that violate StrongPassword rule.
     */
    public function test_reset_password_rejects_weak_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'weakreset@example.com',
        ]);

        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);

        // Password lacking uppercase and special characters
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'weakreset@example.com',
            'password' => 'weakpassword123',
            'password_confirmation' => 'weakpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // =========================================================================
    // LAYER 2: Black-Box Testing
    // =========================================================================

    /**
     * Test forgot-password endpoint dispatches notification when email exists.
     */
    public function test_forgot_password_succeeds_and_dispatches_notification_for_registered_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tautan reset password telah dikirimkan ke email Anda.',
            ]);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) {
                return !empty($notification->token);
            }
        );
    }

    /**
     * Test forgot-password returns 422 when email does not exist.
     */
    public function test_forgot_password_fails_when_email_not_registered(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'notfound@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Email tidak ditemukan dalam sistem.',
            ]);

        Notification::assertNothingSent();
    }

    /**
     * Test reset-password fails with 422 when confirmation does not match.
     */
    public function test_reset_password_fails_on_password_confirmation_mismatch(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'mismatch@example.com',
        ]);

        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'mismatch@example.com',
            'password' => 'NewP@ssword123!',
            'password_confirmation' => 'DifferentP@ssword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test reset-password fails with 422 when token is invalid or expired.
     */
    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'invalidtoken@example.com',
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'completely-invalid-token-12345',
            'email' => 'invalidtoken@example.com',
            'password' => 'NewP@ssword123!',
            'password_confirmation' => 'NewP@ssword123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Token reset password tidak valid atau telah kedaluwarsa.',
            ]);
    }

    // =========================================================================
    // LAYER 3: Grey-Box Testing
    // =========================================================================

    /**
     * Test full end-to-end lifecycle: token generation, reset execution, DB mutation,
     * token table cleanup, old token revocation, and successful login with new password.
     */
    public function test_greybox_full_password_reset_lifecycle_and_login(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'lifecycle@example.com',
            'password' => Hash::make('OldP@ssword123!'),
        ]);

        // Create an existing personal access token to verify revocation
        $existingToken = $user->createToken('old-device')->plainTextToken;
        $this->assertNotEmpty($existingToken);
        $this->assertEquals(1, $user->tokens()->count());

        // Generate reset token via broker
        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);

        // Verify token was stored in DB
        $this->assertTrue(DB::table('password_reset_tokens')->where('email', 'lifecycle@example.com')->exists());

        // Submit password reset
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'lifecycle@example.com',
            'password' => 'NewP@ssword123!',
            'password_confirmation' => 'NewP@ssword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password Anda berhasil direset. Silakan login kembali.',
            ]);

        // 1. Verify user password updated in DB
        $user->refresh();
        $this->assertTrue(Hash::check('NewP@ssword123!', $user->password));
        $this->assertFalse(Hash::check('OldP@ssword123!', $user->password));

        // 2. Verify password_reset_tokens record was deleted (single-use)
        $this->assertFalse(DB::table('password_reset_tokens')->where('email', 'lifecycle@example.com')->exists());

        // 3. Verify prior access tokens were revoked
        $this->assertEquals(0, $user->tokens()->count());

        // 4. Verify login with old credentials fails
        $failedLogin = $this->postJson('/api/auth/login', [
            'email' => 'lifecycle@example.com',
            'password' => 'OldP@ssword123!',
        ]);
        $failedLogin->assertStatus(422);

        // 5. Verify login with new credentials succeeds
        $successfulLogin = $this->postJson('/api/auth/login', [
            'email' => 'lifecycle@example.com',
            'password' => 'NewP@ssword123!',
        ]);
        $successfulLogin->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }
}
