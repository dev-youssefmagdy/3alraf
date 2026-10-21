<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** Unlimited-depth tree: parent_id + materialized `path` ("1/5/9", ids incl. self) + `depth`. */
class Category extends Model
{
    use HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description'];

    protected $fillable = ['parent_id', 'path', 'depth', 'name', 'description', 'slug', 'image', 'icon', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function scopeDescendantsOf($q, self $c)
    {
        return $q->where('path', 'like', $c->path.'/%');
    }

    public function ancestorIds(): array
    {
        return array_map('intval', array_slice(explode('/', $this->path), 0, -1));
    }

    /** Root -> self breadcrumb collection. */
    public function breadcrumbs()
    {
        $ids = array_map('intval', explode('/', $this->path));
        $items = static::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $items[$id] ?? null)->filter()->values();
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
