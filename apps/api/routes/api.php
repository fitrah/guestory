<?php

use App\Models\AccountToken;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\EventReceiver;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\InvitationConfig;
use App\Models\InvitationDesignAsset;
use App\Models\Photo;
use App\Models\QRToken;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\GuestoryBilling;
use App\Services\WapiClient;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

Route::get('/brand', function () {
    return [
        'name' => 'Guestory',
        'tagline' => 'Every Guest Has a Story',
        'positioning' => 'Digital invitation, QR check-in, guest book, and event photo album in one connected guest experience.',
        'language' => 'id',
    ];
});

Route::get('/mvp-blueprint', function () {
    return [
        'roles' => [
            'admin' => ['Dashboard', 'Events', 'Guests', 'Invitations', 'QR Codes', 'Check-in', 'Guest Book', 'Photos', 'Receivers', 'Settings'],
            'receiver' => ['Scanner', 'Recent Check-ins', 'Search Guest', 'Profile'],
            'guest' => ['Invitation', 'RSVP', 'Personal QR', 'Photo Upload', 'Album'],
        ],
        'entities' => ['users', 'events', 'guests', 'invitations', 'qr_tokens', 'check_ins', 'photos', 'event_receivers'],
        'rules' => [
            'QR token is a guest digital identity connected to one event and one guest.',
            'Guest does not need an account.',
            'Receiver can only check in guests for assigned events.',
            'One guest can only have one successful check-in in MVP.',
            'Invitation token and QR token are separated.',
            'Photos are scoped by event and guest.',
        ],
    ];
});

Route::get('/health', function () {
    DB::select('select 1');

    return [
        'status' => 'ok',
        'service' => 'guestory-api',
        'time' => now()->toISOString(),
        'checks' => [
            'database' => 'ok',
            'storage_public_path' => Storage::disk('public')->path(''),
        ],
    ];
})->middleware('throttle:60,1');

Route::post('/auth/register', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:160'],
        'email' => ['required', 'email', 'max:255'],
        'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        'terms_accepted' => ['accepted'],
        'privacy_accepted' => ['accepted'],
        'website' => ['nullable', 'max:0'],
    ]);

    $email = Str::lower(trim($data['email']));
    if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
        return response()->json(registrationResponse(), 202);
    }

    $user = User::create([
        'name' => trim($data['name']), 'email' => $email, 'password' => $data['password'],
        'role' => 'EVENT_OWNER', 'status' => 'PENDING_VERIFICATION',
        'terms_version' => config('app.terms_version', '2026-09-14'), 'terms_accepted_at' => now(),
        'privacy_version' => config('app.privacy_version', '2026-09-14'), 'privacy_accepted_at' => now(),
    ]);
    sendAccountLink($user, 'VERIFY_EMAIL');

    return response()->json(registrationResponse(), 202);
})->middleware('throttle:5,1');

Route::post('/auth/email/verify', function (Request $request) {
    $data = $request->validate(['token' => ['required', 'string']]);
    $accountToken = AccountToken::valid($data['token'], 'VERIFY_EMAIL');
    if (! $accountToken) {
        return response()->json(['code' => 'INVALID_VERIFICATION_TOKEN', 'message' => 'Link verifikasi tidak valid atau sudah kedaluwarsa.'], 422);
    }
    DB::transaction(function () use ($accountToken) {
        $accountToken->user->forceFill(['email_verified_at' => now(), 'activated_at' => now(), 'status' => 'ACTIVE'])->save();
        $accountToken->update(['used_at' => now()]);
    });

    return ['code' => 'EMAIL_VERIFIED', 'message' => 'Email berhasil diverifikasi. Silakan login.'];
})->middleware('throttle:10,1');

Route::post('/auth/email/resend', function (Request $request) {
    $data = $request->validate(['email' => ['required', 'email']]);
    $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower(trim($data['email']))])->where('status', 'PENDING_VERIFICATION')->first();
    if ($user) {
        sendAccountLink($user, 'VERIFY_EMAIL');
    }

    return registrationResponse();
})->middleware('throttle:3,1');

Route::post('/auth/activate', function (Request $request) {
    $data = $request->validate([
        'token' => ['required', 'string'],
        'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
    ]);
    $accountToken = AccountToken::valid($data['token'], 'ACTIVATE_ACCOUNT');
    if (! $accountToken) {
        return response()->json(['code' => 'INVALID_ACTIVATION_TOKEN', 'message' => 'Link aktivasi tidak valid atau sudah kedaluwarsa.'], 422);
    }
    DB::transaction(function () use ($accountToken, $data) {
        $accountToken->user->forceFill([
            'password' => Hash::make($data['password']), 'email_verified_at' => now(), 'activated_at' => now(), 'status' => 'ACTIVE',
        ])->save();
        $accountToken->update(['used_at' => now()]);
        $accountToken->user->accessTokens()->delete();
    });

    return ['code' => 'ACCOUNT_ACTIVATED', 'message' => 'Akun aktif. Silakan login.'];
})->middleware('throttle:10,1');

Route::post('/auth/login', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::query()->where('email', $data['email'])->first();

    if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password) || $user->status !== 'ACTIVE' || ! $user->email_verified_at) {
        return response()->json([
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'Email atau password tidak valid.',
        ], 422);
    }

    [$plainToken] = $user->createAccessToken('web');

    return [
        'token_type' => 'Bearer',
        'access_token' => $plainToken,
        'user' => userPayload($user),
    ];
})->middleware('throttle:10,1');

Route::post('/auth/forgot-password', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'email'],
    ]);

    $user = User::query()
        ->where('email', $data['email'])
        ->where('status', 'ACTIVE')
        ->first();

    if ($user) {
        $token = Password::broker()->createToken($user);
        $resetUrl = resetPasswordUrl($user->email, $token);
        $plainText = "Halo {$user->name},\n\nKlik link berikut untuk membuat password baru Guestory:\n\n{$resetUrl}\n\nLink ini berlaku 60 menit. Abaikan email ini kalau kamu tidak meminta reset password.";

        try {
            Mail::raw($plainText, function ($message) use ($user) {
                $message
                    ->to($user->email)
                    ->subject('Reset password Guestory');
            });
        } catch (Throwable $error) {
            Log::warning('Guestory password reset email failed.', [
                'user_id' => $user->id,
                'error' => $error->getMessage(),
            ]);
        }
    }

    return [
        'code' => 'PASSWORD_RESET_LINK_SENT',
        'message' => 'Jika email terdaftar, link reset password akan dikirim.',
    ];
})->middleware('throttle:5,1');

Route::post('/auth/reset-password', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'email'],
        'token' => ['required', 'string'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    $status = Password::broker()->reset($data, function (User $user, string $password) {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();

        $user->accessTokens()->delete();
    });

    if ($status !== Password::PASSWORD_RESET) {
        return response()->json([
            'code' => 'INVALID_RESET_TOKEN',
            'message' => 'Token reset password tidak valid atau sudah kedaluwarsa.',
        ], 422);
    }

    return [
        'code' => 'PASSWORD_RESET',
        'message' => 'Password berhasil diperbarui. Silakan login.',
    ];
})->middleware('throttle:10,1');

Route::middleware('guestory.auth')->group(function () {
    Route::get('/auth/me', function (Request $request) {
        return ['user' => userPayload($request->user())];
    });

    Route::patch('/auth/me', function (Request $request) {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'whatsapp_number' => ['nullable', 'string', 'regex:/^62[1-9][0-9]{7,13}$/'],
        ]);
        $request->user()->update($data);

        return ['user' => userPayload($request->user()->refresh()), 'code' => 'PROFILE_UPDATED'];
    });

    Route::post('/auth/logout', function (Request $request) {
        $request->attributes->get('access_token')?->delete();

        return [
            'code' => 'LOGGED_OUT',
            'message' => 'Logout berhasil.',
        ];
    });
});

Route::middleware(['guestory.auth', 'guestory.role:SUPERADMIN'])->prefix('superadmin')->group(function () {
    Route::get('/users', function (Request $request) {
        $query = User::query()->whereIn('role', ['EVENT_OWNER', 'SUPERADMIN'])->withCount('events')->latest();
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return ['users' => $query->limit(100)->get()->map(fn (User $user) => userPayload($user) + [
            'events_count' => $user->events_count, 'created_at' => $user->created_at?->toISOString(),
        ])];
    });

    Route::post('/users', function (Request $request) {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($data['email']));
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json(['code' => 'EMAIL_ALREADY_EXISTS', 'message' => 'Email sudah digunakan.'], 422);
        }
        $user = User::create([
            'name' => trim($data['name']), 'email' => $email, 'password' => null, 'role' => 'EVENT_OWNER',
            'status' => 'PENDING_ACTIVATION', 'provisioned_by' => $request->user()->id,
        ]);
        sendAccountLink($user, 'ACTIVATE_ACCOUNT');

        return response()->json(['user' => userPayload($user), 'code' => 'OWNER_PROVISIONED', 'message' => 'Akun dibuat dan link aktivasi dikirim.'], 201);
    });

    Route::post('/users/{user}/activation/resend', function (User $user) {
        abort_unless($user->role === 'EVENT_OWNER' && $user->status === 'PENDING_ACTIVATION', 404);
        sendAccountLink($user, 'ACTIVATE_ACCOUNT');

        return ['code' => 'ACTIVATION_LINK_SENT', 'message' => 'Link aktivasi baru dikirim.'];
    })->middleware('throttle:5,1');

    Route::patch('/users/{user}/status', function (Request $request, User $user) {
        abort_unless($user->role === 'EVENT_OWNER', 404);
        $data = $request->validate(['status' => ['required', Rule::in(['ACTIVE', 'SUSPENDED'])]]);
        $user->update(['status' => $data['status']]);
        if ($data['status'] === 'SUSPENDED') {
            $user->accessTokens()->delete();
        }

        return ['user' => userPayload($user), 'code' => 'USER_STATUS_UPDATED'];
    });
});

