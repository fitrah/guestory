<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventReceiver extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
