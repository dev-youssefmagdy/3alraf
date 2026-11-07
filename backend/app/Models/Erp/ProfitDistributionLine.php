<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Model;

class ProfitDistributionLine extends Model
{

    protected $table = 'erp_profit_distribution_lines';

    protected $fillable = ['distribution_id', 'owner_id', 'percent', 'amount', 'paid', 'paid_at', 'paid_account', 'paid_by', 'payment_entry_id'];

    protected function casts(): array
    {
        return ['percent' => 'decimal:4', 'amount' => 'decimal:4', 'paid' => 'bool', 'paid_at' => 'datetime'];
    }

    public function distribution()
    {
        return $this->belongsTo(ProfitDistribution::class, 'distribution_id');
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    public function paymentEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'payment_entry_id');
    }
}
