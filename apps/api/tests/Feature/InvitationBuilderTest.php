<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_read_defaults_and_persist_event_invitation_config(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->getJson("/api/admin/events/{$event->id}/invitation-config", $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('invitation_config.theme', 'classic')
            ->assertJsonPath('invitation_config.is_default', true)
            ->assertJsonCount(6, 'invitation_config.sections')
            ->assertJsonPath('invitation_config.sections.2.id', 'slideshow');

        $payload = $this->validPayload();
        $this->putJson("/api/admin/events/{$event->id}/invitation-config", $payload, $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('invitation_config.theme', 'garden')
            ->assertJsonPath('invitation_config.sections.1.enabled', false)
            ->assertJsonPath('invitation_config.content.headline', 'A celebration made for us')
            ->assertJsonPath('invitation_config.is_default', false);

        $this->assertDatabaseHas('invitation_configs', ['event_id' => $event->id, 'theme' => 'garden']);
        $this->getJson('/api/invite/invite-demo-budi')
            ->assertOk()
            ->assertJsonPath('invitation_config.theme', 'garden')
            ->assertJsonPath('invitation_config.content.headline', 'A celebration made for us');
    }

    public function test_other_owner_cannot_read_or_update_invitation_config(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $other = User::create(['name' => 'Other', 'email' => 'other-builder@example.test', 'password' => 'password', 'role' => 'EVENT_OWNER', 'status' => 'ACTIVE', 'email_verified_at' => now()]);
        [$token] = $other->createAccessToken('test');
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson("/api/admin/events/{$event->id}/invitation-config", $headers)->assertForbidden()->assertJsonPath('code', 'EVENT_FORBIDDEN');
        $this->putJson("/api/admin/events/{$event->id}/invitation-config", $this->validPayload(), $headers)->assertForbidden()->assertJsonPath('code', 'EVENT_FORBIDDEN');
    }

    public function test_invitation_config_rejects_invalid_theme_sections_and_content(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $payload = $this->validPayload();
        $payload['theme'] = 'script-tag';
        $payload['sections'][4]['id'] = 'hero';
        $payload['content']['headline'] = str_repeat('x', 161);

        $this->putJson("/api/admin/events/{$event->id}/invitation-config", $payload, $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['theme', 'sections.4.id', 'content.headline']);
    }

    public function test_public_invitation_without_saved_config_keeps_compatible_payload_with_defaults(): void
    {
        $this->seed();

        $this->getJson('/api/invite/invite-demo-budi')
            ->assertOk()
            ->assertJsonPath('event.name', 'Andi & Sinta Wedding')
            ->assertJsonPath('guest.name', 'Budi Santoso')
            ->assertJsonPath('photo_feature.can_upload', false)
            ->assertJsonPath('photo_feature.requires_check_in', true)
            ->assertJsonPath('invitation_config.theme', 'classic')
            ->assertJsonPath('invitation_config.is_default', true);
    }

    public function test_old_five_section_config_is_normalized_without_rewriting_it(): void
    {
        $this->seed();
        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $legacySections = collect(['photos', 'hero', 'details', 'qr', 'rsvp'])
            ->map(fn (string $id, int $order) => ['id' => $id, 'enabled' => $id !== 'details', 'order' => $order])
            ->all();
        $event->invitationConfig()->create([
            'theme' => 'midnight',
            'sections' => $legacySections,
            'content' => ['headline' => 'Legacy invitation'],
        ]);

        $this->getJson("/api/admin/events/{$event->id}/invitation-config", $this->ownerHeaders())
            ->assertOk()
            ->assertJsonCount(6, 'invitation_config.sections')
            ->assertJsonPath('invitation_config.sections.0.id', 'photos')
            ->assertJsonPath('invitation_config.sections.2.enabled', false)
            ->assertJsonPath('invitation_config.sections.5.id', 'slideshow')
            ->assertJsonPath('invitation_config.sections.5.enabled', true)
            ->assertJsonPath('invitation_config.content.slideshow_heading', 'Our story');

        $this->assertSame($legacySections, $event->invitationConfig()->firstOrFail()->sections);
    }

    private function ownerHeaders(): array
    {
        $owner = User::where('email', 'fitrahajah@gmail.com')->firstOrFail();
        [$token] = $owner->createAccessToken('test');

        return ['Authorization' => "Bearer {$token}"];
    }

    private function validPayload(): array
    {
        return [
            'theme' => 'garden',
            'sections' => [
                ['id' => 'hero', 'enabled' => true, 'order' => 0],
                ['id' => 'details', 'enabled' => false, 'order' => 1],
                ['id' => 'slideshow', 'enabled' => true, 'order' => 2],
                ['id' => 'qr', 'enabled' => true, 'order' => 3],
                ['id' => 'rsvp', 'enabled' => true, 'order' => 4],
                ['id' => 'photos', 'enabled' => true, 'order' => 5],
            ],
            'content' => [
                'eyebrow' => 'You are invited',
                'headline' => 'A celebration made for us',
                'welcome_message' => 'Come celebrate with us.',
                'details_heading' => 'Save the date',
                'slideshow_heading' => 'Our story',
                'slideshow_message' => 'A few favorite moments.',
                'qr_heading' => 'Personal QR',
                'qr_message' => 'Show this at the venue.',
                'rsvp_heading' => 'RSVP',
                'photos_heading' => 'Shared moments',
                'photos_message' => 'Share your favorite photos.',
                'closing_message' => 'See you there.',
            ],
        ];
    }
}
