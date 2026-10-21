<?php

namespace App\Models;

use App\Enums\TextDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    protected $fillable = ['code', 'name', 'native_name', 'direction', 'is_active', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return ['direction' => TextDirection::class, 'is_active' => 'bool', 'is_default' => 'bool'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('languages.active'));
        static::deleted(fn () => Cache::forget('languages.active'));
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** Active languages (cached). When a tenant is resolved, restricted to its locales. */
    public static function activeList()
    {
        // cache raw rows (arrays) - Laravel's cache store does not unserialize Eloquent classes
        $rows = Cache::remember('languages.active', 3600, fn () => static::active()->get()->map->getAttributes()->all());
        $all = static::hydrate($rows);

        return ($t = current_tenant()) ? $all->whereIn('code', $t->locales)->values() : $all;
    }

    public function isRtl(): bool
    {
        return $this->direction === TextDirection::RTL;
    }
}
