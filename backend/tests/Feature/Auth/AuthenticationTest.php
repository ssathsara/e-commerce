<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_customer_can_register_and_receives_customer_role(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'Jane@Example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.roles', ['Customer'])
            ->assertJsonPath('data.permissions', [])
            ->assertJsonMissingPath('data.user.password');

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertTrue(Hash::check('Secret123!', $user->password));
        $this->assertNotSame('Secret123!', $user->getRawOriginal('password'));
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_validates_input(): void
    {
        $this->customer(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_can_login_and_fetch_profile_with_roles_and_permissions(): void
    {
        $admin = $this->admin();

        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'Password123!'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $admin->id)
            ->assertJsonPath('data.roles', ['Admin']);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', $admin->email)
            ->assertJsonFragment(['create products']);

        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->customer();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = $this->customer(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!'])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_deactivated_user_session_is_rejected(): void
    {
        $user = $this->customer();
        $user->forceFill(['is_active' => false])->save();

        $this->signIn($user)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_guest_cannot_access_protected_endpoints(): void
    {
        foreach (['/api/v1/auth/me', '/api/v1/orders', '/api/v1/account/profile', '/api/v1/account/addresses', '/api/v1/admin/dashboard'] as $uri) {
            $this->getJson($uri)->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        }
    }

    public function test_user_can_logout(): void
    {
        $user = $this->customer();

        $this->signIn($user)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->customer();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_forgot_password_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        $user = $this->customer();

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = $this->customer();
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('BrandNew123!', $user->fresh()->password));

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'Another123!',
            'password_confirmation' => 'Another123!',
        ])->assertStatus(422);
    }

    public function test_email_can_be_verified_with_signed_link(): void
    {
        $user = User::factory()->unverified()->withRole('Customer')->create();

        $path = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ], absolute: false);

        $this->signIn($user)->postJson($path)->assertOk()->assertJsonPath('data.user.email_verified', true);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->withRole('Customer')->create();

        $this->signIn($user)->postJson("/api/v1/auth/email/verify/{$user->id}/".sha1($user->email).'?signature=forged')
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
