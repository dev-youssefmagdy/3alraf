<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmbassadorReward extends Model
{

    protected $table = 'ambassador_rewards';

    protected $fillable = ['ambassador_id', 'affiliate_id', 'order_id', 'amount', 'status', 'reason', 'valid_at', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'valid_at' => 'datetime', 'paid_at' => 'datetime'];
    }
}
