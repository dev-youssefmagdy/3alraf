<?php

namespace App\Models\Erp;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Posted/reversed entries are immutable: corrections are reversal + replacement (JournalService). */
class JournalEntry extends Model
{

    protected $table = 'erp_journal_entries';

    protected $fillable = ['number', 'status', 'type', 'entry_date', 'period_id', 'branch_id', 'currency', 'exchange_rate', 'source_type', 'source_id',
        'idempotency_key', 'reverses_id', 'reversed_by_id', 'description', 'attachment', 'total', 'created_by', 'posted_by', 'posted_at'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'posted_at' => 'datetime', 'total' => 'decimal:4', 'exchange_rate' => 'decimal:8'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $e) {
            $was = $e->getOriginal('status');
            if ($was === 'posted' && $e->status === 'reversed') {
                // the only legal mutation of a posted entry: marking it reversed
                $illegal = array_diff(array_keys($e->getDirty()), ['status', 'reversed_by_id', 'updated_at']);
                if ($illegal) {
                    throw new LogicException('Posted journal entries are immutable.');
                }

                return;
            }
            if (in_array($was, ['posted', 'reversed'], true)) {
                throw new LogicException('Posted journal entries are immutable.');
            }
        });
        static::deleting(function (self $e) {
            if (in_array($e->status, ['posted', 'reversed'], true)) {
                throw new LogicException('Posted journal entries cannot be deleted; reverse them instead.');
            }
        });
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class, 'entry_id');
    }

    public function period()
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function reverses()
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }
}
