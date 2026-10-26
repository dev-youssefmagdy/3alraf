<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use Audited, SoftDeletes;

    /** Encrypted settings may hold gateway/mail secrets: never written to the audit log. */
    protected array $auditExcept = ['settings'];

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

    /** settings.cod_collect_shipping_upfront (default true; PAYMENTS_COD_UPFRONT_DEFAULT only changes the fallback, e.g. legacy test env). */
    public function codCollectsShippingUpfront(): bool
    {
        return (bool) (($this->settings ?? [])['cod_collect_shipping_upfront'] ?? config('payments.cod_upfront_default', true));
    }
}
