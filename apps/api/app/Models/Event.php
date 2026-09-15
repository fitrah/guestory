<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'type',
        'description',
        'date',
        'start_time',
        'end_time',
        'timezone',
        'venue_name',
        'venue_address',
        'map_url',
        'cover_image',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function invitationConfig()
    {
        return $this->hasOne(InvitationConfig::class);
    }

    public function qrTokens()
    {
        return $this->hasMany(QRToken::class);
    }

    public function checkIns()
    {
        return $this->hasMany(CheckIn::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function invitationDesignAssets()
    {
        return $this->hasMany(InvitationDesignAsset::class)->orderBy('position');
    }

    public function whatsAppMessages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function receivers()
    {
        return $this->hasMany(EventReceiver::class);
    }

    public function billing()
    {
        return $this->hasOne(EventBilling::class);
    }
}
