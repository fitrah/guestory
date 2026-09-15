<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitationDesignAsset extends Model
{
    protected $fillable = [
        'event_id',
        'file_path',
        'file_url',
        'original_name',
        'mime_type',
        'size_bytes',
        'position',
        'is_cover',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'position' => 'integer',
            'is_cover' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
