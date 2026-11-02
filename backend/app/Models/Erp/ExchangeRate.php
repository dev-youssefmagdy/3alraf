<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Model;

/** Deliberately NOT BelongsToTenant: tenant_id NULL rows are global and the central panel reads all of them. Tenant pages filter explicitly. */
class ExchangeRate extends Model
{
    protected $table = 'erp_exchange_rates';

    protected $fillable = ['tenant_id', 'from_currency', 'to_currency', 'rate', 'effective_date', 'created_by'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:8', 'effective_date' => 'date'];
    }
}
