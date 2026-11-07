<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Daily traffic counter (date, product or 0, channel). No personal data. */
class StorefrontVisit extends Model
{

    protected $fillable = ['visit_date', 'product_id', 'channel', 'visits', 'unique_sessions'];

    protected function casts(): array
    {
        return ['visit_date' => 'date'];
    }
}