Route::middleware(['guestory.auth', 'guestory.role:EVENT_OWNER,SUPERADMIN'])->group(function () {
    Route::get('/admin/billing/plans', function (GuestoryBilling $billing) {
        return ['plans' => $billing->plans()];
    });

    Route::get('/admin/events/{event}/billing', function (Request $request, Event $event, GuestoryBilling $billing) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        return [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'billing' => $billing->payload($event->billing),
            'entitlements' => $event->billing?->entitlements,
        ];
    });

    Route::post('/admin/events/{event}/billing/free', function (Request $request, Event $event, GuestoryBilling $billing) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $eventBilling = $billing->activateFree($event);

        return ['billing' => $billing->payload($eventBilling), 'entitlements' => $eventBilling->entitlements];
    });

    Route::post('/admin/events/{event}/billing/checkout', function (Request $request, Event $event, GuestoryBilling $billing) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate(['plan_code' => ['required', 'string', Rule::in(['BASIC', 'PREMIUM'])]]);

        try {
            $result = $billing->checkout($event, $data['plan_code']);
        } catch (Throwable $error) {
            Log::warning('Guestory checkout failed.', ['event_id' => $event->id, 'error' => $error->getMessage()]);

            return response()->json([
                'code' => 'PAYMENT_CHECKOUT_UNAVAILABLE',
                'message' => $error->getMessage(),
            ], 503);
        }

        return response()->json([
            'billing' => $billing->payload($result['billing']),
            'redirect_url' => $result['redirect_url'],
        ], 201);
    });

    Route::get('/admin/events/{event}/billing/status', function (Request $request, Event $event, GuestoryBilling $billing) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        return ['billing' => $billing->payload($event->billing), 'entitlements' => $event->billing?->entitlements];
    });

    Route::post('/admin/events/{event}/billing/sync', function (Request $request, Event $event, GuestoryBilling $billing) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! $event->billing) {
            return response()->json(['code' => 'BILLING_NOT_FOUND', 'message' => 'Billing event belum dibuat.'], 404);
        }

        try {
            $eventBilling = $billing->sync($event->billing);
        } catch (Throwable $error) {
            Log::warning('Guestory payment sync failed.', ['event_id' => $event->id, 'error' => $error->getMessage()]);

            return response()->json(['code' => 'PAYMENT_SYNC_UNAVAILABLE', 'message' => 'Status pembayaran belum dapat disinkronkan.'], 503);
        }

        return ['billing' => $billing->payload($eventBilling), 'entitlements' => $eventBilling->entitlements];
    });

    Route::get('/admin/events', function (Request $request) {
        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['Draft', 'Published', 'Archived'])],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $events = Event::query()
            ->where('owner_id', $request->user()->id)
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->where('name', 'ilike', "%{$search}%"))
            ->withCount(['guests', 'checkIns', 'photos'])
            ->latest('date')
            ->get()
            ->map(fn (Event $event) => eventPayload($event));

        return ['events' => $events];
    });

    Route::post('/admin/events', function (Request $request) {
        $data = $request->validate(eventValidationRules());
        $status = $data['status'] ?? 'Draft';

        $event = Event::create([
            ...$data,
            'owner_id' => $request->user()->id,
            'status' => $status,
            'published_at' => $status === 'Published' ? now() : null,
        ]);

        return response()->json(['event' => eventPayload($event)], 201);
    });

    Route::get('/admin/events/{event}/receivers', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        return [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'receivers' => $event->receivers()->with('user')->latest('created_at')->get()
                ->map(fn (EventReceiver $assignment) => receiverAssignmentPayload($assignment)),
        ];
    });

    Route::post('/admin/events/{event}/receivers', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'event_ids' => ['nullable', 'array', 'min:1'],
            'event_ids.*' => ['integer', 'distinct', 'exists:events,id'],
        ]);
        $email = Str::lower(trim($data['email']));
        $eventIds = collect($data['event_ids'] ?? [$event->id])->map(fn ($id) => (int) $id)->unique()->values();
        if (! $eventIds->contains($event->id)) {
            $eventIds->push($event->id);
        }
        if (Event::query()->where('owner_id', $request->user()->id)->whereIn('id', $eventIds)->count() !== $eventIds->count()) {
            return eventForbiddenResponse();
        }

        $created = false;
        $user = DB::transaction(function () use ($data, $email, $eventIds, $request, &$created) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            if (! $user) {
                if (blank($data['name'] ?? null)) {
                    abort(422, 'Nama wajib diisi untuk akun baru.');
                }
                $user = User::create([
                    'name' => trim($data['name']), 'email' => $email, 'password' => null, 'role' => 'RECEIVER',
                    'status' => 'PENDING_ACTIVATION', 'provisioned_by' => $request->user()->id,
                ]);
                $created = true;
            }

            foreach ($eventIds as $eventId) {
                EventReceiver::query()->updateOrCreate(
                    ['event_id' => $eventId, 'user_id' => $user->id],
                    ['status' => 'ACTIVE', 'created_at' => now()],
                );
            }

            return $user;
        });

        if ($created) {
            sendAccountLink($user, 'ACTIVATE_ACCOUNT');
        }

        $assignment = EventReceiver::query()->with('user')->where('event_id', $event->id)->where('user_id', $user->id)->firstOrFail();

        return response()->json([
            'receiver' => receiverAssignmentPayload($assignment),
            'assigned_event_ids' => $eventIds,
            'account_reused' => ! $created,
            'code' => $created ? 'RECEIVER_INVITED' : 'RECEIVER_ASSIGNED',
            'message' => $created ? 'Petugas ditambahkan dan link aktivasi dikirim.' : 'Akun yang sudah ada ditambahkan tanpa mengubah kapabilitasnya.',
        ], $created ? 201 : 200);
    });

    Route::delete('/admin/events/{event}/receivers/{user}', function (Request $request, Event $event, User $user) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }
        $assignment = EventReceiver::query()->where('event_id', $event->id)->where('user_id', $user->id)->first();
        if (! $assignment) {
            return response()->json(['code' => 'RECEIVER_ASSIGNMENT_NOT_FOUND', 'message' => 'Penugasan petugas tidak ditemukan.'], 404);
        }
        $assignment->update(['status' => 'REVOKED']);

        return ['code' => 'RECEIVER_ASSIGNMENT_REVOKED', 'message' => 'Akses petugas ke event ini dicabut.'];
    });

    Route::post('/admin/events/{event}/receivers/{user}/activation/resend', function (Request $request, Event $event, User $user) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }
        abort_unless(EventReceiver::query()->where('event_id', $event->id)->where('user_id', $user->id)->exists(), 404);
        if ($user->status !== 'PENDING_ACTIVATION' || $user->password || $user->activated_at) {
            return response()->json(['code' => 'ACTIVATION_NOT_REQUIRED', 'message' => 'Akun ini tidak memerlukan aktivasi.'], 409);
        }
        sendAccountLink($user, 'ACTIVATE_ACCOUNT');

        return ['code' => 'ACTIVATION_LINK_SENT', 'message' => 'Link aktivasi baru dikirim.'];
    })->middleware('throttle:5,1');

    Route::get('/admin/events/{event}', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        return ['event' => eventPayload($event->loadCount(['guests', 'checkIns', 'photos']))];
    });

    Route::get('/admin/events/{event}/invitation-config', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        return ['invitation_config' => invitationConfigPayload($event)];
    });

    Route::put('/admin/events/{event}/invitation-config', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate(invitationConfigValidationRules());
        $config = $event->invitationConfig()->updateOrCreate([], $data);

        return ['invitation_config' => invitationConfigPayload($event, $config)];
    });

    Route::get('/admin/events/{event}/invitation-design-assets', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        return ['assets' => invitationDesignAssetsPayload($event)];
    });

    Route::post('/admin/events/{event}/invitation-design-assets', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:12'],
            'files.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $existingCount = $event->invitationDesignAssets()->count();
        if ($existingCount + count($data['files']) > 12) {
            return response()->json([
                'code' => 'DESIGN_ASSET_LIMIT_EXCEEDED',
                'message' => 'Maksimal 12 gambar slideshow per event.',
                'limit' => 12,
            ], 422);
        }

        $storedPaths = [];
        try {
            $assets = DB::transaction(function () use ($event, $data, $existingCount, &$storedPaths) {
                $created = collect();
                foreach ($data['files'] as $index => $file) {
                    $path = $file->store("events/{$event->id}/invitation-design", 'public');
                    $storedPaths[] = $path;
                    $created->push(InvitationDesignAsset::create([
                        'event_id' => $event->id,
                        'file_path' => $path,
                        'file_url' => Storage::disk('public')->url($path),
                        'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'position' => $existingCount + $index,
                        'is_cover' => $existingCount === 0 && $index === 0,
                    ]));
                }

                return $created;
            });
        } catch (Throwable $error) {
            Storage::disk('public')->delete($storedPaths);
            throw $error;
        }

        return response()->json(['assets' => $assets->map(fn ($asset) => invitationDesignAssetPayload($asset))], 201);
    });

    Route::put('/admin/events/{event}/invitation-design-assets/order', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }
        $data = $request->validate(['asset_ids' => ['required', 'array', 'max:12'], 'asset_ids.*' => ['required', 'integer', 'distinct']]);
        $assets = $event->invitationDesignAssets()->get();
        if (collect($data['asset_ids'])->sort()->values()->all() !== $assets->pluck('id')->sort()->values()->all()) {
            return response()->json(['code' => 'INVALID_DESIGN_ASSET_ORDER', 'message' => 'Urutan harus memuat seluruh aset event tepat satu kali.'], 422);
        }
        DB::transaction(function () use ($assets, $data) {
            foreach ($assets as $asset) {
                $asset->update(['position' => $asset->position + 100]);
            }
            foreach ($data['asset_ids'] as $position => $id) {
                InvitationDesignAsset::whereKey($id)->update(['position' => $position, 'is_cover' => $position === 0]);
            }
        });

        return ['assets' => invitationDesignAssetsPayload($event)];
    });

    Route::put('/admin/events/{event}/invitation-design-assets/{asset}/cover', function (Request $request, Event $event, InvitationDesignAsset $asset) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }
        if ($asset->event_id !== $event->id) {
            return response()->json(['code' => 'DESIGN_ASSET_NOT_FOUND', 'message' => 'Aset slideshow tidak ditemukan.'], 404);
        }
        DB::transaction(function () use ($event, $asset) {
            $ordered = $event->invitationDesignAssets()->get()->reject(fn ($item) => $item->id === $asset->id)->prepend($asset)->values();
            foreach ($ordered as $item) {
                $item->update(['position' => $item->position + 100, 'is_cover' => false]);
            }
            foreach ($ordered as $position => $item) {
                $item->update(['position' => $position, 'is_cover' => $position === 0]);
            }
        });

        return ['assets' => invitationDesignAssetsPayload($event)];
    });

    Route::delete('/admin/events/{event}/invitation-design-assets/{asset}', function (Request $request, Event $event, InvitationDesignAsset $asset) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }
        if ($asset->event_id !== $event->id) {
            return response()->json(['code' => 'DESIGN_ASSET_NOT_FOUND', 'message' => 'Aset slideshow tidak ditemukan.'], 404);
        }
        if (Storage::disk('public')->exists($asset->file_path) && ! Storage::disk('public')->delete($asset->file_path)) {
            return response()->json(['code' => 'DESIGN_ASSET_DELETE_FAILED', 'message' => 'File aset gagal dihapus. Silakan coba lagi.'], 500);
        }
        DB::transaction(function () use ($event, $asset) {
            $wasCover = $asset->is_cover;
            $asset->delete();
            $remaining = $event->invitationDesignAssets()->get();
            foreach ($remaining as $position => $item) {
                $item->update(['position' => $position + 100]);
            }
            foreach ($remaining as $position => $item) {
                $item->update(['position' => $position]);
            }
            if ($wasCover && $remaining->isNotEmpty()) {
                $remaining->first()->update(['is_cover' => true]);
            }
        });

        return ['code' => 'DESIGN_ASSET_DELETED', 'assets' => invitationDesignAssetsPayload($event)];
    });

    Route::patch('/admin/events/{event}', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate(eventValidationRules(partial: true));

        if (($data['status'] ?? null) === 'Published' && $event->published_at === null) {
            $data['published_at'] = now();
        }

        if (($data['status'] ?? null) !== null && $data['status'] !== 'Published') {
            $data['published_at'] = null;
        }

        $event->update($data);

        return ['event' => eventPayload($event)];
    });

    Route::delete('/admin/events/{event}', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $event->delete();

        return [
            'code' => 'EVENT_DELETED',
            'message' => 'Event berhasil dihapus.',
        ];
    });

    Route::post('/admin/events/{event}/publish', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $event->forceFill([
            'status' => 'Published',
            'published_at' => $event->published_at ?? now(),
        ])->save();

        return ['event' => eventPayload($event)];
    });

    Route::post('/admin/events/{event}/archive', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $event->forceFill([
            'status' => 'Archived',
            'published_at' => null,
        ])->save();

        return ['event' => eventPayload($event)];
    });

    Route::get('/admin/events/{event}/guests', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'rsvp_status' => ['nullable', 'string', Rule::in(['PENDING', 'ATTENDING', 'DECLINED'])],
            'invitation_status' => ['nullable', 'string', Rule::in(['DRAFT', 'SENT', 'OPENED'])],
            'attendance_status' => ['nullable', 'string', Rule::in(['NOT_CHECKED_IN', 'CHECKED_IN'])],
            'qr_status' => ['nullable', 'string', Rule::in(['ACTIVE', 'REVOKED', 'EXPIRED'])],
        ]);

        $guests = $event->guests()
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->where('name', 'ilike', "%{$search}%"))
            ->when($data['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($data['rsvp_status'] ?? null, fn ($query, string $status) => $query->where('rsvp_status', $status))
            ->when($data['invitation_status'] ?? null, fn ($query, string $status) => $query->where('invitation_status', $status))
            ->when($data['attendance_status'] ?? null, fn ($query, string $status) => $query->where('attendance_status', $status))
            ->when($data['qr_status'] ?? null, fn ($query, string $status) => $query->whereHas('qrTokens', fn ($qrQuery) => $qrQuery->where('status', $status)))
            ->with(['invitation', 'checkIn.receiver', 'qrTokens'])
            ->withCount('photos')
            ->orderBy('name')
            ->get()
            ->map(fn (Guest $guest) => guestPayload($guest));

        return ['guests' => $guests];
    });

    Route::post('/admin/events/{event}/guests', function (Request $request, Event $event, GuestoryBilling $billing) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate(guestValidationRules($event));
        if (filled($data['phone'] ?? null)) {
            $data['phone'] = normalizeWhatsAppRecipient($data['phone']);
        }
        $result = DB::transaction(function () use ($event, $data, $billing) {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $current = $lockedEvent->guests()->count();
            $limit = (int) $billing->effectiveEntitlements($lockedEvent->load('billing'))['guest_limit'];
            if ($current + 1 > $limit) {
                return ['quota' => true, 'current' => $current, 'limit' => $limit, 'requested' => 1];
            }

            $data['guest_code'] = $data['guest_code'] ?? nextGuestCode($lockedEvent);

            return ['guest' => $lockedEvent->guests()->create($data)];
        });

        if ($result['quota'] ?? false) {
            return quotaExceededResponse('GUEST_LIMIT_EXCEEDED', 'guest records', $result);
        }

        return response()->json(['guest' => guestPayload($result['guest'])], 201);
    });

    Route::post('/admin/events/{event}/guests/import', function (Request $request, Event $event, GuestoryBilling $billing) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate([
            'guests' => ['nullable', 'array'],
            'guests.*.guest_code' => ['nullable', 'string', 'max:40'],
            'guests.*.name' => ['required_with:guests', 'string', 'max:160'],
            'guests.*.phone' => ['nullable', 'string', 'max:40'],
            'guests.*.email' => ['nullable', 'email', 'max:160'],
            'guests.*.category' => ['nullable', 'string', 'max:80'],
            'guests.*.group_name' => ['nullable', 'string', 'max:120'],
            'guests.*.guest_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'guests.*.table_number' => ['nullable', 'string', 'max:40'],
            'guests.*.notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $allowedImportKeys = ['guest_code', 'name', 'phone', 'email', 'category', 'group_name', 'guest_count', 'table_number', 'notes'];
        $rows = collect($request->input('guests', []))
            ->map(fn (array $row) => array_intersect_key($row, array_flip($allowedImportKeys)))
            ->all();

        if ($request->hasFile('file')) {
            $rows = array_merge($rows, parseGuestCsv($request->file('file')->get()));
        }

        if ($rows === []) {
            return response()->json([
                'code' => 'GUEST_IMPORT_EMPTY',
                'message' => 'Data import tamu kosong.',
            ], 422);
        }

        $result = DB::transaction(function () use ($event, $rows, $billing) {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $current = $lockedEvent->guests()->count();
            $limit = (int) $billing->effectiveEntitlements($lockedEvent->load('billing'))['guest_limit'];
            $requested = count($rows);
            if ($current + $requested > $limit) {
                return ['quota' => true, 'current' => $current, 'limit' => $limit, 'requested' => $requested];
            }

            $created = [];

            foreach ($rows as $row) {
                $row['guest_code'] = filled($row['guest_code'] ?? null) ? $row['guest_code'] : nextGuestCode($lockedEvent);
                $row['category'] = $row['category'] ?? 'Other';
                $row['guest_count'] = $row['guest_count'] ?? 1;
                if (filled($row['phone'] ?? null)) {
                    $row['phone'] = normalizeWhatsAppRecipient($row['phone']);
                }

                $created[] = $lockedEvent->guests()->create($row);
            }

            return ['created' => $created];
        });

        if ($result['quota'] ?? false) {
            return quotaExceededResponse('GUEST_LIMIT_EXCEEDED', 'guest records', $result);
        }

        $created = $result['created'];

        return response()->json([
            'imported' => count($created),
            'guests' => collect($created)->map(fn (Guest $guest) => guestPayload($guest)),
        ], 201);
    });

    Route::get('/admin/events/{event}/guests/export', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $headers = ['guest_code', 'name', 'phone', 'email', 'category', 'group_name', 'guest_count', 'table_number', 'rsvp_status', 'invitation_status', 'attendance_status', 'notes'];
        $lines = [csvLine($headers)];

        $event->guests()->orderBy('name')->each(function (Guest $guest) use (&$lines, $headers) {
            $lines[] = csvLine(array_map(fn (string $key) => $guest->{$key}, $headers));
        });

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="guestory-guests-'.$event->id.'.csv"',
        ]);
    });

    Route::get('/admin/events/{event}/guests/{guest}', function (Request $request, Event $event, Guest $guest) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        return ['guest' => guestPayload($guest->load(['invitation', 'checkIn.receiver', 'qrTokens'])->loadCount('photos'))];
    });

    Route::patch('/admin/events/{event}/guests/{guest}', function (Request $request, Event $event, Guest $guest) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        $data = $request->validate(guestValidationRules($event, $guest, partial: true));
        if (filled($data['phone'] ?? null)) {
            $data['phone'] = normalizeWhatsAppRecipient($data['phone']);
        }
        $guest->update($data);

        return ['guest' => guestPayload($guest)];
    });

    Route::delete('/admin/events/{event}/guests/{guest}', function (Request $request, Event $event, Guest $guest) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        $guest->delete();

        return [
            'code' => 'GUEST_DELETED',
            'message' => 'Tamu berhasil dihapus.',
        ];
    });

    Route::get('/admin/events/{event}/invitations', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $invitations = $event->invitations()
            ->with('guest:id,event_id,guest_code,name,invitation_status,rsvp_status')
            ->latest('updated_at')
            ->get()
            ->map(fn (Invitation $invitation) => invitationPayload($invitation));

        return ['invitations' => $invitations];
    });

    Route::get('/admin/events/{event}/qr-codes', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $qrTokens = $event->qrTokens()
            ->with('guest:id,event_id,guest_code,name,attendance_status')
            ->latest('updated_at')
            ->get()
            ->map(fn (QRToken $qrToken) => qrPayload($qrToken));

        return ['qr_codes' => $qrTokens];
    });

    Route::post('/admin/events/{event}/guests/{guest}/invitation/generate', function (Request $request, Event $event, Guest $guest) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        $invitation = Invitation::updateOrCreate(
            ['event_id' => $event->id, 'guest_id' => $guest->id],
            [
                'token' => $guest->invitation?->token ?? uniqueInvitationToken(),
                'status' => 'PUBLISHED',
                'published_at' => now(),
            ],
        );

        $guest->forceFill(['invitation_status' => 'SENT'])->save();

        return response()->json(['invitation' => invitationPayload($invitation->load('guest'))], 201);
    });

    Route::post('/admin/events/{event}/guests/{guest}/qr/generate', function (Request $request, Event $event, Guest $guest) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        $qrToken = $guest->qrTokens()->where('status', 'ACTIVE')->latest('id')->first()
            ?? $guest->qrTokens()->create([
                'event_id' => $event->id,
                'token' => uniqueQrToken(),
                'status' => 'ACTIVE',
            ]);

        return response()->json(['qr' => qrPayload($qrToken->load('guest'))], 201);
    });

    Route::post('/admin/events/{event}/guests/{guest}/qr/regenerate', function (Request $request, Event $event, Guest $guest) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        $qrToken = DB::transaction(function () use ($event, $guest) {
            $guest->qrTokens()
                ->where('status', 'ACTIVE')
                ->update(['status' => 'REVOKED', 'revoked_at' => now()]);

            return $guest->qrTokens()->create([
                'event_id' => $event->id,
                'token' => uniqueQrToken(),
                'status' => 'ACTIVE',
            ]);
        });

        return response()->json(['qr' => qrPayload($qrToken->load('guest'))], 201);
    });

    Route::post('/admin/events/{event}/guests/{guest}/qr/revoke', function (Request $request, Event $event, Guest $guest) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        $updated = $guest->qrTokens()
            ->where('status', 'ACTIVE')
            ->update(['status' => 'REVOKED', 'revoked_at' => now()]);

        return [
            'code' => 'QR_REVOKED',
            'message' => $updated > 0 ? 'QR Code berhasil dinonaktifkan.' : 'Tidak ada QR aktif untuk tamu ini.',
        ];
    });

    Route::post('/admin/events/{event}/guests/{guest}/qr/activate', function (Request $request, Event $event, Guest $guest) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        $qrToken = DB::transaction(function () use ($event, $guest) {
            $latestQrToken = $guest->qrTokens()->latest('id')->first();

            if (! $latestQrToken) {
                $latestQrToken = $guest->qrTokens()->create([
                    'event_id' => $event->id,
                    'token' => uniqueQrToken(),
                    'status' => 'ACTIVE',
                ]);
            }

            $guest->qrTokens()
                ->whereKeyNot($latestQrToken->id)
                ->where('status', 'ACTIVE')
                ->update(['status' => 'REVOKED', 'revoked_at' => now()]);

            $latestQrToken->forceFill([
                'status' => 'ACTIVE',
                'revoked_at' => null,
            ])->save();

            return $latestQrToken;
        });

        return ['qr' => qrPayload($qrToken->load('guest'))];
    });

    Route::get('/admin/events/{event}/guests/{guest}/qr/download', function (Request $request, Event $event, Guest $guest) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        $qrToken = $guest->qrTokens()->where('status', 'ACTIVE')->latest('id')->first();
        if (! $qrToken) {
            return response()->json([
                'code' => 'QR_NOT_FOUND',
                'message' => 'QR aktif tidak ditemukan.',
            ], 404);
        }

        $svg = (new SvgWriter)->write(new QrCode(qrPublicUrl($qrToken)))->getString();

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="guestory-'.$guest->guest_code.'.svg"',
        ]);
    });

    Route::get('/admin/events/{event}/whatsapp/messages', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['PENDING', 'QUEUED', 'SENT', 'FAILED', 'SKIPPED'])],
            'message_type' => ['nullable', 'string', Rule::in(['INVITATION', 'REMINDER'])],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $messages = $event->whatsAppMessages()
            ->with('guest:id,event_id,guest_code,name,phone')
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($data['message_type'] ?? null, fn ($query, string $messageType) => $query->where('message_type', $messageType))
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('recipient', 'ilike', "%{$search}%")
                    ->orWhereHas('guest', fn ($guestQuery) => $guestQuery->where('name', 'ilike', "%{$search}%"));
            }))
            ->latest()
            ->get()
            ->map(fn (WhatsAppMessage $message) => whatsAppMessagePayload($message));

        return [
            'messages' => $messages,
            'summary' => whatsAppSummary($event),
        ];
    });

    Route::post('/admin/events/{event}/guests/{guest}/whatsapp/invitation', function (Request $request, Event $event, Guest $guest, WapiClient $wapi) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! guestBelongsToEvent($guest, $event)) {
            return guestNotFoundResponse();
        }

        $result = sendInvitationWhatsApp($event, $guest, $wapi);

        return response()->json([
            'code' => 'WHATSAPP_INVITATION_QUEUED',
            'message' => 'Pengiriman undangan WhatsApp diproses.',
            'whatsapp_message' => whatsAppMessagePayload($result),
        ], 202);
    });

    Route::post('/admin/events/{event}/whatsapp/invitations/send', function (Request $request, Event $event, WapiClient $wapi) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate([
            'guest_ids' => ['nullable', 'array'],
            'guest_ids.*' => ['integer'],
        ]);

        $guests = $event->guests()
            ->when($data['guest_ids'] ?? null, fn ($query, array $guestIds) => $query->whereIn('id', $guestIds))
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('name')
            ->get();

        if ($guests->isEmpty()) {
            return response()->json([
                'code' => 'WHATSAPP_RECIPIENT_EMPTY',
                'message' => 'Tidak ada tamu dengan nomor WhatsApp yang bisa dikirim.',
            ], 422);
        }

        $messages = $guests->map(fn (Guest $guest) => sendInvitationWhatsApp($event, $guest, $wapi));

        return response()->json([
            'code' => 'WHATSAPP_BULK_INVITATION_PROCESSED',
            'message' => 'Pengiriman undangan WhatsApp diproses.',
            'processed' => $messages->count(),
            'messages' => $messages->map(fn (WhatsAppMessage $message) => whatsAppMessagePayload($message))->values(),
        ], 202);
    });

    Route::post('/admin/events/{event}/whatsapp/messages/{message}/resend', function (Request $request, Event $event, WhatsAppMessage $message, WapiClient $wapi) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if ((int) $message->event_id !== (int) $event->id) {
            return response()->json([
                'code' => 'WHATSAPP_MESSAGE_NOT_FOUND',
                'message' => 'Log WhatsApp tidak ditemukan.',
            ], 404);
        }

        $result = sendWhatsAppMessage($event, $message->guest, $message->body, $wapi, $message->message_type);

        return response()->json([
            'code' => 'WHATSAPP_MESSAGE_RESENT',
            'message' => 'Pengiriman ulang WhatsApp diproses.',
            'whatsapp_message' => whatsAppMessagePayload($result),
        ], 202);
    });
});

