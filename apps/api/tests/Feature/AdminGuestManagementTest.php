<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminGuestManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_list_filter_update_export_and_delete_guest(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $createResponse = $this->postJson("/api/admin/events/{$event->id}/guests", [
            'name' => 'Nina Rahman',
            'phone' => '+62812345678',
            'email' => 'nina@example.com',
            'category' => 'Friend',
            'group_name' => 'College',
            'guest_count' => 2,
            'table_number' => 'A3',
            'notes' => 'Vegetarian meal',
        ], $this->adminAuthHeaders())
            ->assertCreated()
            ->assertJsonPath('guest.name', 'Nina Rahman')
            ->assertJsonPath('guest.category', 'Friend')
            ->assertJsonPath('guest.guest_count', 2);

        $guestId = $createResponse->json('guest.id');
        $this->assertNotEmpty($createResponse->json('guest.guest_code'));

        $this->getJson("/api/admin/events/{$event->id}/guests?search=Nina", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('guests.0.id', $guestId);

        $this->getJson("/api/admin/events/{$event->id}/guests?qr_status=REVOKED", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('guests.0.name', 'Maya Putri');

        $this->getJson("/api/admin/events/{$event->id}/guests/{$guestId}", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('guest.name', 'Nina Rahman');

        $this->patchJson("/api/admin/events/{$event->id}/guests/{$guestId}", [
            'rsvp_status' => 'ATTENDING',
            'table_number' => 'VIP-1',
        ], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('guest.rsvp_status', 'ATTENDING')
            ->assertJsonPath('guest.table_number', 'VIP-1');

        $this->get("/api/admin/events/{$event->id}/guests/export", $this->adminAuthHeaders())
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->deleteJson("/api/admin/events/{$event->id}/guests/{$guestId}", [], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'GUEST_DELETED');

        $this->assertDatabaseMissing('guests', ['id' => $guestId]);
    }

    public function test_admin_can_import_guest_rows(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->postJson("/api/admin/events/{$event->id}/guests/import", [
            'guests' => [
                [
                    'name' => 'Rafi Hidayat',
                    'phone' => '081234567801',
                    'category' => 'Family',
                    'guest_count' => 3,
                ],
                [
                    'guest_code' => 'VIP-001',
                    'name' => 'Dewi Lestari',
                    'email' => 'dewi-import@example.com',
                    'category' => 'VIP',
                ],
            ],
        ], $this->adminAuthHeaders())
            ->assertCreated()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('invitations_sent', 0)
            ->assertJsonPath('guests.1.guest_code', 'VIP-001');

        $this->assertDatabaseHas('guests', [
            'event_id' => $event->id,
            'name' => 'Rafi Hidayat',
            'guest_count' => 3,
        ]);
    }

    public function test_contact_import_normalizes_indonesian_numbers_and_creates_no_invitation(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $response = $this->postJson("/api/admin/events/{$event->id}/guests/import", ['guests' => [[
            'name' => 'Kontak Baru', 'phone' => '0812-3456-7890', 'email' => 'KONTAK@EXAMPLE.COM',
        ]]], $this->adminAuthHeaders());

        $response->assertCreated()->assertJsonPath('guests.0.phone', '6281234567890');
        $guestId = $response->json('guests.0.id');
        $this->assertDatabaseHas('guests', ['id' => $guestId, 'phone' => '6281234567890', 'email' => 'kontak@example.com']);
        $this->assertDatabaseMissing('invitations', ['guest_id' => $guestId]);
    }

    public function test_contact_import_rejects_in_file_duplicates_atomically(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $before = $event->guests()->count();

        $this->postJson("/api/admin/events/{$event->id}/guests/import", ['guests' => [
            ['name' => 'Satu', 'phone' => '081234567899'],
            ['name' => 'Dua', 'phone' => '+62 812-3456-7899'],
        ]], $this->adminAuthHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'GUEST_IMPORT_DUPLICATES')
            ->assertJsonCount(1, 'duplicates');

        $this->assertSame($before, $event->guests()->count());
    }

    public function test_guest_management_is_scoped_to_event_owner(): void
    {
        $this->seed();

        $otherAdmin = User::create([
            'name' => 'Other Admin',
            'email' => 'other-guest-fitrahajah@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'EVENT_OWNER',
            'status' => 'ACTIVE',
        ]);

        $otherEvent = Event::create([
            'owner_id' => $otherAdmin->id,
            'name' => 'Other Event',
            'type' => 'Gathering',
            'date' => '2026-12-10',
            'status' => 'Draft',
        ]);

        $otherGuest = Guest::create([
            'event_id' => $otherEvent->id,
            'guest_code' => 'OTHER-001',
            'name' => 'Other Guest',
        ]);

        $this->getJson("/api/admin/events/{$otherEvent->id}/guests", $this->adminAuthHeaders())
            ->assertForbidden()
            ->assertJsonPath('code', 'EVENT_FORBIDDEN');

        $ownedEvent = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->getJson("/api/admin/events/{$ownedEvent->id}/guests/{$otherGuest->id}", $this->adminAuthHeaders())
            ->assertNotFound()
            ->assertJsonPath('code', 'GUEST_NOT_FOUND');
    }

    public function test_guest_validation_rejects_bad_status_and_duplicate_guest_code(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->postJson("/api/admin/events/{$event->id}/guests", [
            'guest_code' => 'GUEST-001',
            'name' => 'Duplicate Code',
            'rsvp_status' => 'YES',
        ], $this->adminAuthHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['guest_code', 'rsvp_status']);
    }

    private function adminAuthHeaders(): array
    {
        $user = User::where('email', 'fitrahajah@gmail.com')->firstOrFail();
        [$token] = $user->createAccessToken('test');

        return ['Authorization' => "Bearer {$token}"];
    }
}
