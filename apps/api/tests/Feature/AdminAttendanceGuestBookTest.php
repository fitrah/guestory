<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAttendanceGuestBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_attendance_with_summary_and_filters(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->getJson("/api/admin/events/{$event->id}/attendance", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('summary.total_guests', 5)
            ->assertJsonPath('summary.checked_in', 1)
            ->assertJsonPath('attendance.0.name', 'Andi Wijaya');

        $this->getJson("/api/admin/events/{$event->id}/attendance?attendance_status=CHECKED_IN", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'attendance')
            ->assertJsonPath('attendance.0.name', 'Sinta Dewi')
            ->assertJsonPath('attendance.0.method', 'QR');

        $this->getJson("/api/admin/events/{$event->id}/attendance?rsvp_status=ATTENDING&category=Family", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'attendance')
            ->assertJsonPath('attendance.0.name', 'Budi Santoso');
    }

    public function test_admin_can_sort_attendance_by_check_in_time(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $guest = Guest::where('guest_code', 'GUEST-003')->firstOrFail();
        CheckIn::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'receiver_id' => User::where('email', 'receiver@guestory.local')->value('id'),
            'method' => 'MANUAL',
            'actual_guest_count' => 1,
            'checked_in_at' => Carbon::parse('2026-10-24 19:15:00', 'Asia/Jakarta'),
        ]);
        $guest->forceFill(['attendance_status' => 'CHECKED_IN'])->save();

        $this->getJson("/api/admin/events/{$event->id}/attendance?attendance_status=CHECKED_IN&sort=check_in_time&direction=desc", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonPath('attendance.0.name', 'Andi Wijaya')
            ->assertJsonPath('attendance.0.method', 'MANUAL');
    }

    public function test_admin_can_export_attendance_csv(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $response = $this->get("/api/admin/events/{$event->id}/attendance/export", $this->adminAuthHeaders())
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('guest_code,name,category,rsvp_status,attendance_status', $response->getContent());
        $this->assertStringContainsString('GUEST-002', $response->getContent());
    }

    public function test_guest_book_is_derived_from_check_ins(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $this->getJson("/api/admin/events/{$event->id}/guest-book", $this->adminAuthHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'guest_book')
            ->assertJsonPath('guest_book.0.guest_name', 'Sinta Dewi')
            ->assertJsonPath('guest_book.0.method', 'QR')
            ->assertJsonPath('guest_book.0.receiver_name', 'Gate A Receiver');
    }

    public function test_guest_book_export_is_csv(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();

        $response = $this->get("/api/admin/events/{$event->id}/guest-book/export", $this->adminAuthHeaders())
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('guest_code,guest_name,category,guest_count,actual_guest_count,method', $response->getContent());
        $this->assertStringContainsString('Sinta Dewi', $response->getContent());
    }

    public function test_other_admin_cannot_access_attendance_or_guest_book(): void
    {
        $this->seed();

        $event = Event::where('name', 'Andi & Sinta Wedding')->firstOrFail();
        $otherAdmin = User::create([
            'name' => 'Other Attendance Admin',
            'email' => 'attendance-other-fitrahajah@gmail.com',
            'password' => 'password',
            'role' => 'EVENT_OWNER',
            'status' => 'ACTIVE',
        ]);
        [$token] = $otherAdmin->createAccessToken('test');
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson("/api/admin/events/{$event->id}/attendance", $headers)
            ->assertForbidden()
            ->assertJsonPath('code', 'EVENT_FORBIDDEN');

        $this->getJson("/api/admin/events/{$event->id}/guest-book", $headers)
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
