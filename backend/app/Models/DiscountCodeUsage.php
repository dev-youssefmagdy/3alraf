<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DiscountCodeUsage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['discount_code_id', 'order_id', 'customer_id', 'amount'];
}
