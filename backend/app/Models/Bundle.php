<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\FreesUniqueOnDelete;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Fixed-price pack of products (docs/CALCULATIONS.md "Bundles"). Expanded into component order lines at quote/placement. */
class Bundle extends Model
{
    use Audited, BelongsToTenant, FreesUniqueOnDelete, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description'];

    protected array $freedOnDelete = ['slug'];

    protected $fillable = ['name', 'description', 'slug', 'image', 'price', 'is_active', 'starts_at', 'ends_at', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'bool', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'sort_order' => 'int'];
    }

    public function items()
    {
        return $this->hasMany(BundleItem::class)->orderBy('id');
    }

    /** Active flag + schedule window (the storefront/cart/order gate; component availability is checked by BundleResolver). */
    public function scopeLive($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isLive(): bool
    {
        return $this->is_active && (! $this->starts_at || $this->starts_at->lte(now())) && (! $this->ends_at || $this->ends_at->gte(now()));
    }

    public function imageUrl(): ?string
    {
        return media_url($this->image);
    }

    /** Slug without the soft-delete tombstone. */
    public function getSlugAttribute($v): ?string
    {
        return $v === null ? null : preg_replace('/#del\d+$/', '', $v);
    }
}
