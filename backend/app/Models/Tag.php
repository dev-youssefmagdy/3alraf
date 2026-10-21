<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Global (central catalog) product tag, e.g. "new", "eco". Shared by all tenants; storefront shows it on active products only. */
class Tag extends Model
{
    use HasTranslations, SoftDeletes;

    protected array $translatable = ['name'];

    protected $fillable = ['name', 'slug', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
