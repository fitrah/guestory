<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccountToken extends Model
{
    protected $fillable = ['user_id', 'purpose', 'token_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function issue(User $user, string $purpose, int $minutes): string
    {
        $plainToken = Str::random(80);
        $user->accountTokens()->where('purpose', $purpose)->whereNull('used_at')->delete();
        $user->accountTokens()->create([
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        return $plainToken;
    }

    public static function valid(string $plainToken, string $purpose): ?self
    {
        return static::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
