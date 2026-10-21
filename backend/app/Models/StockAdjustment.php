<?php

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use Audited, BelongsToTenant;

    protected $fillable = ['number', 'branch_id', 'type', 'reason', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['type' => AdjustmentType::class, 'reason' => AdjustmentReason::class];
    }

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }
}
