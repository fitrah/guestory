<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
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
