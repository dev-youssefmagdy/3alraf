<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;

class AffiliatePayout extends Model
{

    protected $fillable = ['affiliate_id', 'amount', 'commission_amount', 'bonus_amount', 'paid_from', 'status', 'note', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'commission_amount' => 'decimal:2', 'bonus_amount' => 'decimal:2', 'status' => PayoutStatus::class, 'paid_at' => 'datetime'];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function commissions()
    {
        return $this->hasMany(AffiliateCommission::class, 'payout_id');
    }

    public function bonuses()
    {
        return $this->hasMany(AffiliateBonus::class, 'payout_id');
    }
}
