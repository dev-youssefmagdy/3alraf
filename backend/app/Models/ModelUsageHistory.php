<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelUsageHistory extends Model
{
    protected $table = 'model_usage_history';

    protected $fillable = ['virtual_model_id', 'product_id', 'generation_id', 'usage', 'campaign', 'created_by'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function virtualModel(): BelongsTo
    {
        return $this->belongsTo(VirtualModel::class);
    }
}
