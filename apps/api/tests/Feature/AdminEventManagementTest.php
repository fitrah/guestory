<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminEventManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_list_update_publish_archive_and_delete_event(): void
    {
        $this->seed();

        $createResponse = $this->postJson('/api/admin/events', [
            'name' => 'Guestory Product Launch',
            'type' => 'Corporate',
            'description' => 'Launch night for Guestory.',
            'date' => '2026-12-01',
            'start_time' => '19:00',
            'end_time' => '21:30',
            'timezone' => 'Asia/Jakarta',
            'venue_name' => 'Kanezza Hall',
            'venue_address' => 'Jakarta',
            'map_url' => 'https://maps.example.com/kanezza-hall',
        ], $this->adminAuthHeaders())
            ->assertCreated()
            ->assertJsonPath('event.name', 'Guestory Product Launch')
            ->assertJsonPath('event.status', 'Draft');

        $eventId = $createResponse->json('event.id');

        $this->getJson('/api/admin/events?search=Product', $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('events.0.id', $eventId);

        $this->getJson("/api/admin/events/{$eventId}", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('event.type', 'Corporate');

        $this->patchJson("/api/admin/events/{$eventId}", [
            'name' => 'Guestory Launch Night',
            'venue_name' => 'Main Ballroom',
        ], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('event.name', 'Guestory Launch Night')
            ->assertJsonPath('event.venue_name', 'Main Ballroom');

        $this->postJson("/api/admin/events/{$eventId}/publish", [], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('event.status', 'Published');

        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'status' => 'Published',
        ]);

        $this->postJson("/api/admin/events/{$eventId}/archive", [], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('event.status', 'Archived');

        $this->deleteJson("/api/admin/events/{$eventId}", [], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'EVENT_DELETED');

        $this->assertDatabaseMissing('events', ['id' => $eventId]);
    }

    public function test_receiver_cannot_manage_admin_events(): void
    {
        $this->seed();

        $this->getJson('/api/admin/events', $this->receiverAuthHeaders())
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_admin_cannot_access_another_admin_event(): void
    {
        $this->seed();

        $otherAdmin = User::create([
            'name' => 'Other Admin',
            'email' => 'other-fitrahajah@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'EVENT_OWNER',
            'status' => 'ACTIVE',
        ]);

        $otherEvent = Event::create([
            'owner_id' => $otherAdmin->id,
            'name' => 'Private Other Event',
            'type' => 'Gathering',
            'date' => '2026-12-05',
            'status' => 'Draft',
        ]);

        $this->getJson("/api/admin/events/{$otherEvent->id}", $this->adminAuthHeaders())
            ->assertForbidden()
            ->assertJsonPath('code', 'EVENT_FORBIDDEN');

        $this->getJson('/api/admin/events?search=Private', $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'events');
    }

    public function test_event_validation_rejects_unknown_type_and_status(): void
    {
        $this->seed();

        $this->postJson('/api/admin/events', [
            'name' => 'Invalid Event',
            'type' => 'Marketplace',
            'date' => '2026-12-01',
            'status' => 'Live',
        ], $this->adminAuthHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'status']);
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
