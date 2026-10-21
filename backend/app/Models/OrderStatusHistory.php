<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'from_status', 'to_status', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class, 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(TenantAdmin::class, 'user_id')->withoutGlobalScopes();
    }
}
