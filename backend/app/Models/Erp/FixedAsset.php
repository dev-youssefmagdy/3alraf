<?php

namespace App\Models\Erp;

use App\Models\Branch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use BelongsToTenant;

    protected $table = 'erp_fixed_assets';

    public const METHODS = ['straight_line', 'declining_balance'];

    protected $fillable = ['name', 'category', 'acquisition_date', 'cost', 'salvage_value', 'useful_life_months', 'method', 'frequency', 'branch_id', 'supplier_id', 'status',
        'accumulated_depreciation', 'last_depreciated_period', 'disposed_at', 'disposal_proceeds', 'disposal_gain_loss', 'registration_entry_id', 'disposal_entry_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['acquisition_date' => 'date', 'disposed_at' => 'date', 'cost' => 'decimal:4', 'salvage_value' => 'decimal:4', 'accumulated_depreciation' => 'decimal:4',
            'disposal_proceeds' => 'decimal:4', 'disposal_gain_loss' => 'decimal:4'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function getBookValueAttribute(): string
    {
        return bcsub((string) $this->cost, (string) $this->accumulated_depreciation, 4);
    }
}
