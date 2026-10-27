<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use BelongsToTenant, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'state'];

    protected $fillable = ['country_id', 'name', 'state', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function shippingRates()
    {
        return $this->hasMany(ShippingRate::class);
    }
}