Route::middleware(['guestory.auth', 'guestory.role:EVENT_OWNER,SUPERADMIN'])->get('/admin/events/{event}/dashboard', function (Request $request, Event $event) {
    if ($event->owner_id !== $request->user()->id) {
        return response()->json([
            'code' => 'EVENT_FORBIDDEN',
            'message' => 'Admin tidak memiliki akses ke event ini.',
        ], 403);
    }

    $totalGuests = $event->guests()->count();
    $confirmedGuests = $event->guests()->where('rsvp_status', 'ATTENDING')->sum('guest_count');
    $checkedIn = $event->checkIns()->sum('actual_guest_count');
    $notCheckedIn = max($event->guests()->sum('guest_count') - $checkedIn, 0);

    return [
        'event' => [
            'id' => $event->id,
            'name' => $event->name,
            'status' => $event->status,
            'date' => $event->date?->toDateString(),
        ],
        'metrics' => [
            'total_guests' => $totalGuests,
            'confirmed_guests' => $confirmedGuests,
            'pending_rsvp' => $event->guests()->where('rsvp_status', 'PENDING')->count(),
            'declined' => $event->guests()->where('rsvp_status', 'DECLINED')->count(),
            'checked_in' => $checkedIn,
            'not_checked_in' => $notCheckedIn,
            'attendance_rate' => $confirmedGuests > 0 ? round(($checkedIn / $confirmedGuests) * 100, 1) : 0,
            'total_photos' => $event->photos()->count(),
        ],
        'recent_check_ins' => $event->checkIns()
            ->with(['guest:id,name', 'receiver:id,name'])
            ->latest('checked_in_at')
            ->limit(10)
            ->get()
            ->map(fn (CheckIn $checkIn) => [
                'guest_name' => $checkIn->guest?->name,
                'receiver_name' => $checkIn->receiver?->name,
                'method' => $checkIn->method,
                'actual_guest_count' => $checkIn->actual_guest_count,
                'checked_in_at' => $checkIn->checked_in_at?->format('H:i'),
            ]),
    ];
});

