<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\EventReceiver;
use App\Models\Guest;
use App\Models\QRToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiverCheckInAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiver_can_list_only_assigned_events(): void
    {
        $this->seed();

        $otherEvent = Event::create([
            'owner_id' => User::where('email', 'fitrahajah@gmail.com')->value('id'),
            'name' => 'Private Corporate Dinner',
            'type' => 'Corporate',
            'date' => '2026-11-01',
            'status' => 'Published',
        ]);

        $this->getJson('/api/receiver/events', $this->receiverAuthHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.name', 'Andi & Sinta Wedding')
            ->assertJsonMissing(['name' => $otherEvent->name]);
    }

    public function test_receiver_can_validate_qr_for_selected_event(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->postJson("/api/receiver/events/{$event->id}/check-in/validate", [
            'token' => 'demo-qr-budi',
        ], $this->receiverAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'CHECK_IN_ALLOWED')
            ->assertJsonPath('event.name', 'Andi & Sinta Wedding');
    }

    public function test_receiver_cannot_validate_qr_for_unselected_event(): void
    {
        $this->seed();

        $admin = User::where('email', 'fitrahajah@gmail.com')->firstOrFail();
        $otherEvent = Event::create([
            'owner_id' => $admin->id,
            'name' => 'Other Published Event',
            'type' => 'Gathering',
            'date' => '2026-11-04',
            'status' => 'Published',
        ]);
        $guest = Guest::create([
            'event_id' => $otherEvent->id,
            'guest_code' => 'OTHER-001',
            'name' => 'Other Guest',
            'guest_count' => 1,
        ]);
        $qrToken = QRToken::create([
            'event_id' => $otherEvent->id,
            'guest_id' => $guest->id,
            'token' => 'other-event-token',
            'status' => 'ACTIVE',
        ]);

        EventReceiver::create([
            'event_id' => $otherEvent->id,
            'user_id' => User::where('email', 'receiver@guestory.local')->value('id'),
            'status' => 'ACTIVE',
            'created_at' => now(),
        ]);

        $selectedEvent = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->postJson("/api/receiver/events/{$selectedEvent->id}/check-in/validate", [
            'token' => $qrToken->token,
        ], $this->receiverAuthHeaders())
            ->assertConflict()
            ->assertJsonPath('code', 'INVALID_EVENT');
    }

    public function test_receiver_can_search_guests_for_manual_fallback(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->getJson("/api/receiver/events/{$event->id}/guests/search?q=Andi", $this->receiverAuthHeaders())
            ->assertOk()
            ->assertJsonPath('guests.0.name', 'Andi Wijaya')
            ->assertJsonPath('guests.0.attendance_status', 'NOT_CHECKED_IN');
    }

    public function test_receiver_can_manual_check_in_guest_and_see_recent_history(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-003')->firstOrFail();

        $this->postJson("/api/receiver/events/{$event->id}/check-ins/manual", [
            'guest_id' => $guest->id,
            'actual_guest_count' => 1,
        ], $this->receiverAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'CHECK_IN_SUCCESS')
            ->assertJsonPath('method', 'MANUAL')
            ->assertJsonPath('guest.name', 'Andi Wijaya');

        $this->assertSame(1, CheckIn::where('guest_id', $guest->id)->where('method', 'MANUAL')->count());
        $this->assertDatabaseHas('guests', [
            'id' => $guest->id,
            'attendance_status' => 'CHECKED_IN',
        ]);

        $this->getJson("/api/receiver/events/{$event->id}/check-ins/recent", $this->receiverAuthHeaders())
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Andi Wijaya',
            ])
            ->assertJsonFragment([
                'method' => 'MANUAL',
            ]);
    }

    public function test_receiver_cannot_search_or_check_in_unassigned_event(): void
    {
        $this->seed();

        $admin = User::where('email', 'fitrahajah@gmail.com')->firstOrFail();
        $event = Event::create([
            'owner_id' => $admin->id,
            'name' => 'Unassigned Event',
            'type' => 'Birthday',
            'date' => '2026-11-10',
            'status' => 'Published',
        ]);
        $guest = Guest::create([
            'event_id' => $event->id,
            'guest_code' => 'UNASSIGNED-001',
            'name' => 'Unassigned Guest',
            'guest_count' => 1,
        ]);

        $this->getJson("/api/receiver/events/{$event->id}/guests/search?q=Guest", $this->receiverAuthHeaders())
            ->assertForbidden()
            ->assertJsonPath('code', 'RECEIVER_EVENT_FORBIDDEN');

        $this->postJson("/api/receiver/events/{$event->id}/check-ins/manual", [
            'guest_id' => $guest->id,
        ], $this->receiverAuthHeaders())
            ->assertForbidden()
            ->assertJsonPath('code', 'RECEIVER_EVENT_FORBIDDEN');
    }

    private function receiverAuthHeaders(): array
    {
        $user = User::where('email', 'receiver@guestory.local')->firstOrFail();
        [$token] = $user->createAccessToken('test');

        return ['Authorization' => "Bearer {$token}"];
    }
}
