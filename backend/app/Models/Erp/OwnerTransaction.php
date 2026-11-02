<?php

namespace App\Models\Erp;

use App\Models\Branch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OwnerTransaction extends Model
{
    use BelongsToTenant;

    protected $table = 'erp_owner_transactions';

    public const TYPES = ['contribution', 'withdrawal', 'distribution'];

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