Route::middleware(['guestory.auth', 'guestory.role:EVENT_OWNER,SUPERADMIN'])->group(function () {
    Route::get('/admin/events/{event}/attendance', function (Request $request, Event $event) {
        if ($event->owner_id !== $request->user()->id) {
            return response()->json(['code' => 'EVENT_FORBIDDEN', 'message' => 'Admin tidak memiliki akses ke event ini.'], 403);
        }

        $data = attendanceFilterRules($request);
        $records = attendanceRecords($event, $data);

        return [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'filters' => $data,
            'summary' => attendanceSummary($event),
            'attendance' => $records->values(),
        ];
    });

    Route::get('/admin/events/{event}/attendance/export', function (Request $request, Event $event) {
        if ($event->owner_id !== $request->user()->id) {
            return response()->json(['code' => 'EVENT_FORBIDDEN', 'message' => 'Admin tidak memiliki akses ke event ini.'], 403);
        }

        $data = attendanceFilterRules($request);
        $records = attendanceRecords($event, $data);
        $lines = [
            csvLine(['guest_code', 'name', 'category', 'rsvp_status', 'attendance_status', 'guest_count', 'actual_guest_count', 'method', 'checked_in_at', 'receiver']),
        ];

        foreach ($records as $record) {
            $lines[] = csvLine([
                $record['guest_code'],
                $record['name'],
                $record['category'],
                $record['rsvp_status'],
                $record['attendance_status'],
                $record['guest_count'],
                $record['actual_guest_count'],
                $record['method'],
                $record['checked_in_at'],
                $record['receiver_name'],
            ]);
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="guestory-attendance-'.$event->id.'.csv"',
        ]);
    });

    Route::get('/admin/events/{event}/guest-book', function (Request $request, Event $event) {
        if ($event->owner_id !== $request->user()->id) {
            return response()->json(['code' => 'EVENT_FORBIDDEN', 'message' => 'Admin tidak memiliki akses ke event ini.'], 403);
        }

        $data = $request->validate([
            'method' => ['nullable', 'string', Rule::in(['QR', 'MANUAL'])],
            'category' => ['nullable', 'string', 'max:80'],
            'rsvp_status' => ['nullable', 'string', Rule::in(['PENDING', 'ATTENDING', 'DECLINED'])],
            'sort' => ['nullable', 'string', Rule::in(['check_in_time', 'guest_name'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $checkIns = $event->checkIns()
            ->with(['guest:id,guest_code,name,category,rsvp_status,guest_count', 'receiver:id,name']);

        if (! empty($data['method'])) {
            $checkIns->where('method', $data['method']);
        }

        if (! empty($data['category']) || ! empty($data['rsvp_status'])) {
            $checkIns->whereHas('guest', function ($query) use ($data) {
                if (! empty($data['category'])) {
                    $query->where('category', $data['category']);
                }

                if (! empty($data['rsvp_status'])) {
                    $query->where('rsvp_status', $data['rsvp_status']);
                }
            });
        }

        $records = $checkIns->get()->map(fn (CheckIn $checkIn) => guestBookPayload($checkIn));
        $direction = $data['direction'] ?? 'desc';
        $sort = $data['sort'] ?? 'check_in_time';

        $records = $records->sortBy(
            $sort === 'guest_name' ? 'guest_name' : 'checked_in_at',
            SORT_REGULAR,
            $direction === 'desc',
        );

        return [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'guest_book' => $records->values(),
        ];
    });

    Route::get('/admin/events/{event}/guest-book/export', function (Request $request, Event $event) {
        if ($event->owner_id !== $request->user()->id) {
            return response()->json(['code' => 'EVENT_FORBIDDEN', 'message' => 'Admin tidak memiliki akses ke event ini.'], 403);
        }

        $checkIns = $event->checkIns()
            ->with(['guest:id,guest_code,name,category,rsvp_status,guest_count', 'receiver:id,name'])
            ->latest('checked_in_at')
            ->get()
            ->map(fn (CheckIn $checkIn) => guestBookPayload($checkIn));

        $lines = [
            csvLine(['guest_code', 'guest_name', 'category', 'guest_count', 'actual_guest_count', 'method', 'checked_in_at', 'receiver']),
        ];

        foreach ($checkIns as $record) {
            $lines[] = csvLine([
                $record['guest_code'],
                $record['guest_name'],
                $record['category'],
                $record['guest_count'],
                $record['actual_guest_count'],
                $record['method'],
                $record['checked_in_at'],
                $record['receiver_name'],
            ]);
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="guestory-guest-book-'.$event->id.'.csv"',
        ]);
    });

    Route::get('/admin/events/{event}/photos', function (Request $request, Event $event) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['PENDING', 'APPROVED', 'REJECTED'])],
            'guest_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $photos = $event->photos()
            ->with('guest:id,event_id,guest_code,name,category')
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($data['guest_id'] ?? null, fn ($query, int $guestId) => $query->where('guest_id', $guestId))
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->whereHas('guest', fn ($guestQuery) => $guestQuery->where('name', 'ilike', "%{$search}%")))
            ->latest('uploaded_at')
            ->get()
            ->map(fn (Photo $photo) => photoPayload($photo));

        return [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'photos' => $photos,
            'summary' => photoSummary($event),
        ];
    });

    Route::post('/admin/events/{event}/photos/{photo}/approve', function (Request $request, Event $event, Photo $photo) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! photoBelongsToEvent($photo, $event)) {
            return photoNotFoundResponse();
        }

        $photo->forceFill(['status' => 'APPROVED'])->save();

        return [
            'code' => 'PHOTO_APPROVED',
            'message' => 'Foto berhasil disetujui.',
            'photo' => photoPayload($photo->load('guest')),
        ];
    });

    Route::post('/admin/events/{event}/photos/{photo}/reject', function (Request $request, Event $event, Photo $photo) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! photoBelongsToEvent($photo, $event)) {
            return photoNotFoundResponse();
        }

        $photo->forceFill(['status' => 'REJECTED'])->save();

        return [
            'code' => 'PHOTO_REJECTED',
            'message' => 'Foto berhasil ditolak.',
            'photo' => photoPayload($photo->load('guest')),
        ];
    });

    Route::delete('/admin/events/{event}/photos/{photo}', function (Request $request, Event $event, Photo $photo) {
        if (! adminOwnsEvent($request, $event)) {
            return eventForbiddenResponse();
        }

        if (! photoBelongsToEvent($photo, $event)) {
            return photoNotFoundResponse();
        }

        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();

        return [
            'code' => 'PHOTO_DELETED',
            'message' => 'Foto berhasil dihapus.',
        ];
    });
});

Route::post('/payments/webhook', function (Request $request, GuestoryBilling $billing) {
    $rawBody = $request->getContent();
    $timestamp = $request->header('X-Pay-Adapter-Timestamp') ?? $request->header('X-Webhook-Timestamp') ?? $request->header('X-Timestamp');
    $signature = $request->header('X-Pay-Adapter-Signature') ?? $request->header('X-Webhook-Signature') ?? $request->header('X-Signature');
    $deliveryId = $request->header('X-Pay-Adapter-Delivery') ?? $request->header('X-Webhook-Id') ?? $request->header('X-Delivery-Id');

    if (! $billing->verifyWebhook($rawBody, $timestamp, $signature)) {
        return response()->json(['code' => 'INVALID_WEBHOOK_SIGNATURE', 'message' => 'Webhook signature tidak valid.'], 401);
    }

    if (! is_string($deliveryId) || trim($deliveryId) === '' || strlen($deliveryId) > 255) {
        return response()->json(['code' => 'INVALID_WEBHOOK_DELIVERY', 'message' => 'Webhook delivery ID wajib diisi.'], 422);
    }

    $payload = json_decode($rawBody, true);
    if (! is_array($payload)) {
        return response()->json(['code' => 'INVALID_WEBHOOK_PAYLOAD', 'message' => 'Payload webhook tidak valid.'], 422);
    }

    $processed = $billing->processWebhook($deliveryId, $payload);

    return ['code' => $processed ? 'WEBHOOK_PROCESSED' : 'WEBHOOK_DUPLICATE'];
})->middleware('throttle:120,1');

