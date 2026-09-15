<?php

namespace Database\Seeders;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\EventReceiver;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Photo;
use App\Models\QRToken;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'fitrahajah@gmail.com'],
            [
                'name' => 'Fitrah Hidayat',
                'password' => Hash::make('password'),
                'role' => 'SUPERADMIN',
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
                'activated_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'fahrurrozirahmawan@gmail.com'],
            [
                'name' => 'Fahrur Rozi Rahmawan',
                'password' => Hash::make('password'),
                'role' => 'SUPERADMIN',
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
                'activated_at' => now(),
            ],
        );

        $receiver = User::updateOrCreate(
            ['email' => 'receiver@guestory.local'],
            [
                'name' => 'Gate A Receiver',
                'password' => Hash::make('password'),
                'role' => 'RECEIVER',
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
                'activated_at' => now(),
            ],
        );

        $event = Event::updateOrCreate(
            ['owner_id' => $admin->id, 'name' => 'Andi & Sinta Wedding'],
            [
                'type' => 'Wedding',
                'description' => 'A connected Guestory demo event for digital invitation, QR check-in, guest book, and photos.',
                'date' => '2026-10-24',
                'start_time' => '18:30',
                'end_time' => '22:00',
                'timezone' => 'Asia/Jakarta',
                'venue_name' => 'The Garden Hall',
                'venue_address' => 'Jl. Merdeka No. 10, Jakarta',
                'map_url' => 'https://maps.example.com/the-garden-hall',
                'status' => 'Published',
                'published_at' => Carbon::parse('2026-09-11 11:00:00', 'Asia/Jakarta'),
            ],
        );

        EventReceiver::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $receiver->id],
            ['status' => 'ACTIVE', 'created_at' => now()],
        );

        CheckIn::where('event_id', $event->id)->delete();
        Photo::where('event_id', $event->id)->delete();
        QRToken::where('event_id', $event->id)->delete();
        Invitation::where('event_id', $event->id)->delete();
        Guest::where('event_id', $event->id)->delete();

        $guestRows = [
            ['guest_code' => 'GUEST-001', 'name' => 'Budi Santoso', 'phone' => '628121110001', 'category' => 'Family', 'guest_count' => 2, 'rsvp_status' => 'ATTENDING', 'attendance_status' => 'NOT_CHECKED_IN', 'invitation_token' => 'invite-demo-budi', 'qr_token' => 'demo-qr-budi', 'qr_status' => 'ACTIVE'],
            ['guest_code' => 'GUEST-002', 'name' => 'Sinta Dewi', 'phone' => '628121110002', 'category' => 'VIP', 'guest_count' => 1, 'rsvp_status' => 'ATTENDING', 'attendance_status' => 'CHECKED_IN', 'invitation_token' => 'invite-demo-sinta', 'qr_token' => 'demo-qr-used', 'qr_status' => 'ACTIVE'],
            ['guest_code' => 'GUEST-003', 'name' => 'Andi Wijaya', 'phone' => '628121110003', 'category' => 'Friend', 'guest_count' => 1, 'rsvp_status' => 'PENDING', 'attendance_status' => 'NOT_CHECKED_IN', 'invitation_token' => 'invite-demo-andi', 'qr_token' => 'demo-qr-andi', 'qr_status' => 'ACTIVE'],
            ['guest_code' => 'GUEST-004', 'name' => 'Maya Putri', 'phone' => '628121110004', 'category' => 'Colleague', 'guest_count' => 1, 'rsvp_status' => 'DECLINED', 'attendance_status' => 'NOT_CHECKED_IN', 'invitation_token' => 'invite-demo-maya', 'qr_token' => 'demo-qr-revoked', 'qr_status' => 'REVOKED'],
        ];

        foreach ($guestRows as $row) {
            $guest = Guest::updateOrCreate(
                ['event_id' => $event->id, 'guest_code' => $row['guest_code']],
                [
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'category' => $row['category'],
                    'guest_count' => $row['guest_count'],
                    'rsvp_status' => $row['rsvp_status'],
                    'invitation_status' => 'SENT',
                    'attendance_status' => $row['attendance_status'],
                ],
            );

            Invitation::updateOrCreate(
                ['event_id' => $event->id, 'guest_id' => $guest->id],
                [
                    'token' => $row['invitation_token'],
                    'status' => 'PUBLISHED',
                    'published_at' => $event->published_at,
                ],
            );

            QRToken::updateOrCreate(
                ['token' => $row['qr_token']],
                [
                    'event_id' => $event->id,
                    'guest_id' => $guest->id,
                    'status' => $row['qr_status'],
                    'revoked_at' => $row['qr_status'] === 'REVOKED' ? now() : null,
                ],
            );

            if ($guest->guest_code === 'GUEST-002') {
                CheckIn::updateOrCreate(
                    ['event_id' => $event->id, 'guest_id' => $guest->id],
                    [
                        'qr_token_id' => $guest->qrTokens()->where('token', $row['qr_token'])->value('id'),
                        'receiver_id' => $receiver->id,
                        'method' => 'QR',
                        'actual_guest_count' => 1,
                        'checked_in_at' => Carbon::parse('2026-10-24 18:42:00', 'Asia/Jakarta'),
                    ],
                );
            }

            if ($guest->guest_code === 'GUEST-001') {
                foreach (range(1, 3) as $index) {
                    $filePath = "events/{$event->id}/photos/budi-{$index}.jpg";
                    Storage::disk('public')->put($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

                    Photo::updateOrCreate(
                        ['event_id' => $event->id, 'guest_id' => $guest->id, 'file_path' => $filePath],
                        [
                            'file_url' => Storage::disk('public')->url($filePath),
                            'status' => 'APPROVED',
                            'uploaded_at' => now()->subMinutes(12 - $index),
                        ],
                    );
                }
            }
        }
    }
}
