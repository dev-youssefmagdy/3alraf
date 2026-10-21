<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Immutable ledger row (append only). */
class StockMovement extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['branch_id', 'product_id', 'product_variant_id', 'type', 'quantity', 'unit_cost', 'balance_after', 'avg_cost_after',
        'reference_type', 'reference_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['type' => StockMovementType::class, 'quantity' => 'int', 'balance_after' => 'int', 'unit_cost' => 'decimal:2', 'avg_cost_after' => 'decimal:2'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