Route::get('/invite/{token}', function (string $token) {
    $invitation = findPublicInvitation($token);

    if (! $invitation instanceof Invitation) {
        return $invitation;
    }

    $invitation->forceFill(['opened_at' => $invitation->opened_at ?? now()])->save();
    $invitation->guest->forceFill(['invitation_status' => 'OPENED'])->save();
    $activeQrToken = QRToken::query()
        ->where('guest_id', $invitation->guest_id)
        ->where('event_id', $invitation->event_id)
        ->where('status', 'ACTIVE')
        ->latest('id')
        ->first();

    return [
        'token' => $invitation->token,
        'event' => [
            'id' => $invitation->event->id,
            'name' => $invitation->event->name,
            'type' => $invitation->event->type,
            'date' => $invitation->event->date?->toDateString(),
            'start_time' => $invitation->event->start_time,
            'end_time' => $invitation->event->end_time,
            'timezone' => $invitation->event->timezone,
            'venue' => $invitation->event->venue_name,
            'address' => $invitation->event->venue_address,
            'map_url' => $invitation->event->map_url,
        ],
        'guest' => [
            'id' => $invitation->guest->id,
            'name' => $invitation->guest->name,
            'guest_count' => $invitation->guest->guest_count,
            'rsvp_status' => $invitation->guest->rsvp_status,
            'attendance_status' => $invitation->guest->attendance_status,
        ],
        'qr' => [
            'status' => $activeQrToken?->status,
            'token' => $activeQrToken?->token,
        ],
        'qr_svg_url' => url("/api/invite/{$invitation->token}/qr.svg"),
        'photo_feature' => [
            'can_upload' => true,
            'album_status' => 'APPROVED_ONLY',
        ],
        'invitation_config' => invitationConfigPayload($invitation->event),
        'slideshow_assets' => publicInvitationDesignAssetsPayload($invitation->event),
    ];
})->middleware('throttle:60,1');

Route::patch('/invite/{token}/rsvp', function (Request $request, string $token) {
    $invitation = findPublicInvitation($token);

    if (! $invitation instanceof Invitation) {
        return $invitation;
    }

    $data = $request->validate([
        'rsvp_status' => ['required', 'string', Rule::in(['ATTENDING', 'DECLINED'])],
    ]);

    $invitation->guest->forceFill(['rsvp_status' => $data['rsvp_status']])->save();

    return [
        'code' => 'RSVP_UPDATED',
        'message' => 'RSVP berhasil disimpan.',
        'guest' => [
            'id' => $invitation->guest->id,
            'name' => $invitation->guest->name,
            'rsvp_status' => $invitation->guest->rsvp_status,
        ],
    ];
})->middleware('throttle:30,1');

Route::get('/invite/{token}/qr.svg', function (string $token) {
    $invitation = findPublicInvitation($token);

    if (! $invitation instanceof Invitation) {
        return $invitation;
    }

    $qrToken = QRToken::query()
        ->where('event_id', $invitation->event_id)
        ->where('guest_id', $invitation->guest_id)
        ->where('status', 'ACTIVE')
        ->latest('id')
        ->first();

    if (! $qrToken) {
        return response()->json([
            'code' => 'QR_NOT_FOUND',
            'message' => 'QR aktif tidak ditemukan.',
        ], 404);
    }

    $svg = (new SvgWriter)->write(new QrCode(qrPublicUrl($qrToken)))->getString();

    return response($svg, 200, [
        'Content-Type' => 'image/svg+xml',
        'Cache-Control' => 'private, max-age=300',
    ]);
})->middleware('throttle:60,1');

Route::get('/invite/{token}/album', function (Request $request, string $token) {
    $invitation = findPublicInvitation($token);

    if (! $invitation instanceof Invitation) {
        return $invitation;
    }

    $data = $request->validate([
        'page' => ['sometimes', 'integer', 'min:1'],
        'per_page' => ['sometimes', 'integer', 'min:1', 'max:24'],
    ]);
    $perPage = (int) ($data['per_page'] ?? 12);

    $photos = $invitation->event->photos()
        ->with('guest:id,name')
        ->where('status', 'APPROVED')
        ->orderByDesc('uploaded_at')
        ->orderByDesc('id')
        ->paginate($perPage)
        ->withQueryString();

    $photoItems = $photos->getCollection()->map(fn ($photo) => photoPayload($photo));

    return [
        'event' => [
            'id' => $invitation->event->id,
            'name' => $invitation->event->name,
        ],
        // Keep this top-level array for existing album consumers.
        'photos' => $photoItems,
        'meta' => [
            'current_page' => $photos->currentPage(),
            'last_page' => $photos->lastPage(),
            'per_page' => $photos->perPage(),
            'total' => $photos->total(),
            'from' => $photos->firstItem(),
            'to' => $photos->lastItem(),
            'has_next_page' => $photos->hasMorePages(),
            'has_previous_page' => $photos->currentPage() > 1,
            'next_page_url' => $photos->nextPageUrl(),
            'previous_page_url' => $photos->previousPageUrl(),
        ],
    ];
})->middleware('throttle:60,1');

Route::post('/invite/{token}/photos', function (Request $request, string $token, GuestoryBilling $billing) {
    $invitation = findPublicInvitation($token);

    if (! $invitation instanceof Invitation) {
        return $invitation;
    }

    $data = $request->validate([
        'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
    ]);

    $result = DB::transaction(function () use ($invitation, $data, $billing) {
        $lockedEvent = Event::query()->whereKey($invitation->event_id)->lockForUpdate()->firstOrFail();
        $current = $lockedEvent->photos()->count();
        $limit = (int) $billing->effectiveEntitlements($lockedEvent->load('billing'))['photo_limit'];
        if ($current + 1 > $limit) {
            return ['quota' => true, 'current' => $current, 'limit' => $limit, 'requested' => 1];
        }

        $path = $data['photo']->store("events/{$invitation->event_id}/photos", 'public');
        try {
            $photo = Photo::create([
                'event_id' => $invitation->event_id, 'guest_id' => $invitation->guest_id,
                'file_path' => $path, 'file_url' => Storage::disk('public')->url($path),
                'status' => 'PENDING', 'uploaded_at' => now(),
            ]);
        } catch (Throwable $error) {
            Storage::disk('public')->delete($path);
            throw $error;
        }

        return ['photo' => $photo];
    });

    if ($result['quota'] ?? false) {
        return quotaExceededResponse('PHOTO_LIMIT_EXCEEDED', 'photos', $result);
    }

    $photo = $result['photo'];

    return response()->json([
        'code' => 'PHOTO_UPLOADED',
        'message' => 'Foto berhasil diupload dan menunggu persetujuan admin.',
        'photo' => photoPayload($photo->load('guest')),
    ], 201);
})->middleware('throttle:20,1');

Route::get('/g/{token}', function (string $token) {
    $qrToken = QRToken::query()
        ->with(['event:id,name,status', 'guest:id,event_id,name,category,guest_count,attendance_status'])
        ->where('token', $token)
        ->first();

    return validateQrToken($qrToken);
})->middleware('throttle:120,1');

Route::middleware(['guestory.auth', 'throttle:120,1'])->post('/receiver/check-in/validate', function (Request $request) {
    $data = $request->validate([
        'token' => ['required', 'string'],
    ]);

    $qrToken = QRToken::query()
        ->with(['event:id,name,status', 'guest:id,event_id,name,category,guest_count,attendance_status'])
        ->where('token', $data['token'])
        ->first();

    return validateQrToken($qrToken, $request->user()->id);
});

Route::middleware(['guestory.auth', 'throttle:120,1'])->post('/receiver/check-in/confirm', function (Request $request) {
    $data = $request->validate([
        'token' => ['nullable', 'string'],
        'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
        'method' => ['required', 'string', 'in:QR,MANUAL'],
        'actual_guest_count' => ['nullable', 'integer', 'min:1', 'max:20'],
    ]);

    if (($data['method'] === 'QR' && empty($data['token'])) || ($data['method'] === 'MANUAL' && empty($data['guest_id']))) {
        return response()->json([
            'code' => 'CHECK_IN_PAYLOAD_INVALID',
            'message' => 'Data check-in tidak lengkap.',
        ], 422);
    }

    $qrToken = null;
    $guest = null;

    if ($data['method'] === 'QR') {
        $qrToken = QRToken::query()
            ->with(['event:id,name,status', 'guest:id,event_id,name,category,guest_count,attendance_status'])
            ->where('token', $data['token'])
            ->first();

        $validation = validateQrToken($qrToken, $request->user()->id);
        if ($validation->getData(true)['code'] !== 'CHECK_IN_ALLOWED') {
            return $validation;
        }

        $guest = $qrToken->guest;
    } else {
        $guest = Guest::query()->with('event:id,name,status')->find($data['guest_id']);
        if (! $guest) {
            return response()->json(['code' => 'GUEST_NOT_FOUND', 'message' => 'Data tamu tidak ditemukan.'], 404);
        }

        if (! receiverCanAccessEvent($request->user()->id, $guest->event_id)) {
            return response()->json(['code' => 'RECEIVER_EVENT_FORBIDDEN', 'message' => 'Receiver tidak memiliki akses ke event ini.'], 403);
        }

        if ($guest->checkIn()->exists()) {
            return duplicateCheckInResponse($guest);
        }
    }

    try {
        $checkIn = DB::transaction(function () use ($data, $guest, $qrToken, $request) {
            $checkIn = CheckIn::create([
                'event_id' => $guest->event_id,
                'guest_id' => $guest->id,
                'qr_token_id' => $qrToken?->id,
                'receiver_id' => $request->user()->id,
                'method' => $data['method'],
                'actual_guest_count' => $data['actual_guest_count'] ?? $guest->guest_count,
                'checked_in_at' => now(),
            ]);

            $guest->forceFill(['attendance_status' => 'CHECKED_IN'])->save();

            return $checkIn->load('guest:id,name');
        });
    } catch (QueryException) {
        return duplicateCheckInResponse($guest);
    }

    return checkInSuccessPayload($checkIn);
});

Route::middleware(['guestory.auth'])->group(function () {
    Route::get('/receiver/events', function (Request $request) {
        $events = Event::query()
            ->select(['events.id', 'events.name', 'events.type', 'events.date', 'events.start_time', 'events.end_time', 'events.timezone', 'events.venue_name', 'events.venue_address', 'events.status'])
            ->join('event_receivers', 'events.id', '=', 'event_receivers.event_id')
            ->where('event_receivers.user_id', $request->user()->id)
            ->where('event_receivers.status', 'ACTIVE')
            ->orderBy('events.date')
            ->withCount(['guests', 'checkIns'])
            ->get()
            ->map(fn (Event $event) => receiverEventPayload($event));

        return ['events' => $events];
    });

    Route::post('/receiver/events/{event}/check-in/validate', function (Request $request, Event $event) {
        $receiverEvent = receiverEventOrForbidden($request, $event);
        if ($receiverEvent) {
            return $receiverEvent;
        }

        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $qrToken = QRToken::query()
            ->with(['event:id,name,status', 'guest:id,event_id,name,category,guest_count,attendance_status'])
            ->where('token', $data['token'])
            ->first();

        if ($qrToken && $qrToken->event_id !== $event->id) {
            return response()->json(['code' => 'INVALID_EVENT', 'message' => 'QR Code bukan untuk event ini.'], 409);
        }

        return validateQrToken($qrToken, $request->user()->id);
    });

    Route::get('/receiver/events/{event}/guests/search', function (Request $request, Event $event) {
        $receiverEvent = receiverEventOrForbidden($request, $event);
        if ($receiverEvent) {
            return $receiverEvent;
        }

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'attendance_status' => ['nullable', 'string', Rule::in(['NOT_CHECKED_IN', 'CHECKED_IN'])],
        ]);

        $query = $event->guests()
            ->with(['checkIn:id,guest_id,method,actual_guest_count,checked_in_at'])
            ->orderBy('name');

        if (! empty($data['q'])) {
            $needle = '%'.$data['q'].'%';
            $query->where(function ($builder) use ($needle) {
                $builder
                    ->where('name', 'ilike', $needle)
                    ->orWhere('guest_code', 'ilike', $needle)
                    ->orWhere('phone', 'ilike', $needle);
            });
        }

        if (! empty($data['attendance_status'])) {
            $query->where('attendance_status', $data['attendance_status']);
        }

        return [
            'guests' => $query->limit(20)->get()->map(fn (Guest $guest) => receiverGuestPayload($guest)),
        ];
    });

    Route::post('/receiver/events/{event}/check-ins/manual', function (Request $request, Event $event) {
        $receiverEvent = receiverEventOrForbidden($request, $event);
        if ($receiverEvent) {
            return $receiverEvent;
        }

        $data = $request->validate([
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'actual_guest_count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $guest = $event->guests()
            ->with(['event:id,name,status'])
            ->whereKey($data['guest_id'])
            ->first();

        if (! $guest) {
            return response()->json(['code' => 'GUEST_NOT_FOUND', 'message' => 'Data tamu tidak ditemukan.'], 404);
        }

        if ($guest->checkIn()->exists()) {
            return duplicateCheckInResponse($guest);
        }

        try {
            $checkIn = DB::transaction(function () use ($data, $guest, $request) {
                $checkIn = CheckIn::create([
                    'event_id' => $guest->event_id,
                    'guest_id' => $guest->id,
                    'receiver_id' => $request->user()->id,
                    'method' => 'MANUAL',
                    'actual_guest_count' => $data['actual_guest_count'] ?? $guest->guest_count,
                    'checked_in_at' => now(),
                ]);

                $guest->forceFill(['attendance_status' => 'CHECKED_IN'])->save();

                return $checkIn->load('guest:id,name');
            });
        } catch (QueryException) {
            return duplicateCheckInResponse($guest);
        }

        return checkInSuccessPayload($checkIn);
    });

    Route::get('/receiver/events/{event}/check-ins/recent', function (Request $request, Event $event) {
        $receiverEvent = receiverEventOrForbidden($request, $event);
        if ($receiverEvent) {
            return $receiverEvent;
        }

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $checkIns = $event->checkIns()
            ->with(['guest:id,name,category', 'receiver:id,name'])
            ->latest('checked_in_at')
            ->limit($data['limit'] ?? 20)
            ->get()
            ->map(fn (CheckIn $checkIn) => checkInPayload($checkIn));

        return ['check_ins' => $checkIns];
    });
});

