<?php

namespace App\Models\Erp;

use App\Models\Concerns\BelongsToTenant;
use App\Models\TenantAdmin;
use Illuminate\Database\Eloquent\Model;

class ProfitDistribution extends Model
{
    use BelongsToTenant;

    protected $table = 'erp_profit_distributions';

    protected $fillable = ['fiscal_year_id', 'period_start', 'period_end', 'status', 'ledger_profit', 'tax_amount', 'net_profit', 'retained_before', 'reserve_amount', 'distributable_profit',
        'cash_available', 'distributable', 'distributed_amount', 'retained_after', 'acknowledged_open', 'active_key', 'note', 'snapshot', 'created_by', 'approved_by', 'approved_at',
        'posted_by', 'posted_at', 'journal_entry_id', 'transfer_entry_id'];

    protected function casts(): array
    {
        $d = 'decimal:4';

        return ['period_start' => 'date', 'period_end' => 'date', 'ledger_profit' => $d, 'tax_amount' => $d, 'net_profit' => $d, 'retained_before' => $d, 'reserve_amount' => $d,
            'distributable_profit' => $d, 'cash_available' => $d, 'distributable' => $d, 'distributed_amount' => $d, 'retained_after' => $d, 'acknowledged_open' => 'bool',
            'snapshot' => 'array', 'approved_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    public function lines()
    {
        return $this->hasMany(ProfitDistributionLine::class, 'distribution_id')->orderBy('id');
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function creator()
    {
        return $this->belongsTo(TenantAdmin::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(TenantAdmin::class, 'approved_by');
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
