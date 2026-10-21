<?php

namespace App\Models;

use App\Enums\ProductListType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ProductListItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['list_type', 'product_id', 'sort_order'];

    protected function casts(): array
    {
        return ['list_type' => ProductListType::class];
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
