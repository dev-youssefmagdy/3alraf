<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_id', 'token', 'packaging_option_id'];

    public function items()
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
