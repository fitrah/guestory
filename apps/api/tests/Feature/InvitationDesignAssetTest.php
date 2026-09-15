<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\InvitationDesignAsset;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvitationDesignAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_list_reorder_choose_cover_and_delete_design_assets(): void
    {
        Storage::fake('public');
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $response = $this->post("/api/admin/events/{$event->id}/invitation-design-assets", [
            'files' => [$this->fakeJpeg('one.jpg'), $this->fakePng('two.png')],
        ], $this->ownerHeaders())->assertCreated()->assertJsonCount(2, 'assets')->assertJsonPath('assets.0.is_cover', true);
        $ids = $response->json('assets.*.id');

        $this->putJson("/api/admin/events/{$event->id}/invitation-design-assets/order", ['asset_ids' => array_reverse($ids)], $this->ownerHeaders())
            ->assertOk()->assertJsonPath('assets.1.id', $ids[0]);
        $this->putJson("/api/admin/events/{$event->id}/invitation-design-assets/{$ids[1]}/cover", [], $this->ownerHeaders())
            ->assertOk()->assertJsonPath('assets.0.id', $ids[1])->assertJsonPath('assets.0.is_cover', true);

        $path = InvitationDesignAsset::findOrFail($ids[1])->file_path;
        $this->deleteJson("/api/admin/events/{$event->id}/invitation-design-assets/{$ids[1]}", [], $this->ownerHeaders())
            ->assertOk()->assertJsonCount(1, 'assets')->assertJsonPath('assets.0.is_cover', true);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_design_assets_are_owner_scoped_and_cross_event_asset_ids_are_hidden(): void
    {
        Storage::fake('public');
        $this->seed();
        $event = Event::firstOrFail();
        $other = User::create(['name' => 'Other', 'email' => 'other-assets@example.test', 'password' => 'password', 'role' => 'EVENT_OWNER', 'status' => 'ACTIVE', 'email_verified_at' => now()]);
        $otherEvent = Event::create(['owner_id' => $other->id, 'name' => 'Other event', 'date' => now()->addDay(), 'status' => 'Draft']);
        $asset = InvitationDesignAsset::create(['event_id' => $otherEvent->id, 'file_path' => 'other/a.jpg', 'file_url' => '/storage/other/a.jpg', 'original_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 100, 'position' => 0, 'is_cover' => true]);
        [$token] = $other->createAccessToken('test');

        $this->getJson("/api/admin/events/{$event->id}/invitation-design-assets", ['Authorization' => "Bearer {$token}"])->assertForbidden();
        $this->deleteJson("/api/admin/events/{$event->id}/invitation-design-assets/{$asset->id}", [], $this->ownerHeaders())->assertNotFound();
        $this->assertDatabaseHas('invitation_design_assets', ['id' => $asset->id]);
    }

    public function test_upload_validation_enforces_types_size_and_safe_count(): void
    {
        Storage::fake('public');
        $this->seed();
        $event = Event::firstOrFail();
        $this->post("/api/admin/events/{$event->id}/invitation-design-assets", ['files' => [UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf')]], $this->ownerHeaders())
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->post("/api/admin/events/{$event->id}/invitation-design-assets", ['files' => [UploadedFile::fake()->createWithContent('large.jpg', str_repeat('x', 5121 * 1024))]], $this->ownerHeaders())
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        foreach (range(0, 11) as $position) {
            InvitationDesignAsset::create(['event_id' => $event->id, 'file_path' => "assets/{$position}.jpg", 'file_url' => "/storage/assets/{$position}.jpg", 'original_name' => "{$position}.jpg", 'mime_type' => 'image/jpeg', 'size_bytes' => 10, 'position' => $position, 'is_cover' => $position === 0]);
        }
        $this->post("/api/admin/events/{$event->id}/invitation-design-assets", ['files' => [$this->fakeJpeg('overflow.jpg')]], $this->ownerHeaders())
            ->assertUnprocessable()->assertJsonPath('code', 'DESIGN_ASSET_LIMIT_EXCEEDED');
    }

    public function test_public_payload_exposes_only_safe_design_asset_fields_and_never_album_photos(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $asset = InvitationDesignAsset::create(['event_id' => $event->id, 'file_path' => 'private/design.jpg', 'file_url' => '/storage/events/1/invitation-design/design.jpg', 'original_name' => 'secret-name.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 123, 'position' => 0, 'is_cover' => true]);
        $photo = Photo::where('event_id', $event->id)->firstOrFail();

        $payload = $this->getJson('/api/invite/invite-demo-budi')->assertOk()->assertJsonCount(1, 'slideshow_assets')
            ->assertJsonPath('slideshow_assets.0.id', $asset->id)->assertJsonPath('slideshow_assets.0.url', $asset->file_url)
            ->assertJsonMissingPath('slideshow_assets.0.file_path')->assertJsonMissingPath('slideshow_assets.0.original_name')->json();
        $this->assertStringNotContainsString((string) $photo->file_url, json_encode($payload['slideshow_assets']));
    }

    public function test_existing_event_without_assets_returns_empty_slideshow_assets(): void
    {
        $this->seed();
        $this->getJson('/api/invite/invite-demo-budi')->assertOk()->assertExactJsonStructure(['token', 'event', 'guest', 'qr', 'qr_svg_url', 'photo_feature', 'invitation_config', 'slideshow_assets'])->assertJsonCount(0, 'slideshow_assets');
    }

    private function fakeJpeg(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9k=', true));
    }

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true));
    }

    private function ownerHeaders(): array
    {
        $owner = User::where('email', 'fitrahajah@gmail.com')->firstOrFail();
        [$token] = $owner->createAccessToken('test');

        return ['Authorization' => "Bearer {$token}"];
    }
}
