<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'whatsapp_number', 'password', 'role', 'status', 'terms_version', 'terms_accepted_at', 'privacy_version', 'privacy_accepted_at', 'activated_at', 'provisioned_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'activated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'owner_id');
    }

    public function receiverAssignments()
    {
        return $this->hasMany(EventReceiver::class);
    }

    public function checkIns()
    {
        return $this->hasMany(CheckIn::class, 'receiver_id');
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(AccessToken::class);
    }

    public function accountTokens(): HasMany
    {
        return $this->hasMany(AccountToken::class);
    }

    public function createAccessToken(string $name = 'web'): array
    {
        $plainToken = Str::random(80);

        $token = $this->accessTokens()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => [$this->role],
        ]);

        return [$plainToken, $token];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['SUPERADMIN', 'EVENT_OWNER'], true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'SUPERADMIN';
    }

    public function isReceiver(): bool
    {
        return $this->role === 'RECEIVER' || $this->receiverAssignments()->where('status', 'ACTIVE')->exists();
    }

    public function ownsEvents(): bool
    {
        return $this->events()->exists();
    }

    public function canManageEvents(): bool
    {
        return $this->isSuperadmin() || $this->role === 'EVENT_OWNER' || $this->ownsEvents();
    }

    public function canReceiveForEvent(int $eventId): bool
    {
        return $this->receiverAssignments()
            ->where('event_id', $eventId)
            ->where('status', 'ACTIVE')
            ->exists();
    }
}
