<?php

namespace App\Models;

use App\Enums\BannerPlacement;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    use BelongsToTenant, HasTranslations;

    protected array $translatable = ['title'];

    protected $fillable = ['placement', 'title', 'images', 'link_url', 'starts_at', 'ends_at', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['placement' => BannerPlacement::class, 'images' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'bool'];
    }

    public function imageUrl(?string $locale = null): ?string
    {
        $imgs = $this->images ?? [];
        $path = $imgs[$locale ?? app()->getLocale()] ?? $imgs[current_tenant()?->default_locale ?? ''] ?? collect($imgs)->first();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Active flag + schedule window. */
    public function scopeLive($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
