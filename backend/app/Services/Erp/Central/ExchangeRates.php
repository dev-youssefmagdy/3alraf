<?php

namespace App\Services\Erp\Central;

use App\Models\Erp\ExchangeRate;
use Illuminate\Support\Collection;

/** Stored-rate lookup only (never invents a rate). Tenant rows override global ones; the inverse of a stored pair is used when the direct pair is missing. */
class ExchangeRates
{
    private ?Collection $all = null;

    /** Drop the preloaded table (call at the start of a unit of work: controllers/services may be long-lived). */
    public function flush(): void
    {
        $this->all = null;
    }

    private function all(): Collection
    {
        return $this->all ??= ExchangeRate::query()->orderByDesc('effective_date')->orderByDesc('id')->get();
    }

    /** @return ?array{rate:string,date:string,scope:string,inverse:bool} null = no stored rate */
    public function find(string $from, string $to, ?int $tenantId = null, ?string $date = null): ?array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return ['rate' => '1.00000000', 'date' => $date ?? now()->toDateString(), 'scope' => 'same', 'inverse' => false];
        }
        $date ??= now()->toDateString();
        foreach ([[$tenantId, 'tenant'], [null, 'global']] as [$tid, $scope]) {
            if ($scope === 'tenant' && ! $tenantId) {
                continue;
            }
            $pool = $this->all()->filter(fn ($r) => $r->tenant_id === $tid && $r->effective_date->toDateString() <= $date);
            if ($r = $pool->first(fn ($r) => $r->from_currency === $from && $r->to_currency === $to)) {
                return ['rate' => (string) $r->rate, 'date' => $r->effective_date->toDateString(), 'scope' => $scope, 'inverse' => false];
            }
            if ($r = $pool->first(fn ($r) => $r->from_currency === $to && $r->to_currency === $from)) {
                return ['rate' => bcdiv('1', (string) $r->rate, 8), 'date' => $r->effective_date->toDateString(), 'scope' => $scope, 'inverse' => true];
            }
        }

        return null;
    }

    public function convert(string $amount, string $rate): string
    {
        return bcmul($amount, $rate, 4);
    }
}
