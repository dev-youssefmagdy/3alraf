<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use BelongsToTenant, HasTranslations, SoftDeletes;

    protected array $translatable = ['name'];

    protected $fillable = ['name', 'city_id', 'address', 'phone', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['is_default' => 'bool', 'is_active' => 'bool'];
    }

    public function city()
    {
        return $this->belongsTo(City::class)->withTrashed();
    }

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }
}
