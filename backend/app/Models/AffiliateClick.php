<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AffiliateClick extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['affiliate_id', 'product_id', 'ip', 'user_agent'];
}
