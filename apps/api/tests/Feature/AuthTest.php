<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_read_profile_and_logout(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'fitrahajah@gmail.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'SUPERADMIN');

        $token = $loginResponse->json('access_token');

        $this->assertNotEmpty($token);
        $this->assertDatabaseCount('access_tokens', 1);

        $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'fitrahajah@gmail.com');

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('code', 'LOGGED_OUT');

        $this->assertSame(0, AccessToken::count());

        $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->seed();

        $this->postJson('/api/auth/login', [
            'email' => 'fitrahajah@gmail.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_admin_can_update_profile_and_normalized_whatsapp_number(): void
    {
        $this->seed();
        $user = User::where('email', 'fitrahajah@gmail.com')->firstOrFail();
        [$token] = $user->createAccessToken('test');
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->patchJson('/api/auth/me', ['name' => 'Guestory Owner', 'whatsapp_number' => '6281234567890'], $headers)
            ->assertOk()->assertJsonPath('code', 'PROFILE_UPDATED')->assertJsonPath('user.whatsapp_number', '6281234567890');
        $this->patchJson('/api/auth/me', ['whatsapp_number' => '081234'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('whatsapp_number');
    }

    public function test_active_user_can_request_password_reset_link(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        $this->seed();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'fitrahajah@gmail.com',
        ])
            ->assertOk()
            ->assertJsonPath('code', 'PASSWORD_RESET_LINK_SENT');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'fitrahajah@gmail.com',
        ]);
    }

    public function test_password_can_be_reset_with_valid_token_and_existing_sessions_are_revoked(): void
    {
        $this->seed();

        $user = User::where('email', 'fitrahajah@gmail.com')->firstOrFail();
        $user->createAccessToken('web');
        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
            ->assertOk()
            ->assertJsonPath('code', 'PASSWORD_RESET');

        $user->refresh();

        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertSame(0, AccessToken::count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $user->email)->count());
    }

    public function test_password_reset_rejects_invalid_token(): void
    {
        $this->seed();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'fitrahajah@gmail.com',
            'token' => 'invalid-token',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_RESET_TOKEN');
    }
}
