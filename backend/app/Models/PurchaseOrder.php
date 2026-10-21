<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = ['number', 'supplier_id', 'branch_id', 'status', 'items_total', 'shipping_cost', 'other_cost', 'total_cost', 'ordered_at', 'received_at', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['status' => PurchaseStatus::class, 'ordered_at' => 'date', 'received_at' => 'datetime',
            'items_total' => 'decimal:2', 'shipping_cost' => 'decimal:2', 'other_cost' => 'decimal:2', 'total_cost' => 'decimal:2'];
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseStatus::DRAFT;
    }
}
