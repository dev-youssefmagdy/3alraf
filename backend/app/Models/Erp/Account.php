<?php

namespace App\Models\Erp;

use App\Models\Branch;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasTranslations;

    protected $table = 'erp_accounts';

    protected array $translatable = ['name'];

    protected $fillable = ['code', 'name', 'description', 'type', 'subtype', 'parent_id', 'normal_balance', 'branch_id', 'currency',
        'opening_balance', 'role_key', 'is_group', 'is_system', 'is_active', 'archived_at'];

    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:4', 'current_balance' => 'decimal:4', 'is_group' => 'bool', 'is_system' => 'bool', 'is_active' => 'bool', 'archived_at' => 'datetime'];
    }

    public static function normalBalanceFor(string $type): string
    {
        return in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit';
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    public function hasPostedActivity(): bool
    {
        return $this->lines()->whereHas('entry', fn ($q) => $q->whereIn('status', ['posted', 'reversed']))->exists();
    }
}
