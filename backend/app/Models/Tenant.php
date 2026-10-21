<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = ['slug', 'name', 'country', 'currency_code', 'currency_symbol', 'currency_rate',
        'default_locale', 'locales', 'timezone', 'is_active', 'settings'];

    protected function casts(): array
    {
        return ['locales' => 'array', 'settings' => 'encrypted:array', 'is_active' => 'bool', 'currency_rate' => 'decimal:6'];
    }

    public function admins(): HasMany
    {
        return $this->hasMany(TenantAdmin::class);
    }

    public function url(string $path = '/'): string
    {
        return tenant_url($this, $path);
    }

    public function isRtl(?string $locale = null): bool
    {
        $lang = Language::activeList()->firstWhere('code', $locale ?? $this->default_locale);

        return (bool) $lang?->isRtl();
    }
}
