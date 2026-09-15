<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventBilling extends Model
{
    protected $fillable = [
        'event_id', 'plan_code', 'status', 'order_id', 'provider_payment_id',
        'amount', 'currency', 'entitlements', 'paid_at', 'activated_at',
        'expires_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'entitlements' => 'array',
            'paid_at' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
