<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['shipping_company_id', 'city_id', 'price', 'cost', 'free_over', 'eta_min_days', 'eta_max_days', 'supports_cod'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'cost' => 'decimal:2', 'free_over' => 'decimal:2', 'supports_cod' => 'bool'];
    }

    public function company()
    {
        return $this->belongsTo(ShippingCompany::class, 'shipping_company_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class)->withTrashed();
    }

    public function etaLabel(): string
    {
        if (! $this->eta_min_days && ! $this->eta_max_days) {
            return '—';
        }

        return $this->eta_max_days && $this->eta_max_days != $this->eta_min_days
            ? ($this->eta_min_days ?: 1).'-'.$this->eta_max_days
            : (string) ($this->eta_min_days ?: $this->eta_max_days);
    }
}
