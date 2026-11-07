<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCodeUsage extends Model
{

    protected $fillable = ['discount_code_id', 'order_id', 'customer_id', 'amount'];
}
