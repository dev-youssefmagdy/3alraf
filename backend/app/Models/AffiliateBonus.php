<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Model;

/** Sales bonus milestone #n (docs/CALCULATIONS.md §Affiliate sales bonus): threshold/amount copied from the settings at award time. */
class AffiliateBonus extends Model
{
    protected $fillable = ['affiliate_id', 'payout_id', 'milestone', 'threshold', 'amount', 'sales_at_award', 'status'];

    protected function casts(): array
    {
        return ['milestone' => 'integer', 'threshold' => 'decimal:2', 'amount' => 'decimal:2', 'sales_at_award' => 'decimal:2', 'status' => CommissionStatus::class];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function payout()
    {
        return $this->belongsTo(AffiliatePayout::class, 'payout_id');
    }
}
