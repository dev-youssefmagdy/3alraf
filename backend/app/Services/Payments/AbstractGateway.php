<?php

namespace App\Services\Payments;

use App\Exceptions\GatewayException;
use App\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Shared plumbing for real drivers: credentials (encrypted on the method), sandbox/live base URL, HTTP with timeout. */
abstract class AbstractGateway implements PaymentGatewayInterface
{
    public function __construct(protected PaymentMethod $method) {}

    protected function credential(string $key): string
    {
        return (string) ($this->method->credentials[$key] ?? '');
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('payments.'.$this->key().($this->method->is_sandbox ? '.sandbox' : '.live')), '/');
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout((int) config('payments.timeout', 15));
    }

    /** @throws GatewayException */
    protected function send(callable $call): Response
    {
        try {
            $res = $call($this->http());
        } catch (ConnectionException $e) {
            throw new GatewayException($this->key().': connection failed', 0, $e);
        }
        if (! $res->successful()) {
            throw new GatewayException($this->key().': HTTP '.$res->status());
        }

        return $res;
    }

    /** Amount string -> smallest unit (x100) as int. */
    protected function cents(string $amount): int
    {
        return (int) bcmul(Money::round($amount), '100', 0);
    }

    protected function amountEquals(mixed $given, string $expected): bool
    {
        return is_numeric($given) && Money::cmp(Money::round((string) $given), Money::round($expected)) === 0;
    }

    /** Order numbers get a `-R{n}` suffix when a payment is retried (providers reject duplicate merchant references). */
    protected function stripRetry(?string $ref): ?string
    {
        return $ref === null ? null : preg_replace('/-R\d+$/', '', $ref);
    }

    protected function constantEquals(string $expected, string $given): bool
    {
        return $given !== '' && hash_equals($expected, $given);
    }
}
