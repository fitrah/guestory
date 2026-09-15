<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\QRToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInvitationQrManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_invitation_and_qr_for_guest(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-003')->firstOrFail();

        $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/invitation/generate", [], $this->adminAuthHeaders())
            ->assertCreated()
            ->assertJsonPath('invitation.guest.name', 'Andi Wijaya')
            ->assertJsonPath('invitation.status', 'PUBLISHED');

        $this->assertDatabaseHas('guests', [
            'id' => $guest->id,
            'invitation_status' => 'SENT',
        ]);

        QRToken::where('guest_id', $guest->id)->delete();

        $qrResponse = $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/qr/generate", [], $this->adminAuthHeaders())
            ->assertCreated()
            ->assertJsonPath('qr.guest.name', 'Andi Wijaya')
            ->assertJsonPath('qr.status', 'ACTIVE');

        $this->assertNotEmpty($qrResponse->json('qr.token'));
        $this->assertStringContainsString('/api/g/', $qrResponse->json('qr.payload_url'));
        $this->assertSame(1, QRToken::where('guest_id', $guest->id)->where('status', 'ACTIVE')->count());
    }

    public function test_real_invitation_tokens_are_opaque_unique_and_regeneration_rotates_only_the_requested_guest(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guests = collect(['Opaque Guest One', 'Opaque Guest Two'])->map(fn (string $name, int $index) => Guest::create([
            'event_id' => $event->id,
            'guest_code' => 'OPAQUE-'.($index + 1),
            'name' => $name,
            'guest_count' => 1,
            'rsvp_status' => 'PENDING',
            'invitation_status' => 'NOT_SENT',
            'attendance_status' => 'NOT_CHECKED_IN',
        ]));

        $responses = $guests->map(fn (Guest $guest) => $this
            ->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/invitation/generate", [], $this->adminAuthHeaders())
            ->assertCreated());
        $tokens = $responses->map(fn ($response) => basename($response->json('invitation.url')));

        $this->assertCount(2, $tokens->unique());
        foreach ($tokens as $index => $token) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $token);
            $this->assertNotSame((string) $guests[$index]->id, $token);
            $this->assertNotSame((string) $event->id, $token);
            $this->assertSame('/invite/'.$token, parse_url($responses[$index]->json('invitation.url'), PHP_URL_PATH));
        }

        $oldToken = $tokens[0];
        $newToken = basename($this
            ->postJson("/api/admin/events/{$event->id}/guests/{$guests[0]->id}/invitation/regenerate", [], $this->adminAuthHeaders())
            ->assertCreated()
            ->json('invitation.url'));

        $this->assertNotSame($oldToken, $newToken);
        $this->assertNotSame($tokens[1], $newToken);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $newToken);
        $this->assertDatabaseMissing('invitations', ['token' => $oldToken]);
        $this->assertDatabaseHas('invitations', ['guest_id' => $guests[1]->id, 'token' => $tokens[1]]);
    }

    public function test_demo_invitation_token_is_preserved_by_normal_generation(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-003')->firstOrFail();

        $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/invitation/generate", [], $this->adminAuthHeaders())
            ->assertCreated()
            ->assertJsonPath('invitation.url', fn (string $url) => str_ends_with($url, '/invite/invite-demo-andi'));

        $this->assertSame('invite-demo-andi', Invitation::where('guest_id', $guest->id)->value('token'));
    }

    public function test_admin_can_list_invitations_and_qr_codes(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->getJson("/api/admin/events/{$event->id}/invitations", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('invitations.0.status', 'PUBLISHED');

        $this->getJson("/api/admin/events/{$event->id}/qr-codes", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('qr_codes.0.event_id', $event->id);
    }

    public function test_qr_can_be_downloaded_as_svg(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();

        $response = $this->get("/api/admin/events/{$event->id}/guests/{$guest->id}/qr/download", $this->adminAuthHeaders());

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_invitation_and_qr_payloads_use_configured_public_origins(): void
    {
        $this->seed();
        config(['app.url' => 'https://api.example.test']);
        putenv('GUESTORY_WEB_URL=https://web.example.test');
        putenv('GUESTORY_API_URL=https://qr.example.test');
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();
        $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/invitation/generate", [], $this->adminAuthHeaders())
            ->assertCreated()->assertJsonPath('invitation.url', 'https://web.example.test/invite/invite-demo-budi');
        $qr = $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/qr/generate", [], $this->adminAuthHeaders())->assertCreated();
        $this->assertStringStartsWith('https://qr.example.test/api/g/', $qr->json('qr.payload_url'));
        putenv('GUESTORY_WEB_URL');
        putenv('GUESTORY_API_URL');
    }

    public function test_admin_can_revoke_activate_and_regenerate_qr_without_two_active_tokens(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();
        $originalToken = QRToken::where('guest_id', $guest->id)->where('status', 'ACTIVE')->firstOrFail();

        $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/qr/revoke", [], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'QR_REVOKED');

        $this->assertDatabaseHas('qr_tokens', [
            'id' => $originalToken->id,
            'status' => 'REVOKED',
        ]);

        $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/qr/activate", [], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('qr.status', 'ACTIVE');

        $regenerateResponse = $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/qr/regenerate", [], $this->adminAuthHeaders())
            ->assertCreated()
            ->assertJsonPath('qr.status', 'ACTIVE');

        $this->assertNotSame($originalToken->token, $regenerateResponse->json('qr.token'));
        $this->assertSame(1, QRToken::where('guest_id', $guest->id)->where('status', 'ACTIVE')->count());
        $this->assertDatabaseHas('qr_tokens', [
            'id' => $originalToken->id,
            'status' => 'REVOKED',
        ]);
    }

    public function test_other_admin_cannot_manage_guest_invitation_or_qr(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();
        $otherAdmin = User::create([
            'name' => 'Other Admin',
            'email' => 'qr-other-fitrahajah@gmail.com',
            'password' => 'password',
            'role' => 'EVENT_OWNER',
            'status' => 'ACTIVE',
        ]);
        [$token] = $otherAdmin->createAccessToken('test');

        $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/qr/regenerate", [], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'EVENT_FORBIDDEN');
    }

    private function adminAuthHeaders(): array
    {
        $user = User::where('email', 'fitrahajah@gmail.com')->firstOrFail();
        [$token] = $user->createAccessToken('test');

        return ['Authorization' => "Bearer {$token}"];
    }
}
