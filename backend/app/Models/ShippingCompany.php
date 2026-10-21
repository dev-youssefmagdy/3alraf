<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingCompany extends Model
{
    use BelongsToTenant, HasTranslations, SoftDeletes;

    protected array $translatable = ['name'];

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function rates()
    {
        return $this->hasMany(ShippingRate::class);
    }
}
