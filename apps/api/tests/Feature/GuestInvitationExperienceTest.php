<?php

namespace Tests\Feature;

use App\Models\Event;
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
            ->assertJsonPath('photos.0.status', 'APPROVED');
    }

    public function test_guest_can_upload_photo_from_invitation_token(): void
    {
        Storage::fake('public');
        $this->seed();

        $this->post('/api/invite/invite-demo-budi/photos', [
            'photo' => UploadedFile::fake()->createWithContent(
                'moment.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
            ),
        ])
            ->assertCreated()
            ->assertJsonPath('code', 'PHOTO_UPLOADED')
            ->assertJsonPath('photo.status', 'PENDING')
            ->assertJsonPath('photo.guest_name', 'Budi Santoso');

        $photo = Photo::where('status', 'PENDING')->firstOrFail();
        Storage::disk('public')->assertExists($photo->file_path);

        $this->getJson('/api/invite/invite-demo-budi/album')
            ->assertOk()
            ->assertJsonCount(3, 'photos');
    }

    public function test_guest_photo_upload_requires_image_file(): void
    {
        Storage::fake('public');
        $this->seed();

        $this->post('/api/invite/invite-demo-budi/photos', [
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