if (! function_exists('resetPasswordUrl')) {
    function resetPasswordUrl(string $email, string $token): string
    {
        $query = http_build_query([
            'email' => $email,
            'token' => $token,
        ]);

        return rtrim((string) config('app.url'), '/')."/reset-password?{$query}";
    }
}

if (! function_exists('userPayload')) {
    function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'whatsapp_number' => $user->whatsapp_number,
            'role' => $user->role,
            'status' => $user->status,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'capabilities' => [
                'platform_admin' => $user->isSuperadmin(),
                'manage_events' => $user->canManageEvents(),
                'receive_events' => $user->isReceiver(),
            ],
        ];
    }
}

if (! function_exists('receiverAssignmentPayload')) {
    function receiverAssignmentPayload(EventReceiver $assignment): array
    {
        $user = $assignment->user;

        return [
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'account_role' => $user->role, 'account_status' => $user->status,
            'assignment_status' => $assignment->status,
            'activated_at' => $user->activated_at?->toISOString(),
            'activation_required' => $user->status === 'PENDING_ACTIVATION' && ! $user->password,
            'assigned_at' => $assignment->created_at?->toISOString(),
        ];
    }
}

if (! function_exists('registrationResponse')) {
    function registrationResponse(): array
    {
        return [
            'code' => 'VERIFICATION_LINK_SENT',
            'message' => 'Jika data dapat diproses, link verifikasi akan dikirim ke email tersebut.',
        ];
    }
}

if (! function_exists('sendAccountLink')) {
    function sendAccountLink(User $user, string $purpose): void
    {
        $minutes = $purpose === 'ACTIVATE_ACCOUNT' ? 60 : 30;
        $token = AccountToken::issue($user, $purpose, $minutes);
        $path = $purpose === 'ACTIVATE_ACCOUNT' ? '/activate-account' : '/verify-email';
        $url = rtrim((string) config('app.url'), '/').$path.'?'.http_build_query(['token' => $token]);
        $subject = $purpose === 'ACTIVATE_ACCOUNT' ? 'Aktifkan akun Guestory' : 'Verifikasi email Guestory';
        $action = $purpose === 'ACTIVATE_ACCOUNT' ? 'mengaktifkan akun dan membuat password' : 'memverifikasi email';
        $text = "Halo {$user->name},\n\nKlik link berikut untuk {$action}:\n\n{$url}\n\nLink ini berlaku {$minutes} menit.";

        try {
            Mail::raw($text, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
        } catch (Throwable $error) {
            Log::warning('Guestory account email failed.', ['user_id' => $user->id, 'purpose' => $purpose, 'error' => $error->getMessage()]);
        }
    }
}

if (! function_exists('invitationDesignAssetPayload')) {
    function invitationDesignAssetPayload(InvitationDesignAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'url' => $asset->file_url,
            'position' => $asset->position,
            'is_cover' => $asset->is_cover,
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
        ];
    }
}

if (! function_exists('invitationDesignAssetsPayload')) {
    function invitationDesignAssetsPayload(Event $event): array
    {
        return $event->invitationDesignAssets()
            ->get()
            ->values()
            ->map(fn (InvitationDesignAsset $asset, int $index) => [...invitationDesignAssetPayload($asset), 'display_order' => $index])
            ->all();
    }
}

if (! function_exists('publicInvitationDesignAssetsPayload')) {
    function publicInvitationDesignAssetsPayload(Event $event): array
    {
        return collect(invitationDesignAssetsPayload($event))
            ->map(fn (array $asset) => [
                'id' => $asset['id'],
                'url' => $asset['url'],
                'order' => $asset['display_order'],
                'is_cover' => $asset['is_cover'],
            ])
            ->all();
    }
}

if (! function_exists('invitationConfigDefaults')) {
    function invitationConfigDefaults(Event $event): array
    {
        return [
            'theme' => 'classic',
            'sections' => collect(['hero', 'details', 'slideshow', 'qr', 'rsvp', 'photos'])
                ->map(fn (string $id, int $order) => ['id' => $id, 'enabled' => true, 'order' => $order])
                ->all(),
            'content' => [
                'eyebrow' => 'You are invited',
                'headline' => $event->name,
                'welcome_message' => 'Every Guest Has a Story',
                'details_heading' => 'Save the date',
                'slideshow_heading' => 'Our story',
                'slideshow_message' => 'A few moments we love, shared with you.',
                'qr_heading' => 'Personal QR',
                'qr_message' => 'Tunjukkan QR pribadi ini saat tiba di venue.',
                'rsvp_heading' => 'RSVP',
                'photos_heading' => 'Momen dari tamu',
                'photos_message' => 'Bagikan momen terbaikmu bersama kami.',
                'closing_message' => 'Terima kasih telah menjadi bagian dari cerita kami.',
            ],
        ];
    }
}

if (! function_exists('normalizeInvitationSections')) {
    function normalizeInvitationSections(?array $sections, array $defaults): array
    {
        if (! is_array($sections)) {
            return $defaults;
        }

        $allowed = collect($defaults)->pluck('id')->all();
        $normalized = collect($sections)
            ->filter(fn ($section) => is_array($section) && in_array($section['id'] ?? null, $allowed, true))
            ->unique('id')
            ->sortBy(fn ($section) => is_int($section['order'] ?? null) ? $section['order'] : PHP_INT_MAX)
            ->values()
            ->map(fn ($section) => [
                'id' => $section['id'],
                'enabled' => (bool) ($section['enabled'] ?? true),
            ]);

        foreach ($allowed as $id) {
            if (! $normalized->contains('id', $id)) {
                $normalized->push(['id' => $id, 'enabled' => true]);
            }
        }

        return $normalized
            ->values()
            ->map(fn ($section, $order) => [...$section, 'order' => $order])
            ->all();
    }
}

if (! function_exists('invitationConfigPayload')) {
    function invitationConfigPayload(Event $event, ?InvitationConfig $config = null): array
    {
        $config ??= $event->invitationConfig;
        $defaults = invitationConfigDefaults($event);

        return [
            'theme' => $config?->theme ?? $defaults['theme'],
            'sections' => normalizeInvitationSections($config?->sections, $defaults['sections']),
            'content' => array_merge($defaults['content'], $config?->content ?? []),
            'is_default' => $config === null,
            'updated_at' => $config?->updated_at?->toISOString(),
        ];
    }
}

if (! function_exists('invitationConfigValidationRules')) {
    function invitationConfigValidationRules(): array
    {
        return [
            'theme' => ['required', 'string', Rule::in(['classic', 'garden', 'midnight'])],
            'sections' => ['required', 'array', 'size:6'],
            'sections.*.id' => ['required', 'string', 'distinct', Rule::in(['hero', 'details', 'slideshow', 'qr', 'rsvp', 'photos'])],
            'sections.*.enabled' => ['required', 'boolean'],
            'sections.*.order' => ['required', 'integer', 'distinct', 'between:0,5'],
            'content' => ['required', 'array'],
            'content.eyebrow' => ['required', 'string', 'max:80'],
            'content.headline' => ['required', 'string', 'max:160'],
            'content.welcome_message' => ['required', 'string', 'max:500'],
            'content.details_heading' => ['required', 'string', 'max:100'],
            'content.slideshow_heading' => ['required', 'string', 'max:100'],
            'content.slideshow_message' => ['required', 'string', 'max:300'],
            'content.qr_heading' => ['required', 'string', 'max:100'],
            'content.qr_message' => ['required', 'string', 'max:300'],
            'content.rsvp_heading' => ['required', 'string', 'max:100'],
            'content.photos_heading' => ['required', 'string', 'max:100'],
            'content.photos_message' => ['required', 'string', 'max:300'],
            'content.closing_message' => ['required', 'string', 'max:500'],
        ];
    }
}

if (! function_exists('eventValidationRules')) {
    function eventValidationRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:160'],
            'type' => ['sometimes', 'string', Rule::in(['Wedding', 'Birthday', 'Engagement', 'Corporate', 'Gathering', 'Other'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'date' => [$required, 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'timezone' => ['sometimes', 'string', 'max:80'],
            'venue_name' => ['nullable', 'string', 'max:160'],
            'venue_address' => ['nullable', 'string', 'max:2000'],
            'map_url' => ['nullable', 'url', 'max:2000'],
            'cover_image' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', Rule::in(['Draft', 'Published', 'Archived'])],
        ];
    }
}

if (! function_exists('eventPayload')) {
    function eventPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'type' => $event->type,
            'description' => $event->description,
            'date' => $event->date?->toDateString(),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'timezone' => $event->timezone,
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'map_url' => $event->map_url,
            'cover_image' => $event->cover_image,
            'status' => $event->status,
            'published_at' => $event->published_at?->toISOString(),
            'counts' => [
                'guests' => $event->guests_count ?? $event->guests()->count(),
                'check_ins' => $event->check_ins_count ?? $event->checkIns()->count(),
                'photos' => $event->photos_count ?? $event->photos()->count(),
            ],
            'created_at' => $event->created_at?->toISOString(),
            'updated_at' => $event->updated_at?->toISOString(),
        ];
    }
}

if (! function_exists('adminOwnsEvent')) {
    function adminOwnsEvent(Request $request, Event $event): bool
    {
        return $event->owner_id === $request->user()->id;
    }
}

if (! function_exists('eventForbiddenResponse')) {
    function eventForbiddenResponse()
    {
        return response()->json([
            'code' => 'EVENT_FORBIDDEN',
            'message' => 'Admin tidak memiliki akses ke event ini.',
        ], 403);
    }
}

