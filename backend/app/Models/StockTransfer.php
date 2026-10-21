<?php

namespace App\Models;

use App\Enums\TransferStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['number', 'from_branch_id', 'to_branch_id', 'status', 'note', 'sent_at', 'received_at', 'created_by'];

    protected function casts(): array
    {
        return ['status' => TransferStatus::class, 'sent_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function from()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id')->withTrashed();
    }

    public function to()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id')->withTrashed();
    }
}
