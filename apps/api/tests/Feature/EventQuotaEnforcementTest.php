<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBilling;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventQuotaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    public function test_free_guest_quota_counts_records_not_headcount_and_allows_50th_only(): void
    {
        $event = $this->event();
        $this->guests($event, 49);

        $this->postJson("/api/admin/events/{$event->id}/guests", ['name' => 'Fiftieth', 'guest_count' => 20], $this->headers())->assertCreated();
        $this->postJson("/api/admin/events/{$event->id}/guests", ['name' => 'Fifty First'], $this->headers())
            ->assertUnprocessable()->assertJsonPath('code', 'GUEST_LIMIT_EXCEEDED')
            ->assertJsonPath('current', 50)->assertJsonPath('limit', 50)->assertJsonPath('requested', 1)
            ->assertJsonPath('semantics', 'Guest quota counts guest records, not guest_count headcount.');
        $this->assertSame(50, $event->guests()->count());
    }

    public function test_dashboard_exposes_effective_guest_record_allowance(): void
    {
        $event = $this->event();
        $this->guests($event, 49);

        $this->getJson("/api/admin/events/{$event->id}/dashboard", $this->headers())
            ->assertOk()
            ->assertJsonPath('quota.plan_code', 'FREE')
            ->assertJsonPath('quota.guest_limit', 50)
            ->assertJsonPath('quota.guest_records_used', 49)
            ->assertJsonPath('quota.guest_records_remaining', 1)
            ->assertJsonPath('quota.walk_in_records', 0)
            ->assertJsonPath('quota.semantics', 'Guest quota counts normal guest records only; walk-ins are tracked separately and can only bypass quota through the receiver walk-in endpoint.');
    }

    public function test_import_overflow_is_rejected_all_or_nothing(): void
    {
        $event = $this->event();
        $this->guests($event, 49);

        $this->postJson("/api/admin/events/{$event->id}/guests/import", ['guests' => [['name' => 'One'], ['name' => 'Two']]], $this->headers())
            ->assertUnprocessable()->assertJsonPath('code', 'GUEST_LIMIT_EXCEEDED')
            ->assertJsonPath('message', 'Quota guest records event terlampaui.')
            ->assertJsonPath('current', 49)->assertJsonPath('limit', 50)->assertJsonPath('requested', 2)
            ->assertJsonPath('semantics', 'Guest quota counts guest records, not guest_count headcount.');
        $this->assertSame(49, $event->guests()->count());
    }

    public function test_free_photo_quota_allows_100th_and_rejects_101st_without_file(): void
    {
        [$event, $invitation] = $this->invitation();
        $this->photos($event, $invitation->guest, 99);

        $this->post("/api/invite/{$invitation->token}/photos", ['photo' => $this->fakeJpeg('100.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $before = Storage::disk('public')->allFiles();
        $this->post("/api/invite/{$invitation->token}/photos", ['photo' => $this->fakeJpeg('101.jpg')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('code', 'PHOTO_LIMIT_EXCEEDED')->assertJsonPath('current', 100)->assertJsonPath('limit', 100);
        $this->assertSame(100, $event->photos()->count());
        $this->assertSame($before, Storage::disk('public')->allFiles());
    }

    public function test_paid_basic_grants_larger_limit_but_pending_falls_back_to_free(): void
    {
        $paid = $this->event('Paid');
        EventBilling::create(['event_id' => $paid->id, 'plan_code' => 'BASIC', 'status' => 'PAID', 'order_id' => 'GUESTORY-PAID-QUOTA', 'amount' => 99000, 'currency' => 'IDR', 'entitlements' => config('billing.plans.BASIC')]);
        $this->guests($paid, 50);
        $this->postJson("/api/admin/events/{$paid->id}/guests", ['name' => 'Basic 51'], $this->headers())->assertCreated();

        $pending = $this->event('Pending');
        EventBilling::create(['event_id' => $pending->id, 'plan_code' => 'PREMIUM', 'status' => 'PENDING', 'order_id' => 'GUESTORY-PENDING-QUOTA', 'amount' => 249000, 'currency' => 'IDR']);
        $this->guests($pending, 50);
        $this->postJson("/api/admin/events/{$pending->id}/guests", ['name' => 'Pending 51'], $this->headers())
            ->assertUnprocessable()->assertJsonPath('limit', 50);
    }

    private function event(string $name = 'Quota Event'): Event
    {
        return Event::create(['owner_id' => User::where('email', 'fitrahajah@gmail.com')->firstOrFail()->id, 'name' => $name, 'type' => 'Wedding', 'date' => '2026-12-01', 'status' => 'Published', 'published_at' => now()]);
    }

    private function guests(Event $event, int $count): void
    {
        foreach (range(1, $count) as $number) {
            $event->guests()->create(['guest_code' => sprintf('Q%04d', $number), 'name' => "Guest {$number}", 'guest_count' => 20]);
        }
    }

    private function invitation(): array
    {
        $event = $this->event();
        $guest = $event->guests()->create(['guest_code' => 'PHOTO1', 'name' => 'Uploader']);
        $invitation = Invitation::create(['event_id' => $event->id, 'guest_id' => $guest->id, 'token' => 'quota-photo-token', 'status' => 'PUBLISHED', 'published_at' => now()]);

        return [$event, $invitation];
    }

    private function photos(Event $event, Guest $guest, int $count): void
    {
        foreach (range(1, $count) as $number) {
            Photo::create(['event_id' => $event->id, 'guest_id' => $guest->id, 'file_path' => "existing/{$number}.jpg", 'file_url' => "/existing/{$number}.jpg", 'status' => 'APPROVED', 'uploaded_at' => now()]);
        }
    }

    private function fakeJpeg(string $name): UploadedFile
    {
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9k=', true);

        return UploadedFile::fake()->createWithContent($name, $jpeg);
    }

    private function headers(): array
    {
        [$token] = User::where('email', 'fitrahajah@gmail.com')->firstOrFail()->createAccessToken('quota-test');

        return ['Authorization' => "Bearer {$token}"];
    }
}
