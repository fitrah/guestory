<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookDelivery extends Model
{
    public $timestamps = false;

    protected $fillable = ['delivery_id', 'order_id', 'event_type', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
