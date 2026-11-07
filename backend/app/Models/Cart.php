<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{

    protected $fillable = ['customer_id', 'token', 'packaging_option_id', 'attribution'];

    protected function casts(): array
    {
        return ['attribution' => 'array'];
    }

    public function items()
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
