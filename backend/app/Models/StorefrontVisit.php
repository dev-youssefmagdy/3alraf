<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Daily traffic counter (tenant, date, product or 0, channel). No personal data. */
class StorefrontVisit extends Model
{
    use BelongsToTenant;

    protected $fillable = ['visit_date', 'product_id', 'channel', 'visits', 'unique_sessions'];

    protected function casts(): array
    {
        return ['visit_date' => 'date'];
    }
}
