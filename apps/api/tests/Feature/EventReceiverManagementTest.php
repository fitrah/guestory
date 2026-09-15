<?php

namespace Tests\Feature;

use App\Models\AccountToken;
use App\Models\Event;
use App\Models\EventReceiver;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventReceiverManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_owner_invites_new_receiver_to_multiple_owned_events_and_receiver_activates_securely(): void
    {
        $owner = $this->user('owner@example.com', 'EVENT_OWNER');
        $first = $this->event($owner, 'First');
        $second = $this->event($owner, 'Second');

        $response = $this->postJson("/api/admin/events/{$first->id}/receivers", [
            'name' => 'Gate Team', 'email' => ' Gate@Example.COM ', 'event_ids' => [$first->id, $second->id],
        ], $this->headers($owner))->assertCreated()->assertJsonPath('code', 'RECEIVER_INVITED');

        $response->assertJsonMissingPath('token');
        $receiver = User::where('email', 'gate@example.com')->firstOrFail();
        $this->assertSame('RECEIVER', $receiver->role);
        $this->assertNull($receiver->password);
        $this->assertSame(2, EventReceiver::where('user_id', $receiver->id)->where('status', 'ACTIVE')->count());

        $plain = AccountToken::issue($receiver, 'ACTIVATE_ACCOUNT', 60);
        $this->postJson('/api/auth/activate', [
            'token' => $plain, 'password' => 'receiver123', 'password_confirmation' => 'receiver123',
        ])->assertOk();
        $this->assertTrue(Hash::check('receiver123', $receiver->refresh()->password));

        $this->getJson('/api/receiver/events', $this->headers($receiver))->assertOk()->assertJsonCount(2, 'events');
    }

    public function test_existing_owner_is_reused_as_receiver_without_role_demotion(): void
    {
        $assigningOwner = $this->user('assigner@example.com', 'EVENT_OWNER');
        $otherOwner = $this->user('also-owner@example.com', 'EVENT_OWNER');
        $event = $this->event($assigningOwner, 'Assigned Event');
        $owned = $this->event($otherOwner, 'Owned Event');

        $this->postJson("/api/admin/events/{$event->id}/receivers", [
            'email' => 'ALSO-OWNER@example.com',
        ], $this->headers($assigningOwner))->assertOk()->assertJsonPath('account_reused', true);

        $this->assertSame('EVENT_OWNER', $otherOwner->refresh()->role);
        $this->getJson('/api/auth/me', $this->headers($otherOwner))
            ->assertOk()->assertJsonPath('user.capabilities.manage_events', true)->assertJsonPath('user.capabilities.receive_events', true);
        $this->getJson("/api/admin/events/{$owned->id}", $this->headers($otherOwner))->assertOk();
        $this->getJson('/api/receiver/events', $this->headers($otherOwner))->assertOk()->assertJsonPath('events.0.id', $event->id);
    }

    public function test_same_receiver_can_be_assigned_across_owners_but_each_owner_manages_only_own_assignments(): void
    {
        $ownerA = $this->user('a@example.com', 'EVENT_OWNER');
        $ownerB = $this->user('b@example.com', 'EVENT_OWNER');
        $receiver = $this->user('shared@example.com', 'RECEIVER');
        $eventA = $this->event($ownerA, 'A');
        $eventB = $this->event($ownerB, 'B');

        $this->postJson("/api/admin/events/{$eventA->id}/receivers", ['email' => $receiver->email], $this->headers($ownerA))->assertOk();
        $this->postJson("/api/admin/events/{$eventB->id}/receivers", ['email' => $receiver->email], $this->headers($ownerB))->assertOk();
        $this->assertSame(2, EventReceiver::where('user_id', $receiver->id)->count());

        $this->getJson("/api/admin/events/{$eventB->id}/receivers", $this->headers($ownerA))->assertForbidden();
        $this->deleteJson("/api/admin/events/{$eventB->id}/receivers/{$receiver->id}", [], $this->headers($ownerA))->assertForbidden();
        $this->deleteJson("/api/admin/events/{$eventA->id}/receivers/{$receiver->id}", [], $this->headers($ownerA))->assertOk();
        $this->assertDatabaseHas('event_receivers', ['event_id' => $eventA->id, 'user_id' => $receiver->id, 'status' => 'REVOKED']);
        $this->assertDatabaseHas('event_receivers', ['event_id' => $eventB->id, 'user_id' => $receiver->id, 'status' => 'ACTIVE']);
    }

    public function test_owner_cannot_batch_assign_receiver_to_another_owners_event(): void
    {
        $ownerA = $this->user('a@example.com', 'EVENT_OWNER');
        $ownerB = $this->user('b@example.com', 'EVENT_OWNER');
        $eventA = $this->event($ownerA, 'A');
        $eventB = $this->event($ownerB, 'B');

        $this->postJson("/api/admin/events/{$eventA->id}/receivers", [
            'name' => 'No Leak', 'email' => 'no-leak@example.com', 'event_ids' => [$eventA->id, $eventB->id],
        ], $this->headers($ownerA))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'no-leak@example.com']);
    }

    public function test_guest_email_can_repeat_across_events_without_user_account(): void
    {
        $owner = $this->user('owner@example.com', 'EVENT_OWNER');
        $first = $this->event($owner, 'First');
        $second = $this->event($owner, 'Second');

        Guest::create(['event_id' => $first->id, 'guest_code' => 'G-1', 'name' => 'Same Guest', 'email' => 'guest@example.com', 'guest_count' => 1]);
        Guest::create(['event_id' => $second->id, 'guest_code' => 'G-1', 'name' => 'Same Guest', 'email' => 'guest@example.com', 'guest_count' => 1]);

        $this->assertSame(2, Guest::where('email', 'guest@example.com')->count());
        $this->assertDatabaseMissing('users', ['email' => 'guest@example.com']);
    }

    private function user(string $email, string $role): User
    {
        return User::factory()->create([
            'email' => $email, 'role' => $role, 'status' => 'ACTIVE', 'email_verified_at' => now(), 'activated_at' => now(),
        ]);
    }

    private function event(User $owner, string $name): Event
    {
        return Event::create(['owner_id' => $owner->id, 'name' => $name, 'type' => 'Wedding', 'date' => '2026-10-01', 'status' => 'Published']);
    }

    private function headers(User $user): array
    {
        [$token] = $user->createAccessToken('test');

        return ['Authorization' => "Bearer {$token}"];
    }
}
