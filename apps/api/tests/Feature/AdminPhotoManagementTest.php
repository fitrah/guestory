<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPhotoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_photos_with_summary_and_filters(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = $event->guests()->where('guest_code', 'GUEST-001')->firstOrFail();
        Photo::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'file_path' => "events/{$event->id}/photos/pending.jpg",
            'file_url' => "/storage/events/{$event->id}/photos/pending.jpg",
            'status' => 'PENDING',
            'uploaded_at' => now(),
        ]);

        $this->getJson("/api/admin/events/{$event->id}/photos", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.pending', 1)
            ->assertJsonPath('summary.approved', 3)
            ->assertJsonCount(4, 'photos');

        $this->getJson("/api/admin/events/{$event->id}/photos?status=PENDING", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'photos')
            ->assertJsonPath('photos.0.status', 'PENDING')
            ->assertJsonPath('photos.0.guest.name', 'Budi Santoso');
    }

    public function test_admin_can_approve_reject_and_delete_photo(): void
    {
        Storage::fake('public');
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = $event->guests()->where('guest_code', 'GUEST-001')->firstOrFail();
        Storage::disk('public')->put("events/{$event->id}/photos/to-manage.jpg", 'fake-image');
        $photo = Photo::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'file_path' => "events/{$event->id}/photos/to-manage.jpg",
            'file_url' => "/storage/events/{$event->id}/photos/to-manage.jpg",
            'status' => 'PENDING',
            'uploaded_at' => now(),
        ]);

        $this->postJson("/api/admin/events/{$event->id}/photos/{$photo->id}/approve", [], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'PHOTO_APPROVED')
            ->assertJsonPath('photo.status', 'APPROVED');

        $this->postJson("/api/admin/events/{$event->id}/photos/{$photo->id}/reject", [], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'PHOTO_REJECTED')
            ->assertJsonPath('photo.status', 'REJECTED');

        $this->deleteJson("/api/admin/events/{$event->id}/photos/{$photo->id}", [], $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('code', 'PHOTO_DELETED');

        Storage::disk('public')->assertMissing("events/{$event->id}/photos/to-manage.jpg");
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
    }

    public function test_other_admin_cannot_manage_event_photos(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $photo = $event->photos()->firstOrFail();
        $otherAdmin = User::create([
            'name' => 'Other Photo Admin',
            'email' => 'photo-other-fitrahajah@gmail.com',
            'password' => 'password',
            'role' => 'EVENT_OWNER',
            'status' => 'ACTIVE',
        ]);
        [$token] = $otherAdmin->createAccessToken('test');
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson("/api/admin/events/{$event->id}/photos", $headers)
            ->assertForbidden()
            ->assertJsonPath('code', 'EVENT_FORBIDDEN');

        $this->postJson("/api/admin/events/{$event->id}/photos/{$photo->id}/approve", [], $headers)
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
