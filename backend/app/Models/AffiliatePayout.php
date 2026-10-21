<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AffiliatePayout extends Model
{
    use BelongsToTenant;

    protected $fillable = ['affiliate_id', 'amount', 'status', 'note', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'status' => PayoutStatus::class, 'paid_at' => 'datetime'];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function commissions()
    {
        return $this->hasMany(AffiliateCommission::class, 'payout_id');
    }
}
