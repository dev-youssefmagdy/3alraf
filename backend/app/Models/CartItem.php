<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'bundle_id', 'product_id', 'product_variant_id', 'quantity'];

    public function bundle()
    {
        return $this->belongsTo(Bundle::class)->withTrashed();
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
}
