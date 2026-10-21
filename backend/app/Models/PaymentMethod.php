<?php

namespace App\Models;

use App\Enums\FeeBearer;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentProvider;
use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use Audited, BelongsToTenant, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'instructions'];

    protected $fillable = ['type', 'provider', 'name', 'instructions', 'account_number', 'requires_proof', 'fee_percent', 'fee_fixed',
        'fee_bearer', 'credentials', 'is_sandbox', 'is_active', 'sort_order'];

    protected $hidden = ['credentials'];

    protected static function booted(): void
    {
        // the default COD method of a tenant can be deactivated but never deleted
        static::deleting(function (PaymentMethod $m) {
            if ($m->type === PaymentMethodType::COD) {
                throw \Illuminate\Validation\ValidationException::withMessages(['record' => __('ops.cod_cannot_delete')]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class, 'provider' => PaymentProvider::class, 'fee_bearer' => FeeBearer::class,
            'requires_proof' => 'bool', 'is_sandbox' => 'bool', 'is_active' => 'bool',
            'fee_percent' => 'decimal:3', 'fee_fixed' => 'decimal:2',
            'credentials' => 'encrypted:array',
        ];
    }

    /** Every tenant has a COD method from day one (deactivatable, not deletable). */
    public static function ensureDefaultCod(int $tenantId): self
    {
        return static::withoutGlobalScopes()->withTrashed()->firstOrCreate(
            ['tenant_id' => $tenantId, 'type' => PaymentMethodType::COD->value],
            ['provider' => PaymentProvider::COD->value, 'name' => ['ar' => 'الدفع عند الاستلام', 'en' => 'Cash on delivery'], 'fee_percent' => 0, 'fee_fixed' => 0,
                'fee_bearer' => FeeBearer::CUSTOMER->value, 'is_active' => true, 'sort_order' => 1],
        );
    }

    /** Offered at checkout: active, and (for online) backed by a real driver. */
    public function scopeAvailable($q)
    {
        return $q->where('is_active', true)->where(function ($w) {
            $w->where('type', '!=', PaymentMethodType::ONLINE->value)
                ->orWhereIn('provider', collect(PaymentProvider::cases())->filter->implemented()->map->value->all());
        });
    }

    /** Fee per CALCULATIONS.md step 4: base × percent/100 + fixed. */
    public function feeFor(string $base): string
    {
        return \App\Support\Money::add(\App\Support\Money::percent($base, (string) $this->fee_percent), (string) $this->fee_fixed);
    }
}
