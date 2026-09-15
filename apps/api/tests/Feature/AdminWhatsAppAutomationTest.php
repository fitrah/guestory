<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminWhatsAppAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_invitation_whatsapp_in_dry_run_mode(): void
    {
        $this->seed();
        Config::set('services.wapi.dry_run', true);

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();

        $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/whatsapp/invitation", [], $this->adminAuthHeaders())
            ->assertAccepted()
            ->assertJsonPath('code', 'WHATSAPP_INVITATION_QUEUED')
            ->assertJsonPath('whatsapp_message.status', 'SKIPPED')
            ->assertJsonPath('whatsapp_message.recipient', '628121110001');

        $this->assertDatabaseHas('whatsapp_messages', [
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'status' => 'SKIPPED',
            'message_type' => 'INVITATION',
        ]);
    }

    public function test_admin_can_send_invitation_whatsapp_through_wapi_contract(): void
    {
        $this->seed();
        Config::set('services.wapi.dry_run', false);
        Config::set('services.wapi.api_key', 'test-key');
        Config::set('services.wapi.number_id', 'number-1');
        Config::set('services.wapi.base_url', 'https://wapi.test');
        Http::fake([
            'wapi.test/api/messages/send-text' => Http::response([
                'data' => [
                    'id' => 'wapi-message-1',
                    'status' => 'queued',
                ],
            ], 202),
        ]);

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();

        $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/whatsapp/invitation", [], $this->adminAuthHeaders())
            ->assertAccepted()
            ->assertJsonPath('whatsapp_message.status', 'QUEUED')
            ->assertJsonPath('whatsapp_message.provider_message_id', 'wapi-message-1');

        Http::assertSent(fn ($request) => $request->url() === 'https://wapi.test/api/messages/send-text'
            && $request->hasHeader('x-api-key', 'test-key')
            && $request['numberId'] === 'number-1'
            && $request['recipient'] === '628121110001'
            && str_contains($request['body'], '/invite/invite-demo-budi'));
    }

    public function test_admin_can_bulk_send_and_list_whatsapp_logs(): void
    {
        $this->seed();
        Config::set('services.wapi.dry_run', true);

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->postJson("/api/admin/events/{$event->id}/whatsapp/invitations/send", [], $this->adminAuthHeaders())
            ->assertAccepted()
            ->assertJsonPath('processed', 4)
            ->assertJsonCount(4, 'messages');

        $this->getJson("/api/admin/events/{$event->id}/whatsapp/messages?status=SKIPPED", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.skipped', 4)
            ->assertJsonCount(4, 'messages');
    }

    public function test_admin_can_resend_whatsapp_message(): void
    {
        $this->seed();
        Config::set('services.wapi.dry_run', true);

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();

        $messageId = $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/whatsapp/invitation", [], $this->adminAuthHeaders())
            ->assertAccepted()
            ->json('whatsapp_message.id');

        $this->postJson("/api/admin/events/{$event->id}/whatsapp/messages/{$messageId}/resend", [], $this->adminAuthHeaders())
            ->assertAccepted()
            ->assertJsonPath('code', 'WHATSAPP_MESSAGE_RESENT')
            ->assertJsonPath('whatsapp_message.status', 'SKIPPED');

        $this->assertDatabaseCount('whatsapp_messages', 2);
    }

    public function test_other_admin_cannot_send_event_whatsapp_invitation(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();
        $otherAdmin = User::create([
            'name' => 'Other WhatsApp Admin',
            'email' => 'whatsapp-other-fitrahajah@gmail.com',
            'password' => 'password',
            'role' => 'EVENT_OWNER',
            'status' => 'ACTIVE',
        ]);
        [$token] = $otherAdmin->createAccessToken('test');

        $this->postJson("/api/admin/events/{$event->id}/guests/{$guest->id}/whatsapp/invitation", [], ['Authorization' => "Bearer {$token}"])
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
