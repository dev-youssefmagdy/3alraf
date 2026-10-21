<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasTranslations, SoftDeletes;

    protected array $translatable = ['name'];

    protected $fillable = ['name', 'slug', 'logo', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? media_url($this->logo) : null;
    }
}
