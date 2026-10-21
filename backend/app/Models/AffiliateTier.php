<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AffiliateTier extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'min_sales', 'percent'];

    protected function casts(): array
    {
        return ['min_sales' => 'decimal:2', 'percent' => 'decimal:2'];
    }
}
