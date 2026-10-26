<?php

namespace App\Services\Ai\Drivers;

use App\Exceptions\AiException;
use App\Models\AiProvider;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Support\AiRequest;
use App\Services\Ai\Support\AiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BaseDriver implements AiDriver
{
    public function __construct(protected AiProvider $provider) {}

    abstract protected function defaultBaseUrl(): string;

    abstract protected function headers(): array;

    protected function base(): string
    {
        return rtrim($this->provider->base_url ?: $this->defaultBaseUrl(), '/');
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders($this->headers())->acceptJson()->asJson()
            ->timeout((int) config('ai.timeout', 60))->connectTimeout((int) config('ai.connect_timeout', 10))
            ->retry((int) config('ai.retries', 2), fn (int $attempt, $e) => $this->backoffMs($e), fn ($e) => $e instanceof ConnectionException || ($e->response?->status() >= 500 || $e->response?->status() === 429), throw: false);
    }

    /** Delay before a retry: the provider's Retry-After (capped) on 429, else exponential from 400 ms. */
    private function backoffMs(mixed $e): int
    {
        $cap = (int) config('ai.retry_max_wait', 8) * 1000;
        $after = $e instanceof \Illuminate\Http\Client\RequestException ? (int) $e->response->header('Retry-After') : 0;

        return $after > 0 ? min($after * 1000, $cap) : 800;
    }

    /** Maps vendor failures to a user-safe exception; the vendor body is never exposed. */
    protected function check(Response $r): array
    {
        if ($r->failed()) {
            \Illuminate\Support\Facades\Log::warning($r->status() === 429 ? 'AI provider rate limited' : 'AI provider error', ['provider' => $this->provider->getKey(), 'status' => $r->status(), 'retry_after' => $r->header('Retry-After'), 'body' => $this->redact(mb_substr($r->body(), 0, 500))]);
            throw new AiException(__($r->status() === 429 ? 'ai.errors.throttled' : 'ai.errors.provider'), 'http_'.$r->status());
        }

        return (array) $r->json();
    }

    /** Vendors sometimes echo the offending key ("Incorrect API key provided: sk-..."); never let it reach the log. */
    protected function redact(string $text): string
    {
        $key = (string) $this->provider->api_key;
        if ($key !== '') {
            $text = str_replace($key, '[redacted]', $text);
        }

        return (string) preg_replace('/\b(sk|key|pk|api)[-_][A-Za-z0-9_\-]{8,}/i', '[redacted]', $text);
    }

    protected function call(callable $fn): Response
    {
        try {
            return $fn();
        } catch (ConnectionException $e) {
            throw AiException::provider($e);
        }
    }

    public function stream(AiRequest $request, callable $onDelta): AiResponse
    {
        $res = $this->chat($request);   // v1: single-shot, delivered as one delta (SSE fan-out handled by the chat sprint)
        if ($res->text !== '') {
            $onDelta($res->text);
        }

        return $res;
    }

    public function image(string $model, string $prompt, array $options = []): AiResponse
    {
        throw AiException::unsupported();
    }

    public function video(string $model, string $prompt, array $options = []): AiResponse
    {
        throw AiException::unsupported();
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['chat', 'tools'], true);
    }
}
