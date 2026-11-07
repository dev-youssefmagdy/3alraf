<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Processed provider webhook events (idempotency: unique provider + event_id). */
class PaymentEvent extends Model
{

    protected $fillable = ['provider', 'event_id', 'payment_method_id', 'order_id', 'status', 'outcome'];
}
