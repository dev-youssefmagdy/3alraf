<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_id', 'product_id'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
