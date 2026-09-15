<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalkInGuestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_assigned_receiver_creates_complete_walk_in_atomically_without_whatsapp(): void
    {
        $event = $this->event();
        $response = $this->postJson("/api/receiver/events/{$event->id}/walk-ins", [
            'name' => 'Tamu Dadakan', 'phone' => '081234567890', 'guest_count' => 3,
            'category' => 'Friend', 'group_name' => 'Teman Kantor', 'notes' => 'Datang langsung',
        ], [...$this->headers(), 'Idempotency-Key' => 'gate-001']);

        $response->assertCreated()->assertJsonPath('code', 'WALK_IN_CREATED')
            ->assertJsonPath('guest.walk_in', true)->assertJsonPath('guest.attendance_status', 'CHECKED_IN')
            ->assertJsonPath('check_in.method', 'WALK_IN')->assertJsonPath('check_in.actual_guest_count', 3)
            ->assertJsonPath('invitation.status', 'PUBLISHED')->assertJsonPath('qr.status', 'ACTIVE')
            ->assertJsonPath('whatsapp_sent', false);
        $guest = Guest::where('name', 'Tamu Dadakan')->firstOrFail();
        $this->assertSame('6281234567890', $guest->phone);
        $this->assertSame(48, strlen($guest->invitation->token));
        $this->assertNotNull($guest->checkIn);
        $this->assertSame(0, $guest->whatsAppMessages()->count());
        $this->getJson('/api/invite/'.$guest->invitation->token)->assertOk()->assertJsonPath('guest.name', 'Tamu Dadakan');
        $this->get('/api/invite/'.$guest->invitation->token.'/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->getJson('/api/invite/'.$guest->invitation->token.'/album')->assertOk();
    }

    public function test_walk_in_bypasses_exhausted_quota_but_normal_admin_and_import_do_not(): void
    {
        $event = $this->event();
        $needed = 50 - $event->guests()->where('walk_in', false)->count();
        foreach (range(1, $needed) as $n) {
            $event->guests()->create(['guest_code' => "N{$n}", 'name' => "Normal {$n}"]);
        }

        $this->postJson("/api/admin/events/{$event->id}/guests", ['name' => 'Blocked'], $this->adminHeaders())->assertUnprocessable()->assertJsonPath('code', 'GUEST_LIMIT_EXCEEDED');
        $this->postJson("/api/admin/events/{$event->id}/guests/import", ['guests' => [['name' => 'Blocked Import']]], $this->adminHeaders())->assertUnprocessable()->assertJsonPath('code', 'GUEST_LIMIT_EXCEEDED');
        $this->postJson("/api/receiver/events/{$event->id}/walk-ins", ['name' => 'Allowed Walk-in', 'guest_count' => 2], [...$this->headers(), 'Idempotency-Key' => 'full-quota'])->assertCreated();
        $this->assertSame(50, $event->guests()->where('walk_in', false)->count());
        $this->assertSame(1, $event->guests()->where('walk_in', true)->count());
        $this->getJson("/api/admin/events/{$event->id}/dashboard", $this->adminHeaders())->assertJsonPath('quota.guest_records_used', 50)->assertJsonPath('quota.walk_in_records', 1);
    }

    public function test_idempotency_replays_same_request_and_rejects_changed_payload(): void
    {
        $event = $this->event();
        $headers = [...$this->headers(), 'Idempotency-Key' => 'retry-key'];
        $payload = ['name' => 'Retry Guest', 'guest_count' => 2];
        $first = $this->postJson("/api/receiver/events/{$event->id}/walk-ins", $payload, $headers)->assertCreated();
        $second = $this->postJson("/api/receiver/events/{$event->id}/walk-ins", $payload, $headers)->assertOk()->assertJsonPath('code', 'WALK_IN_REPLAYED');
        $this->assertSame($first->json('guest.id'), $second->json('guest.id'));
        $this->assertSame(1, Guest::where('name', 'Retry Guest')->count());
        $this->postJson("/api/receiver/events/{$event->id}/walk-ins", ['name' => 'Changed', 'guest_count' => 2], $headers)->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_KEY_CONFLICT');
    }

    public function test_endpoint_requires_auth_assignment_published_event_and_idempotency_key(): void
    {
        $event = $this->event();
        $this->postJson("/api/receiver/events/{$event->id}/walk-ins", ['name' => 'No auth', 'guest_count' => 1])->assertUnauthorized();
        $other = User::factory()->create(['role' => 'RECEIVER', 'status' => 'ACTIVE']);
        [$token] = $other->createAccessToken('test');
        $this->postJson("/api/receiver/events/{$event->id}/walk-ins", ['name' => 'No assignment', 'guest_count' => 1], ['Authorization' => "Bearer {$token}", 'Idempotency-Key' => 'x'])->assertForbidden();
        $this->postJson("/api/receiver/events/{$event->id}/walk-ins", ['name' => 'No key', 'guest_count' => 1], $this->headers())->assertUnprocessable()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');
        $event->update(['status' => 'Archived']);
        $this->postJson("/api/receiver/events/{$event->id}/walk-ins", ['name' => 'Archived', 'guest_count' => 1], [...$this->headers(), 'Idempotency-Key' => 'archived'])->assertConflict()->assertJsonPath('code', 'INVALID_EVENT');
    }

    public function test_transaction_rolls_back_if_invitation_creation_fails(): void
    {
        $event = $this->event();
        DB::unprepared("CREATE FUNCTION reject_walk_in_invitation() RETURNS trigger AS $$ BEGIN IF NEW.token <> 'never' THEN RAISE EXCEPTION 'forced invitation failure'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
        DB::unprepared('CREATE TRIGGER reject_walk_in_invitation BEFORE INSERT ON invitations FOR EACH ROW EXECUTE FUNCTION reject_walk_in_invitation()');
        try {
            $this->postJson("/api/receiver/events/{$event->id}/walk-ins", ['name' => 'Rollback Guest', 'guest_count' => 1], [...$this->headers(), 'Idempotency-Key' => 'rollback'])->assertServerError();
            $this->assertDatabaseMissing('guests', ['name' => 'Rollback Guest']);
        } finally {
            DB::unprepared('DROP TRIGGER reject_walk_in_invitation ON invitations');
            DB::unprepared('DROP FUNCTION reject_walk_in_invitation()');
        }
    }

    public function test_search_surfaces_existing_match_and_exports_label_walk_ins(): void
    {
        $event = $this->event();
        $existing = $event->guests()->create(['guest_code' => 'EXIST', 'name' => 'Siti Aminah', 'phone' => '628123456789']);
        $this->getJson("/api/receiver/events/{$event->id}/guests/search?q=Siti", $this->headers())->assertOk()->assertJsonPath('guests.0.id', $existing->id);
        $this->postJson("/api/receiver/events/{$event->id}/walk-ins", ['name' => 'Walk Export', 'guest_count' => 1], [...$this->headers(), 'Idempotency-Key' => 'export'])->assertCreated();
        $this->get("/api/admin/events/{$event->id}/guests/export", $this->adminHeaders())->assertOk()->assertSee('walk_in')->assertSee('Walk Export');
        $this->get("/api/admin/events/{$event->id}/attendance/export", $this->adminHeaders())->assertOk()->assertSee('walk_in')->assertSee('WALK_IN');
    }

    private function event(): Event
    {
        return Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
    }

    private function headers(): array
    {
        [$token] = User::where('email', 'receiver@guestory.local')->firstOrFail()->createAccessToken('walk-in-test');

        return ['Authorization' => "Bearer {$token}"];
    }

    private function adminHeaders(): array
    {
        [$token] = User::where('email', 'fitrahajah@gmail.com')->firstOrFail()->createAccessToken('walk-in-admin');

        return ['Authorization' => "Bearer {$token}"];
    }
}
