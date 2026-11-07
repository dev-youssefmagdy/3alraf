<?php

namespace App\Models;

use App\Enums\ProductListType;
use Illuminate\Database\Eloquent\Model;

class ProductListItem extends Model
{

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
