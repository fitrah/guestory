<?php

namespace Tests\Feature;

use App\Models\AccountToken;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_seeded_platform_accounts_are_superadmins(): void
    {
        $this->seed();
        $this->assertDatabaseHas('users', ['email' => 'fitrahajah@gmail.com', 'role' => 'SUPERADMIN']);
        $this->assertDatabaseHas('users', ['email' => 'fahrurrozirahmawan@gmail.com', 'role' => 'SUPERADMIN']);
    }

    public function test_owner_self_registration_requires_verification_before_login(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => ' Owner@Example.COM ',
            'password' => 'secure123', 'password_confirmation' => 'secure123',
            'terms_accepted' => true, 'privacy_accepted' => true, 'website' => '',
        ])->assertAccepted()->assertJsonPath('code', 'VERIFICATION_LINK_SENT');

        $response->assertJsonMissingPath('token');
        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame('EVENT_OWNER', $owner->role);
        $this->assertSame('PENDING_VERIFICATION', $owner->status);
        $this->postJson('/api/auth/login', ['email' => $owner->email, 'password' => 'secure123'])->assertUnprocessable();

        $token = AccountToken::where('user_id', $owner->id)->where('purpose', 'VERIFY_EMAIL')->firstOrFail();
        // Plain tokens are intentionally unavailable from storage; issue one to exercise consumption.
        $plain = AccountToken::issue($owner, 'VERIFY_EMAIL', 30);
        $this->postJson('/api/auth/email/verify', ['token' => $plain])->assertOk()->assertJsonPath('code', 'EMAIL_VERIFIED');
        $this->assertNotNull($token);
        $this->postJson('/api/auth/login', ['email' => $owner->email, 'password' => 'secure123'])->assertOk();
    }

    public function test_registration_duplicate_has_generic_response(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'role' => 'EVENT_OWNER']);
        $this->postJson('/api/auth/register', [
            'name' => 'Someone', 'email' => 'OWNER@example.com', 'password' => 'secure123',
            'password_confirmation' => 'secure123', 'terms_accepted' => true, 'privacy_accepted' => true,
        ])->assertAccepted()->assertJsonPath('code', 'VERIFICATION_LINK_SENT');
        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['owner@example.com'])->count());
    }

    public function test_superadmin_provisions_owner_without_plaintext_password_and_owner_activates(): void
    {
        $this->seed();
        $superadmin = User::where('email', 'fitrahajah@gmail.com')->firstOrFail();
        [$accessToken] = $superadmin->createAccessToken();

        $this->postJson('/api/superadmin/users', ['name' => 'Provisioned Owner', 'email' => 'provisioned@example.com'], $this->headers($accessToken))
            ->assertCreated()->assertJsonPath('user.role', 'EVENT_OWNER')->assertJsonMissingPath('password');

        $owner = User::where('email', 'provisioned@example.com')->firstOrFail();
        $this->assertNull($owner->password);
        $this->assertSame('PENDING_ACTIVATION', $owner->status);
        $plain = AccountToken::issue($owner, 'ACTIVATE_ACCOUNT', 60);
        $this->postJson('/api/auth/activate', ['token' => $plain, 'password' => 'owner1234', 'password_confirmation' => 'owner1234'])
            ->assertOk()->assertJsonPath('code', 'ACCOUNT_ACTIVATED');
        $this->assertTrue(Hash::check('owner1234', $owner->refresh()->password));
    }

    public function test_event_owner_cannot_use_superadmin_user_management_and_remains_owner_scoped(): void
    {
        $owner = User::factory()->create(['role' => 'EVENT_OWNER', 'status' => 'ACTIVE']);
        $other = User::factory()->create(['role' => 'EVENT_OWNER', 'status' => 'ACTIVE']);
        $event = Event::create(['owner_id' => $other->id, 'name' => 'Other Event', 'type' => 'Wedding', 'date' => '2026-10-01', 'status' => 'Draft']);
        [$token] = $owner->createAccessToken();

        $this->getJson('/api/superadmin/users', $this->headers($token))->assertForbidden();
        $this->getJson("/api/admin/events/{$event->id}", $this->headers($token))->assertForbidden()->assertJsonPath('code', 'EVENT_FORBIDDEN');
    }

    private function headers(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }
}
