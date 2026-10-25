<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Models\Order;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Layered anti-abuse limiter for OFFLINE (wallet) checkout attempts. All tiers are checked first; only when none is exhausted are all hit
 * (a blocked client cannot extend its own window). Keys use the RESOLVED tenant (never the raw X-Tenant header), the real client IP and
 * NORMALISED phone / e-mail so "0100-123 4567" vs "+20 100 1234567" and "A+x@Gmail.com" vs "a@gmail.com" share one counter.
 */
class OfflineCheckoutThrottle
{
    /** Arabic-Indic and Persian digits -> ASCII (otherwise "\D" would strip them and the phone would dodge every phone tier). */
    public static function asciiDigits(string $s): string
    {
        return strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
    }

    /** Digits only, last 10 (drops +20 / 0 / 00 country prefixes). */
    public static function phoneKey(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', self::asciiDigits((string) $phone));

        return $d === '' ? null : substr($d, -10);
    }

    /** Lower-case, no "+tag", gmail dots removed. */
    public static function emailKey(?string $email): ?string
    {
        $e = mb_strtolower(trim((string) $email));
        if ($e === '' || ! str_contains($e, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $e, 2);
        $local = explode('+', $local, 2)[0];
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    public function attempt(Request $r, ?string $phone, ?string $email, ?int $customerId): void
    {
        $c = config('checkout.offline');
        $t = AppServiceProvider::tenantKey($r);
        $tiers = [
            'ip' => ["checkout-offline:ip:$t:{$r->ip()}", (int) $c['ip_attempts'], (int) $c['ip_window'] * 60],
            'ip-day' => ["checkout-offline:ipd:$t:{$r->ip()}", (int) $c['ip_daily'], 86400],
        ];
        if ($p = self::phoneKey($phone)) {
            $tiers['phone'] = ["checkout-offline:phone:$t:$p", (int) $c['contact_attempts'], (int) $c['contact_window'] * 60];
        }
        if ($e = self::emailKey($email)) {
            $tiers['email'] = ["checkout-offline:email:$t:".sha1($e), (int) $c['contact_attempts'], (int) $c['contact_window'] * 60];
        }

        // hit FIRST (RateLimiter::hit is atomic and never extends a running window), then compare: a burst of parallel requests
        // cannot all slip through a check-then-hit gap.
        $tripped = null;
        foreach ($tiers as $name => [$key, $max, $decay]) {
            if (RateLimiter::hit($key, $decay) > $max && ! $tripped) {
                $tripped = [$name, RateLimiter::availableIn($key)];
            }
        }
        if ($tripped) {
            $this->trip($r, $tripped[0], $tripped[1]);
        }
        if ($this->openOrders($phone, $email, $customerId) >= (int) $c['open_cap']) {
            $this->trip($r, 'open-cap', (int) $c['open_cap_retry']);
        }
    }

    /** Pre-validate (no order placed, but the file is decoded and reuse is revealed): one looser per-IP tier per tenant. */
    public function attemptValidate(Request $r): void
    {
        $c = config('checkout.validate');
        $key = 'checkout-offline:val:'.AppServiceProvider::tenantKey($r).':'.$r->ip();
        if (RateLimiter::hit($key, (int) $c['window'] * 60) > (int) $c['attempts']) {
            $this->trip($r, 'validate', RateLimiter::availableIn($key));
        }
    }

    /** Offline orders of this customer / phone / e-mail still waiting for proof review. */
    public function openOrders(?string $phone, ?string $email, ?int $customerId): int
    {
        $p = self::phoneKey($phone);
        $e = $email ? mb_strtolower(trim($email)) : null;
        if (! $p && ! $e && ! $customerId) {
            return 0;
        }

        return Order::query()->where('proof_status', ProofStatus::PENDING->value)->whereNotNull('payment_proof')
            ->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::RETURNED->value])
            ->where(function ($q) use ($p, $e, $customerId) {
                $customerId && $q->orWhere('customer_id', $customerId);
                $p && $q->orWhereRaw('RIGHT('.$this->digitsSql('customer_phone').', 10) = ?', [$p]);
                $e && $q->orWhereRaw('LOWER(customer_email) = ?', [$e]);
            })->count();
    }

    /** Portable (MySQL 5.7 / MariaDB / 8.x) "digits only" expression: strips the separators people type (no REGEXP_REPLACE, which needs MySQL 8). */
    private function digitsSql(string $column): string
    {
        $sql = $column;
        foreach ([' ', '-', '+', '(', ')', '.', '/'] as $ch) {
            $sql = "REPLACE($sql, '$ch', '')";
        }

        return $sql;
    }

    private function trip(Request $r, string $tier, int $retryAfter): never
    {
        $retryAfter = max(1, $retryAfter);
        Log::warning('Offline checkout throttled', ['tier' => $tier, 'ip' => $r->ip(), 'tenant' => AppServiceProvider::tenantKey($r), 'retry_after' => $retryAfter]);

        throw new ThrottleRequestsException(__('orders.offline_throttled', ['minutes' => (int) ceil($retryAfter / 60)]), null, ['Retry-After' => $retryAfter, 'X-RateLimit-Remaining' => 0]);
    }
}
