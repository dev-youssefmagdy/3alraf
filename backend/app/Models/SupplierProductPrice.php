<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SupplierProductPrice extends Model
{
    use BelongsToTenant;

    protected $fillable = ['supplier_id', 'product_id', 'variant_id', 'unit_cost', 'moq', 'lead_time_days', 'valid_from', 'valid_to', 'is_preferred', 'note'];

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2', 'valid_from' => 'date', 'valid_to' => 'date', 'is_preferred' => 'bool'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeActiveOn($q, $date)
    {
        return $q->where(fn ($w) => $w->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($w) => $w->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date));
    }
}
