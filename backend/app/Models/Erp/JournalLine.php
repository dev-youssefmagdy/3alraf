<?php

namespace App\Models\Erp;

use App\Models\Branch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class JournalLine extends Model
{
    use BelongsToTenant;

    protected $table = 'erp_journal_lines';

    protected $fillable = ['entry_id', 'account_id', 'branch_id', 'debit', 'credit', 'original_amount', 'original_currency', 'party_type', 'party_id', 'memo'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:4', 'credit' => 'decimal:4', 'original_amount' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        $guard = function (self $l) {
            if (in_array($l->entry()->withoutGlobalScopes()->value('status'), ['posted', 'reversed'], true)) {
                throw new LogicException('Lines of a posted journal entry are immutable.');
            }
        };
        static::creating($guard); // no lines can be appended to a posted entry either
        static::updating($guard);
        static::deleting($guard);
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'entry_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }
}
