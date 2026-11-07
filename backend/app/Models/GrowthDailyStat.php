<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrowthDailyStat extends Model
{

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['stat_date' => 'date'];
    }
}
