<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AffiliateCommission extends Model
{
    use BelongsToTenant;

    protected $fillable = ['affiliate_id', 'order_id', 'payout_id', 'base', 'percent', 'amount', 'status', 'approved_at'];

    protected function casts(): array
    {
        return ['base' => 'decimal:2', 'percent' => 'decimal:2', 'amount' => 'decimal:2', 'status' => CommissionStatus::class, 'approved_at' => 'datetime'];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
