<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QRToken extends Model
{
    protected $table = 'qr_tokens';

    protected $fillable = [
        'event_id',
        'guest_id',
        'token',
        'status',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function checkIn()
    {
        return $this->hasOne(CheckIn::class);
    }
}
