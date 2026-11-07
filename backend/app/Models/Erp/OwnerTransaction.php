<?php

namespace App\Models\Erp;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;

class OwnerTransaction extends Model
{

    protected $table = 'erp_owner_transactions';

    /** All types that may exist in history ("distribution" rows pre-date the controlled workflow and are kept for history only). */
    public const TYPES = ['contribution', 'withdrawal', 'distribution'];

    /** Types an operator may record today. Profit distributions go ONLY through ProfitDistributionService (ERP-005). */
    public const NEW_TYPES = ['contribution', 'withdrawal'];

    protected $fillable = ['owner_id', 'type', 'amount', 'txn_date', 'journal_entry_id', 'branch_id', 'account_role', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'txn_date' => 'date'];
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }
}
