<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OrderReturn extends Model
{
    use Audited, BelongsToTenant;

    protected $fillable = ['number', 'order_id', 'status', 'reason', 'restock', 'branch_id', 'refund_shipping', 'return_value', 'refund_amount', 'return_shipping_cost', 'returned_cogs', 'approved_at', 'refunded_at', 'reason_code'];

    protected function casts(): array
    {
        return ['status' => ReturnStatus::class, 'restock' => 'bool', 'refund_shipping' => 'bool', 'return_value' => 'decimal:2', 'refund_amount' => 'decimal:2',
            'return_shipping_cost' => 'decimal:2', 'returned_cogs' => 'decimal:2', 'approved_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }
}
