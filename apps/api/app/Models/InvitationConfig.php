<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitationConfig extends Model
{
    protected $fillable = ['event_id', 'theme', 'sections', 'content'];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'content' => 'array',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