if (! function_exists('guestValidationRules')) {
    function guestValidationRules(Event $event, ?Guest $guest = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $uniqueGuestCode = Rule::unique('guests', 'guest_code')
            ->where(fn ($query) => $query->where('event_id', $event->id));

        if ($guest) {
            $uniqueGuestCode->ignore($guest->id);
        }

        return [
            'guest_code' => ['nullable', 'string', 'max:40', $uniqueGuestCode],
            'name' => [$required, 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'regex:/^\+?(?:62|0)[1-9][0-9]{7,13}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'category' => ['sometimes', 'string', 'max:80'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'table_number' => ['nullable', 'string', 'max:40'],
            'rsvp_status' => ['sometimes', 'string', Rule::in(['PENDING', 'ATTENDING', 'DECLINED'])],
            'invitation_status' => ['sometimes', 'string', Rule::in(['DRAFT', 'SENT', 'OPENED'])],
            'attendance_status' => ['sometimes', 'string', Rule::in(['NOT_CHECKED_IN', 'CHECKED_IN'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

if (! function_exists('guestPayload')) {
    function guestPayload(Guest $guest): array
    {
        $guest->loadMissing(['invitation', 'checkIn.receiver', 'qrTokens']);

        $activeQrToken = $guest->qrTokens
            ->where('status', 'ACTIVE')
            ->sortByDesc('id')
            ->first();

        $latestQrToken = $guest->qrTokens
            ->sortByDesc('id')
            ->first();

        return [
            'id' => $guest->id,
            'event_id' => $guest->event_id,
            'guest_code' => $guest->guest_code,
            'name' => $guest->name,
            'phone' => $guest->phone,
            'email' => $guest->email,
            'category' => $guest->category,
            'group_name' => $guest->group_name,
            'guest_count' => $guest->guest_count,
            'table_number' => $guest->table_number,
            'rsvp_status' => $guest->rsvp_status,
            'invitation_status' => $guest->invitation_status,
            'attendance_status' => $guest->attendance_status,
            'notes' => $guest->notes,
            'invitation' => [
                'status' => $guest->invitation?->status,
                'token' => $guest->invitation?->token,
                'opened_at' => $guest->invitation?->opened_at?->toISOString(),
            ],
            'qr' => [
                'status' => $activeQrToken?->status ?? $latestQrToken?->status,
                'token' => $activeQrToken?->token,
                'created_at' => $activeQrToken?->created_at?->toISOString() ?? $latestQrToken?->created_at?->toISOString(),
            ],
            'attendance' => [
                'status' => $guest->attendance_status,
                'checked_in_at' => $guest->checkIn?->checked_in_at?->toISOString(),
                'method' => $guest->checkIn?->method,
                'receiver_name' => $guest->checkIn?->receiver?->name,
                'actual_guest_count' => $guest->checkIn?->actual_guest_count,
            ],
            'photos_count' => $guest->photos_count ?? $guest->photos()->count(),
            'created_at' => $guest->created_at?->toISOString(),
            'updated_at' => $guest->updated_at?->toISOString(),
        ];
    }
}

if (! function_exists('nextGuestCode')) {
    function nextGuestCode(Event $event): string
    {
        $maxId = $event->guests()->max('id') ?? 0;

        return 'GUEST-'.str_pad((string) ($maxId + 1), 3, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('guestBelongsToEvent')) {
    function guestBelongsToEvent(Guest $guest, Event $event): bool
    {
        return $guest->event_id === $event->id;
    }
}

if (! function_exists('guestNotFoundResponse')) {
    function guestNotFoundResponse()
    {
        return response()->json([
            'code' => 'GUEST_NOT_FOUND',
            'message' => 'Data tamu tidak ditemukan.',
        ], 404);
    }
}

if (! function_exists('parseGuestCsv')) {
    function parseGuestCsv(string $contents): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($contents))));
        if ($lines === []) {
            return [];
        }

        $headers = array_map(
            fn (string $header) => trim(strtolower($header)),
            str_getcsv(array_shift($lines)),
        );
        $allowed = ['guest_code', 'name', 'phone', 'email', 'category', 'group_name', 'guest_count', 'table_number', 'notes'];

        return collect($lines)
            ->map(function (string $line) use ($headers, $allowed) {
                $values = str_getcsv($line);
                $row = [];

                foreach ($headers as $index => $header) {
                    if (! in_array($header, $allowed, true)) {
                        continue;
                    }

                    $value = trim((string) ($values[$index] ?? ''));
                    if ($value !== '') {
                        $row[$header] = $header === 'guest_count' ? (int) $value : $value;
                    }
                }

                return $row;
            })
            ->filter(fn (array $row) => isset($row['name']))
            ->values()
            ->all();
    }
}

if (! function_exists('csvLine')) {
    function csvLine(array $values): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $values);
        rewind($handle);
        $line = rtrim((string) stream_get_contents($handle), "\n");
        fclose($handle);

        return $line;
    }
}

if (! function_exists('attendanceFilterRules')) {
    function attendanceFilterRules(Request $request): array
    {
        return $request->validate([
            'attendance_status' => ['nullable', 'string', Rule::in(['NOT_CHECKED_IN', 'CHECKED_IN'])],
            'rsvp_status' => ['nullable', 'string', Rule::in(['PENDING', 'ATTENDING', 'DECLINED'])],
            'category' => ['nullable', 'string', 'max:80'],
            'method' => ['nullable', 'string', Rule::in(['QR', 'MANUAL'])],
            'sort' => ['nullable', 'string', Rule::in(['check_in_time', 'guest_name'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);
    }
}

if (! function_exists('attendanceRecords')) {
    function attendanceRecords(Event $event, array $filters)
    {
        $query = $event->guests()
            ->with(['checkIn.receiver'])
            ->orderBy('name');

        if (! empty($filters['attendance_status'])) {
            $query->where('attendance_status', $filters['attendance_status']);
        }

        if (! empty($filters['rsvp_status'])) {
            $query->where('rsvp_status', $filters['rsvp_status']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['method'])) {
            $query->whereHas('checkIn', fn ($checkInQuery) => $checkInQuery->where('method', $filters['method']));
        }

        $records = $query->get()->map(fn (Guest $guest) => attendancePayload($guest));
        $direction = $filters['direction'] ?? 'asc';
        $sort = $filters['sort'] ?? 'guest_name';

        return $records->sortBy(
            $sort === 'check_in_time' ? 'checked_in_at' : 'name',
            SORT_REGULAR,
            $direction === 'desc',
        );
    }
}

if (! function_exists('attendancePayload')) {
    function attendancePayload(Guest $guest): array
    {
        $guest->loadMissing('checkIn.receiver');

        return [
            'guest_id' => $guest->id,
            'guest_code' => $guest->guest_code,
            'name' => $guest->name,
            'category' => $guest->category,
            'rsvp_status' => $guest->rsvp_status,
            'attendance_status' => $guest->attendance_status,
            'guest_count' => $guest->guest_count,
            'actual_guest_count' => $guest->checkIn?->actual_guest_count,
            'method' => $guest->checkIn?->method,
            'checked_in_at' => $guest->checkIn?->checked_in_at?->toISOString(),
            'checked_in_time' => $guest->checkIn?->checked_in_at?->format('H:i'),
            'receiver_name' => $guest->checkIn?->receiver?->name,
        ];
    }
}

if (! function_exists('guestBookPayload')) {
    function guestBookPayload(CheckIn $checkIn): array
    {
        $checkIn->loadMissing(['guest', 'receiver']);

        return [
            'check_in_id' => $checkIn->id,
            'guest_id' => $checkIn->guest_id,
            'guest_code' => $checkIn->guest?->guest_code,
            'guest_name' => $checkIn->guest?->name,
            'category' => $checkIn->guest?->category,
            'guest_count' => $checkIn->guest?->guest_count,
            'actual_guest_count' => $checkIn->actual_guest_count,
            'method' => $checkIn->method,
            'checked_in_at' => $checkIn->checked_in_at?->toISOString(),
            'checked_in_time' => $checkIn->checked_in_at?->format('H:i'),
            'receiver_name' => $checkIn->receiver?->name,
        ];
    }
}

if (! function_exists('attendanceSummary')) {
    function attendanceSummary(Event $event): array
    {
        $totalGuests = $event->guests()->sum('guest_count');
        $checkedIn = $event->checkIns()->sum('actual_guest_count');

        return [
            'total_guests' => $totalGuests,
            'checked_in' => $checkedIn,
            'not_checked_in' => max($totalGuests - $checkedIn, 0),
            'attendance_rate' => $totalGuests > 0 ? round(($checkedIn / $totalGuests) * 100, 1) : 0,
        ];
    }
}

if (! function_exists('invitationPayload')) {
    function invitationPayload(Invitation $invitation): array
    {
        $invitation->loadMissing('guest');

        return [
            'id' => $invitation->id,
            'event_id' => $invitation->event_id,
            'guest_id' => $invitation->guest_id,
            'guest' => [
                'guest_code' => $invitation->guest?->guest_code,
                'name' => $invitation->guest?->name,
                'invitation_status' => $invitation->guest?->invitation_status,
                'rsvp_status' => $invitation->guest?->rsvp_status,
            ],
            'token' => $invitation->token,
            'url' => invitationPublicUrl($invitation),
            'status' => $invitation->status,
            'opened_at' => $invitation->opened_at?->toISOString(),
            'published_at' => $invitation->published_at?->toISOString(),
        ];
    }
}

if (! function_exists('qrPayload')) {
    function qrPayload(QRToken $qrToken): array
    {
        $qrToken->loadMissing('guest');

        return [
            'id' => $qrToken->id,
            'event_id' => $qrToken->event_id,
            'guest_id' => $qrToken->guest_id,
            'guest' => [
                'guest_code' => $qrToken->guest?->guest_code,
                'name' => $qrToken->guest?->name,
                'attendance_status' => $qrToken->guest?->attendance_status,
            ],
            'token' => $qrToken->token,
            'payload_url' => qrPublicUrl($qrToken),
            'status' => $qrToken->status,
            'expires_at' => $qrToken->expires_at?->toISOString(),
            'revoked_at' => $qrToken->revoked_at?->toISOString(),
            'created_at' => $qrToken->created_at?->toISOString(),
        ];
    }
}

if (! function_exists('uniqueInvitationToken')) {
    function uniqueInvitationToken(): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (Invitation::where('token', $token)->exists());

        return $token;
    }
}

if (! function_exists('uniqueQrToken')) {
    function uniqueQrToken(): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (QRToken::where('token', $token)->exists());

        return $token;
    }
}

if (! function_exists('invitationPublicUrl')) {
    function invitationPublicUrl(Invitation $invitation): string
    {
        return rtrim((string) env('GUESTORY_WEB_URL', config('app.url')), '/').'/invite/'.$invitation->token;
    }
}

if (! function_exists('qrPublicUrl')) {
    function qrPublicUrl(QRToken $qrToken): string
    {
        return rtrim((string) env('GUESTORY_API_URL', config('app.url')), '/').'/api/g/'.$qrToken->token;
    }
}

if (! function_exists('findPublicInvitation')) {
    function findPublicInvitation(string $token)
    {
        $invitation = Invitation::query()
            ->with([
                'event:id,name,type,date,start_time,end_time,timezone,venue_name,venue_address,map_url,status',
                'guest:id,event_id,name,guest_count,rsvp_status,attendance_status,invitation_status',
            ])
            ->where('token', $token)
            ->first();

        if (! $invitation || ! $invitation->guest || ! $invitation->event) {
            return response()->json([
                'code' => 'INVITATION_NOT_FOUND',
                'message' => 'Undangan tidak ditemukan.',
            ], 404);
        }

        if ($invitation->event->status !== 'Published') {
            return response()->json([
                'code' => 'INVITATION_NOT_AVAILABLE',
                'message' => 'Undangan belum tersedia.',
            ], 409);
        }

        return $invitation;
    }
}

if (! function_exists('photoPayload')) {
    function photoPayload(Photo $photo): array
    {
        return [
            'id' => $photo->id,
            'event_id' => $photo->event_id,
            'guest_id' => $photo->guest_id,
            'guest' => $photo->guest ? [
                'id' => $photo->guest->id,
                'guest_code' => $photo->guest->guest_code,
                'name' => $photo->guest->name,
                'category' => $photo->guest->category,
            ] : null,
            'guest_name' => $photo->guest?->name,
            'file_url' => $photo->file_url,
            'status' => $photo->status,
            'uploaded_at' => $photo->uploaded_at?->toISOString(),
        ];
    }
}

if (! function_exists('photoSummary')) {
    function photoSummary(Event $event): array
    {
        return [
            'total' => $event->photos()->count(),
            'pending' => $event->photos()->where('status', 'PENDING')->count(),
            'approved' => $event->photos()->where('status', 'APPROVED')->count(),
            'rejected' => $event->photos()->where('status', 'REJECTED')->count(),
        ];
    }
}

if (! function_exists('photoBelongsToEvent')) {
    function photoBelongsToEvent(Photo $photo, Event $event): bool
    {
        return (int) $photo->event_id === (int) $event->id;
    }
}

if (! function_exists('photoNotFoundResponse')) {
    function photoNotFoundResponse()
    {
        return response()->json([
            'code' => 'PHOTO_NOT_FOUND',
            'message' => 'Foto tidak ditemukan.',
        ], 404);
    }
}

if (! function_exists('sendInvitationWhatsApp')) {
    function sendInvitationWhatsApp(Event $event, Guest $guest, WapiClient $wapi): WhatsAppMessage
    {
        $invitation = Invitation::updateOrCreate(
            ['event_id' => $event->id, 'guest_id' => $guest->id],
            [
                'token' => $guest->invitation?->token ?? uniqueInvitationToken(),
                'status' => 'PUBLISHED',
                'published_at' => now(),
            ],
        );

        $guest->forceFill(['invitation_status' => 'SENT'])->save();

        return sendWhatsAppMessage($event, $guest, invitationWhatsAppBody($event, $guest, $invitation), $wapi, 'INVITATION', $invitation);
    }
}

if (! function_exists('sendWhatsAppMessage')) {
    function sendWhatsAppMessage(Event $event, Guest $guest, string $body, WapiClient $wapi, string $messageType = 'INVITATION', ?Invitation $invitation = null): WhatsAppMessage
    {
        if (blank($guest->phone)) {
            return WhatsAppMessage::create([
                'event_id' => $event->id,
                'guest_id' => $guest->id,
                'invitation_id' => $invitation?->id,
                'recipient' => '',
                'message_type' => $messageType,
                'body' => $body,
                'status' => 'FAILED',
                'provider' => 'WAPI',
                'error_message' => 'GUEST_PHONE_EMPTY',
            ]);
        }

        $result = $wapi->sendText($guest->phone, $body);

        return WhatsAppMessage::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'invitation_id' => $invitation?->id,
            'recipient' => normalizeWhatsAppRecipient($guest->phone),
            'message_type' => $messageType,
            'body' => $body,
            'status' => $result['status'],
            'provider' => 'WAPI',
            'provider_message_id' => $result['provider_message_id'],
            'error_message' => $result['error_message'],
            'sent_at' => in_array($result['status'], ['QUEUED', 'SENT', 'SKIPPED'], true) ? now() : null,
        ]);
    }
}

if (! function_exists('invitationWhatsAppBody')) {
    function invitationWhatsAppBody(Event $event, Guest $guest, Invitation $invitation): string
    {
        return implode("\n", [
            "Dear {$guest->name},",
            '',
            "Anda diundang ke {$event->name}.",
            'Buka undangan personal Anda di:',
            invitationPublicUrl($invitation),
            '',
            'Tunjukkan QR pribadi dari link tersebut saat tiba di venue.',
            '',
            'Guestory - Every Guest Has a Story',
        ]);
    }
}

if (! function_exists('invitationPublicUrl')) {
    function invitationPublicUrl(Invitation $invitation): string
    {
        $baseUrl = rtrim((string) env('GUESTORY_WEB_URL', config('app.url')), '/');

        return "{$baseUrl}/invite/{$invitation->token}";
    }
}

if (! function_exists('normalizeWhatsAppRecipient')) {
    function normalizeWhatsAppRecipient(string $recipient): string
    {
        $digits = preg_replace('/\D+/', '', $recipient) ?: '';
        if (str_starts_with($digits, '0')) {
            $digits = '62'.ltrim($digits, '0');
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }
}

if (! function_exists('whatsAppMessagePayload')) {
    function whatsAppMessagePayload(WhatsAppMessage $message): array
    {
        return [
            'id' => $message->id,
            'event_id' => $message->event_id,
            'guest_id' => $message->guest_id,
            'invitation_id' => $message->invitation_id,
            'recipient' => $message->recipient,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'status' => $message->status,
            'provider' => $message->provider,
            'provider_message_id' => $message->provider_message_id,
            'error_message' => $message->error_message,
            'sent_at' => $message->sent_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
            'guest' => $message->guest ? [
                'id' => $message->guest->id,
                'guest_code' => $message->guest->guest_code,
                'name' => $message->guest->name,
                'phone' => $message->guest->phone,
            ] : null,
        ];
    }
}

if (! function_exists('whatsAppSummary')) {
    function whatsAppSummary(Event $event): array
    {
        return [
            'total' => $event->whatsAppMessages()->count(),
            'queued' => $event->whatsAppMessages()->where('status', 'QUEUED')->count(),
            'sent' => $event->whatsAppMessages()->where('status', 'SENT')->count(),
            'failed' => $event->whatsAppMessages()->where('status', 'FAILED')->count(),
            'skipped' => $event->whatsAppMessages()->where('status', 'SKIPPED')->count(),
        ];
    }
}

if (! function_exists('validateQrToken')) {
    function validateQrToken(?QRToken $qrToken, ?int $receiverId = null)
    {
        if (! $qrToken) {
            return response()->json(['code' => 'INVALID_QR', 'message' => 'QR Code tidak valid.'], 404);
        }

        if ($qrToken->status === 'REVOKED') {
            return response()->json(['code' => 'QR_REVOKED', 'message' => 'QR Code sudah tidak aktif.'], 409);
        }

        if ($qrToken->expires_at && $qrToken->expires_at->isPast()) {
            return response()->json(['code' => 'QR_EXPIRED', 'message' => 'QR Code sudah kedaluwarsa.'], 409);
        }

        if (! $qrToken->event || $qrToken->event->status !== 'Published') {
            return response()->json(['code' => 'INVALID_EVENT', 'message' => 'Event tidak valid.'], 409);
        }

        if (! $qrToken->guest) {
            return response()->json(['code' => 'GUEST_NOT_FOUND', 'message' => 'Data tamu tidak ditemukan.'], 404);
        }

        if ($receiverId && ! receiverCanAccessEvent($receiverId, $qrToken->event_id)) {
            return response()->json(['code' => 'RECEIVER_EVENT_FORBIDDEN', 'message' => 'Receiver tidak memiliki akses ke event ini.'], 403);
        }

        if ($qrToken->guest->checkIn()->exists()) {
            return duplicateCheckInResponse($qrToken->guest);
        }

        return response()->json([
            'code' => 'CHECK_IN_ALLOWED',
            'message' => 'Check-in dapat dilakukan.',
            'guest' => [
                'id' => $qrToken->guest->id,
                'name' => $qrToken->guest->name,
                'category' => $qrToken->guest->category,
                'guest_count' => $qrToken->guest->guest_count,
                'attendance_status' => $qrToken->guest->attendance_status,
            ],
            'event' => [
                'id' => $qrToken->event->id,
                'name' => $qrToken->event->name,
            ],
        ]);
    }
}

if (! function_exists('checkInSuccessPayload')) {
    function checkInSuccessPayload(CheckIn $checkIn): array
    {
        $checkIn->loadMissing('guest:id,name');

        return [
            'code' => 'CHECK_IN_SUCCESS',
            'message' => 'Check-in berhasil.',
            'method' => $checkIn->method,
            'guest' => [
                'id' => $checkIn->guest_id,
                'name' => $checkIn->guest?->name,
                'actual_guest_count' => $checkIn->actual_guest_count,
            ],
            'checked_in_at' => $checkIn->checked_in_at?->format('H:i'),
        ];
    }
}

if (! function_exists('checkInPayload')) {
    function checkInPayload(CheckIn $checkIn): array
    {
        return [
            'id' => $checkIn->id,
            'method' => $checkIn->method,
            'actual_guest_count' => $checkIn->actual_guest_count,
            'checked_in_at' => $checkIn->checked_in_at?->toISOString(),
            'checked_in_time' => $checkIn->checked_in_at?->format('H:i'),
            'guest' => [
                'id' => $checkIn->guest_id,
                'name' => $checkIn->guest?->name,
                'category' => $checkIn->guest?->category,
            ],
            'receiver' => [
                'id' => $checkIn->receiver_id,
                'name' => $checkIn->receiver?->name,
            ],
        ];
    }
}

if (! function_exists('receiverEventPayload')) {
    function receiverEventPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'type' => $event->type,
            'date' => $event->date?->toDateString(),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'timezone' => $event->timezone,
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'status' => $event->status,
            'guest_count' => $event->guests_count ?? $event->guests()->count(),
            'checked_in_count' => $event->check_ins_count ?? $event->checkIns()->count(),
        ];
    }
}

if (! function_exists('receiverGuestPayload')) {
    function receiverGuestPayload(Guest $guest): array
    {
        $guest->loadMissing('checkIn');

        return [
            'id' => $guest->id,
            'guest_code' => $guest->guest_code,
            'name' => $guest->name,
            'category' => $guest->category,
            'group_name' => $guest->group_name,
            'guest_count' => $guest->guest_count,
            'table_number' => $guest->table_number,
            'rsvp_status' => $guest->rsvp_status,
            'attendance_status' => $guest->attendance_status,
            'check_in' => $guest->checkIn ? [
                'method' => $guest->checkIn->method,
                'actual_guest_count' => $guest->checkIn->actual_guest_count,
                'checked_in_at' => $guest->checkIn->checked_in_at?->toISOString(),
                'checked_in_time' => $guest->checkIn->checked_in_at?->format('H:i'),
            ] : null,
        ];
    }
}

if (! function_exists('duplicateCheckInResponse')) {
    function duplicateCheckInResponse(Guest $guest)
    {
        $checkIn = $guest->checkIn()->latest('checked_in_at')->first();

        return response()->json([
            'code' => 'ALREADY_CHECKED_IN',
            'message' => 'Tamu sudah melakukan check-in.',
            'guest' => [
                'id' => $guest->id,
                'name' => $guest->name,
                'checked_in_at' => $checkIn?->checked_in_at?->format('H:i'),
            ],
        ], 409);
    }
}

if (! function_exists('quotaExceededResponse')) {
    function quotaExceededResponse(string $code, string $unit, array $usage)
    {
        return response()->json([
            'code' => $code,
            'message' => "Quota {$unit} event terlampaui.",
            'current' => $usage['current'],
            'limit' => $usage['limit'],
            'requested' => $usage['requested'],
            'semantics' => $unit === 'guest records' ? 'Guest quota counts guest records, not guest_count headcount.' : 'Photo quota counts all event photo records.',
        ], 422);
    }
}

if (! function_exists('receiverEventOrForbidden')) {
    function receiverEventOrForbidden(Request $request, Event $event)
    {
        if (! receiverCanAccessEvent($request->user()->id, $event->id)) {
            return response()->json(['code' => 'RECEIVER_EVENT_FORBIDDEN', 'message' => 'Receiver tidak memiliki akses ke event ini.'], 403);
        }

        if ($event->status !== 'Published') {
            return response()->json(['code' => 'INVALID_EVENT', 'message' => 'Event tidak valid.'], 409);
        }

        return null;
    }
}

if (! function_exists('receiverCanAccessEvent')) {
    function receiverCanAccessEvent(int $receiverId, int $eventId): bool
    {
        return User::query()->find($receiverId)?->canReceiveForEvent($eventId) ?? false;
    }
}
