<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $fillable = [
        'event_id',
        'guest_id',
        'token',
        'status',
        'opened_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'published_at' => 'datetime',
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
}
