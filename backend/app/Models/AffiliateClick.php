<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateClick extends Model
{

    public $timestamps = false;

    protected $fillable = ['affiliate_id', 'product_id', 'ip', 'user_agent'];
}
