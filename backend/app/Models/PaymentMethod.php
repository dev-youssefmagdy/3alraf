<?php

namespace App\Models;

use App\Enums\FeeBearer;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentProvider;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use BelongsToTenant, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'instructions'];

    protected $fillable = ['type', 'provider', 'name', 'instructions', 'account_number', 'requires_proof', 'fee_percent', 'fee_fixed',
        'fee_bearer', 'credentials', 'is_sandbox', 'is_active', 'sort_order'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class, 'provider' => PaymentProvider::class, 'fee_bearer' => FeeBearer::class,
            'requires_proof' => 'bool', 'is_sandbox' => 'bool', 'is_active' => 'bool',
            'fee_percent' => 'decimal:3', 'fee_fixed' => 'decimal:2',
            'credentials' => 'encrypted:array',
        ];
    }

    /** Fee per CALCULATIONS.md step 4: base × percent/100 + fixed. */
    public function feeFor(string $base): string
    {
        return \App\Support\Money::add(\App\Support\Money::percent($base, (string) $this->fee_percent), (string) $this->fee_fixed);
    }
}
