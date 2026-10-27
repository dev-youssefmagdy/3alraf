<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ProductSupplyTerm extends Model
{
    use BelongsToTenant;

    protected $table = 'product_supply_terms';

    protected $fillable = ['product_id', 'supplier_id', 'lead_time_days', 'moq', 'shelf_life_days'];

    protected function casts(): array
    {
        return [];
    }
}
