<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReturnItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['return_value' => 'decimal:2', 'quantity' => 'int'];
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
