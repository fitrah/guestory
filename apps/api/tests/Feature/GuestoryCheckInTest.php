<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestoryCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_personalized_invitation_with_active_qr(): void
    {
        $this->seed();

        $response = $this->getJson('/api/invite/invite-demo-budi');

        $response
            ->assertOk()
            ->assertJsonPath('event.name', 'Andi & Sinta Wedding')
            ->assertJsonPath('guest.name', 'Budi Santoso')
            ->assertJsonPath('qr.token', 'demo-qr-budi');

        $this->assertDatabaseHas('guests', [
            'guest_code' => 'GUEST-001',
            'invitation_status' => 'OPENED',
        ]);
    }

    public function test_receiver_can_validate_active_qr(): void
    {
        $this->seed();

        $this->postJson('/api/receiver/check-in/validate', [
            'token' => 'demo-qr-budi',
        ], $this->receiverAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'CHECK_IN_ALLOWED')
            ->assertJsonPath('guest.name', 'Budi Santoso');
    }

    public function test_revoked_qr_is_rejected(): void
    {
        $this->seed();

        $this->postJson('/api/receiver/check-in/validate', [
            'token' => 'demo-qr-revoked',
        ], $this->receiverAuthHeaders())
            ->assertConflict()
            ->assertJsonPath('code', 'QR_REVOKED');
    }

    public function test_qr_check_in_creates_single_attendance_record(): void
    {
        $this->seed();

        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();

        $this->postJson('/api/receiver/check-in/confirm', [
            'token' => 'demo-qr-budi',
            'method' => 'QR',
            'actual_guest_count' => 2,
        ], $this->receiverAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'CHECK_IN_SUCCESS')
            ->assertJsonPath('guest.actual_guest_count', 2);

        $this->assertSame(1, CheckIn::where('guest_id', $guest->id)->count());
        $this->assertDatabaseHas('guests', [
            'id' => $guest->id,
            'attendance_status' => 'CHECKED_IN',
        ]);

        $this->postJson('/api/receiver/check-in/confirm', [
            'token' => 'demo-qr-budi',
            'method' => 'QR',
            'actual_guest_count' => 2,
        ], $this->receiverAuthHeaders())
            ->assertConflict()
            ->assertJsonPath('code', 'ALREADY_CHECKED_IN');

        $this->assertSame(1, CheckIn::where('guest_id', $guest->id)->count());
    }

    public function test_admin_dashboard_requires_admin_access(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->getJson("/api/admin/events/{$event->id}/dashboard")
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->getJson("/api/admin/events/{$event->id}/dashboard", $this->receiverAuthHeaders())
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->getJson("/api/admin/events/{$event->id}/dashboard", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('event.name', 'Andi & Sinta Wedding')
            ->assertJsonStructure([
                'check_in_activity' => [['label', 'total']],
                'category_counts' => [['category', 'total']],
            ]);
    }

    public function test_receiver_routes_require_receiver_access(): void
    {
        $this->seed();

        $this->postJson('/api/receiver/check-in/validate', [
            'token' => 'demo-qr-budi',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->postJson('/api/receiver/check-in/validate', [
            'token' => 'demo-qr-budi',
        ], $this->adminAuthHeaders())
            ->assertForbidden()
            ->assertJsonPath('code', 'RECEIVER_EVENT_FORBIDDEN');
    }

    private function adminAuthHeaders(): array
    {
        return $this->authHeaders('fitrahajah@gmail.com');
    }

    private function receiverAuthHeaders(): array
    {
        return $this->authHeaders('receiver@guestory.local');
    }

    private function authHeaders(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        [$token] = $user->createAccessToken('test');

        return ['Authorization' => "Bearer {$token}"];
    }
}
