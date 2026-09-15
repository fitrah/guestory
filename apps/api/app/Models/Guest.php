<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $fillable = [
        'event_id',
        'guest_code',
        'name',
        'phone',
        'email',
        'category',
        'group_name',
        'guest_count',
        'table_number',
        'rsvp_status',
        'invitation_status',
        'attendance_status',
        'notes',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function invitation()
    {
        return $this->hasOne(Invitation::class);
    }

    public function qrTokens()
    {
        return $this->hasMany(QRToken::class);
    }

    public function activeQrToken()
    {
        return $this->hasOne(QRToken::class)->where('qr_tokens.status', 'ACTIVE')->latestOfMany();
    }

    public function checkIn()
    {
        return $this->hasOne(CheckIn::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function whatsAppMessages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }
}
