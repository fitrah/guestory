<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuestInvitationExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_update_rsvp_from_public_invitation_token(): void
    {
        $this->seed();

        $this->patchJson('/api/invite/invite-demo-budi/rsvp', [
            'rsvp_status' => 'DECLINED',
        ])
            ->assertOk()
            ->assertJsonPath('code', 'RSVP_UPDATED')
            ->assertJsonPath('guest.rsvp_status', 'DECLINED');

        $this->assertDatabaseHas('guests', [
            'guest_code' => 'GUEST-001',
            'rsvp_status' => 'DECLINED',
        ]);
    }

    public function test_guest_qr_svg_is_available_from_invitation_token(): void
    {
        $this->seed();

        $response = $this->get('/api/invite/invite-demo-budi/qr.svg');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_guest_album_only_returns_approved_photos_for_invitation_event(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        Photo::create([
            'event_id' => $event->id,
            'guest_id' => $event->guests()->where('guest_code', 'GUEST-001')->value('id'),
            'file_path' => 'events/1/photos/pending.jpg',
            'file_url' => '/storage/events/1/photos/pending.jpg',
            'status' => 'PENDING',
        ]);

        $this->getJson('/api/invite/invite-demo-budi/album')
            ->assertOk()
            ->assertJsonPath('event.name', 'Andi & Sinta Wedding')
            ->assertJsonCount(3, 'photos')
            ->assertJsonPath('photos.0.status', 'APPROVED')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 12);
    }

    public function test_guest_album_paginates_in_stable_newest_order_and_stays_event_scoped(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guestId = $event->guests()->where('guest_code', 'GUEST-001')->value('id');
        $sameTime = now()->addHour();
        $older = Photo::create(['event_id' => $event->id, 'guest_id' => $guestId, 'file_path' => 'older.jpg', 'file_url' => '/storage/older.jpg', 'status' => 'APPROVED', 'uploaded_at' => now()]);
        $tieOne = Photo::create(['event_id' => $event->id, 'guest_id' => $guestId, 'file_path' => 'tie-1.jpg', 'file_url' => '/storage/tie-1.jpg', 'status' => 'APPROVED', 'uploaded_at' => $sameTime]);
        $tieTwo = Photo::create(['event_id' => $event->id, 'guest_id' => $guestId, 'file_path' => 'tie-2.jpg', 'file_url' => '/storage/tie-2.jpg', 'status' => 'APPROVED', 'uploaded_at' => $sameTime]);

        $otherEvent = Event::create(['owner_id' => $event->owner_id, 'name' => 'Other Event', 'type' => 'Wedding', 'date' => now()->addMonth(), 'status' => 'Published']);
        $otherGuest = Guest::create(['event_id' => $otherEvent->id, 'guest_code' => 'OTHER-001', 'name' => 'Other Guest', 'guest_count' => 1, 'rsvp_status' => 'PENDING', 'invitation_status' => 'NOT_SENT', 'attendance_status' => 'NOT_CHECKED_IN']);
        Photo::create(['event_id' => $otherEvent->id, 'guest_id' => $otherGuest->id, 'file_path' => 'other.jpg', 'file_url' => '/storage/other.jpg', 'status' => 'APPROVED', 'uploaded_at' => now()->addDay()]);

        $this->getJson('/api/invite/invite-demo-andi/album?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('photos.0.id', $tieTwo->id)
            ->assertJsonPath('photos.1.id', $tieOne->id)
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 2)
            ->assertJsonPath('meta.has_next_page', true)
            ->assertJsonPath('meta.has_previous_page', false)
            ->assertJsonMissing(['id' => $older->id]);

        $this->getJson('/api/invite/invite-demo-andi/album?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(2, 'photos')
            ->assertJsonPath('meta.from', 5)
            ->assertJsonPath('meta.to', 6)
            ->assertJsonPath('meta.has_next_page', false)
            ->assertJsonPath('meta.has_previous_page', true)
            ->assertJsonMissing(['file_url' => '/storage/other.jpg']);
    }

    public function test_guest_album_validates_pagination_boundaries(): void
    {
        $this->seed();

        $this->getJson('/api/invite/invite-demo-andi/album?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson('/api/invite/invite-demo-andi/album?per_page=25')->assertUnprocessable()->assertJsonValidationErrors('per_page');

        $this->getJson('/api/invite/invite-demo-andi/album?page=99&per_page=2')
            ->assertOk()
            ->assertJsonCount(0, 'photos')
            ->assertJsonPath('meta.current_page', 99)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.from', null)
            ->assertJsonPath('meta.to', null);
    }

    public function test_unchecked_guest_cannot_upload_and_leaves_no_file_or_database_artifact(): void
    {
        Storage::fake('public');
        $this->seed();

        $guestId = Guest::where('guest_code', 'GUEST-001')->value('id');
        $beforeCount = Photo::where('guest_id', $guestId)->count();
        $beforeFiles = Storage::disk('public')->allFiles();

        $this->post('/api/invite/invite-demo-budi/photos', [
            'photo' => UploadedFile::fake()->createWithContent(
                'moment.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
            ),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PHOTO_UPLOAD_CHECK_IN_REQUIRED');

        $this->assertSame($beforeCount, Photo::where('guest_id', $guestId)->count());
        $this->assertSame($beforeFiles, Storage::disk('public')->allFiles());
    }

    public function test_checked_in_guest_can_upload_photo_from_invitation_token(): void
    {
        Storage::fake('public');
        $this->seed();

        $guest = Guest::where('guest_code', 'GUEST-002')->firstOrFail();

        $this->post('/api/invite/invite-demo-sinta/photos', [
            'photo' => UploadedFile::fake()->createWithContent(
                'moment.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
            ),
        ])
            ->assertCreated()
            ->assertJsonPath('code', 'PHOTO_UPLOADED')
            ->assertJsonPath('photo.status', 'PENDING')
            ->assertJsonPath('photo.guest_name', 'Sinta Dewi');

        $photo = Photo::where('guest_id', $guest->id)->where('status', 'PENDING')->firstOrFail();
        Storage::disk('public')->assertExists($photo->file_path);

        $this->getJson('/api/invite/invite-demo-sinta/album')
            ->assertOk()
            ->assertJsonCount(3, 'photos');
    }

    public function test_check_in_from_another_event_does_not_authorize_upload(): void
    {
        Storage::fake('public');
        $this->seed();

        $guest = Guest::where('guest_code', 'GUEST-001')->firstOrFail();
        $otherEvent = Event::create(['owner_id' => $guest->event->owner_id, 'name' => 'Other Event', 'type' => 'Wedding', 'date' => now()->addMonth(), 'status' => 'Published']);
        $receiverId = CheckIn::query()->value('receiver_id');
        CheckIn::create(['event_id' => $otherEvent->id, 'guest_id' => $guest->id, 'receiver_id' => $receiverId, 'method' => 'MANUAL', 'actual_guest_count' => 1, 'checked_in_at' => now()]);
        $beforeCount = Photo::where('guest_id', $guest->id)->count();
        $beforeFiles = Storage::disk('public')->allFiles();

        $this->post('/api/invite/invite-demo-budi/photos', [
            'photo' => UploadedFile::fake()->createWithContent('cross-event.jpg', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')),
        ])->assertForbidden()->assertJsonPath('code', 'PHOTO_UPLOAD_CHECK_IN_REQUIRED');

        $this->assertSame($beforeCount, Photo::where('guest_id', $guest->id)->count());
        $this->assertSame($beforeFiles, Storage::disk('public')->allFiles());
    }

    public function test_checked_in_walk_in_guest_can_upload_photo(): void
    {
        Storage::fake('public');
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::create(['event_id' => $event->id, 'walk_in' => true, 'guest_code' => 'WALKIN-PHOTO', 'name' => 'Walk-in Photo', 'guest_count' => 1, 'rsvp_status' => 'ATTENDING', 'invitation_status' => 'OPENED', 'attendance_status' => 'CHECKED_IN']);
        $invitation = Invitation::create(['event_id' => $event->id, 'guest_id' => $guest->id, 'token' => 'walk-in-photo-token', 'status' => 'PUBLISHED', 'published_at' => now()]);
        CheckIn::create(['event_id' => $event->id, 'guest_id' => $guest->id, 'receiver_id' => CheckIn::query()->value('receiver_id'), 'method' => 'WALK_IN', 'actual_guest_count' => 1, 'checked_in_at' => now()]);

        $this->post("/api/invite/{$invitation->token}/photos", [
            'photo' => UploadedFile::fake()->createWithContent('walk-in.jpg', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')),
        ])->assertCreated()->assertJsonPath('photo.guest_name', 'Walk-in Photo');

        $photo = Photo::where('guest_id', $guest->id)->firstOrFail();
        Storage::disk('public')->assertExists($photo->file_path);
    }

    public function test_revoked_invitation_cannot_upload_even_after_check_in(): void
    {
        Storage::fake('public');
        $this->seed();

        $invitation = Invitation::where('token', 'invite-demo-sinta')->firstOrFail();
        $invitation->update(['status' => 'REVOKED']);

        $beforeCount = Photo::where('guest_id', $invitation->guest_id)->count();
        $beforeFiles = Storage::disk('public')->allFiles();
        $this->post('/api/invite/invite-demo-sinta/photos', [
            'photo' => UploadedFile::fake()->createWithContent('revoked.jpg', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')),
        ])->assertForbidden()->assertJsonPath('code', 'PHOTO_UPLOAD_CHECK_IN_REQUIRED');

        $this->assertSame($beforeCount, Photo::where('guest_id', $invitation->guest_id)->count());
        $this->assertSame($beforeFiles, Storage::disk('public')->allFiles());
    }

    public function test_guest_photo_upload_requires_image_file(): void
    {
        Storage::fake('public');
        $this->seed();

        $this->post('/api/invite/invite-demo-sinta/photos', [
            'photo' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
        ])->assertUnprocessable();
    }

    public function test_unpublished_event_invitation_is_not_available(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $event->forceFill(['status' => 'Draft'])->save();

        $this->getJson('/api/invite/invite-demo-budi')
            ->assertConflict()
            ->assertJsonPath('code', 'INVITATION_NOT_AVAILABLE');
    }

    public function test_guest_qr_payload_resolves_to_validation_result(): void
    {
        $this->seed();

        $this->getJson('/api/g/demo-qr-budi')
            ->assertOk()
            ->assertJsonPath('code', 'CHECK_IN_ALLOWED')
            ->assertJsonPath('guest.name', 'Budi Santoso');
    }
}
