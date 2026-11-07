<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $table = 'erp_exchange_rates';

    protected $fillable = ['from_currency', 'to_currency', 'rate', 'effective_date', 'created_by'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:8', 'effective_date' => 'date'];
    }
}
