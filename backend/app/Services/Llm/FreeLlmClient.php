<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client of FreeLLMAPI (config product_ai), an OpenAI-compatible gateway in front of free LLM tiers (docs/FREELLMAPI_SETUP.md).
 *
 * Automatic model switching: the models of config product_ai.models.{text|vision} are tried in order. On 429 / quota_exceeded, 5xx,
 * a connection error or timeout, 422 no_vision_model, an unknown model (400) or an answer that cannot be used, the next one is tried.
 * A model that answered 429 / 5xx / no_vision_model "cools down" (cache) for Retry-After seconds (default cooldown_seconds), so later
 * requests skip it. LlmUnavailableException (code from the error contract) is thrown only when every model failed.
 * Every answer is untrusted text: callers validate it (chatJson()).
 */
class FreeLlmClient
{
    /** Seconds a whole chat() may take over all models, so the admin request stays under the ~60 s ceiling. */
    private const BUDGET_SECONDS = 55;

    public function configured(): bool
    {
        return trim((string) config('product_ai.base_url')) !== '' && trim((string) config('product_ai.api_key')) !== '';
    }

    /**
     * @param  list<array{role:string,content:string|list<array<string,mixed>>}>  $messages
     * @param  'text'|'vision'  $kind
     * @param  array{json?:bool,max_tokens?:int,temperature?:float,budget?:int}  $options  budget = seconds for all models together (default 55)
     *
     * @throws LlmUnavailableException
     */
    public function chat(array $messages, string $kind = 'text', array $options = []): LlmResponse
    {
        return $this->send($messages, $kind, $options)[0];
    }

    /**
     * Chat with response_format json_object, tolerant parsing (code fences, text around the JSON) and validation. A model whose answer is
     * not valid JSON, or that $validate rejects (returns null), counts as failed and the next model is tried.
     *
     * @template T
     *
     * @param  list<array{role:string,content:string|list<array<string,mixed>>}>  $messages
     * @param  callable(array<mixed>): (T|null)  $validate
     * @param  array{max_tokens?:int,temperature?:float,budget?:int}  $options
     * @return array{0: T, 1: LlmResponse}
     *
     * @throws LlmUnavailableException
     */
    public function chatJson(array $messages, string $kind, callable $validate, array $options = []): array
    {
        [$response, $data] = $this->send($messages, $kind, ['json' => true, ...$options],
            fn (string $content) => is_array($json = self::parseJson($content)) ? $validate($json) : null);

        return [$data, $response];
    }

    /**
     * @return array{0: LlmResponse, 1: mixed}
     *
     * @throws LlmUnavailableException
     */
    private function send(array $messages, string $kind, array $options, ?callable $accept = null): array
    {
        if (! $this->configured()) {
            throw new LlmUnavailableException(LlmUnavailableException::NOT_CONFIGURED);
        }
        $models = $this->models($kind);
        $failures = [];
        $codes = [];
        $retry = [];
        $started = microtime(true);

        foreach ($models as $model) {
            if ($cool = $this->activeCooldown($kind, $model)) {
                $failures[$model] = 'cooling_down';
                $codes[] = $cool['code'];
                $retry[] = max(1, $cool['until'] - time());

                continue;
            }
            $left = (int) ($options['budget'] ?? self::BUDGET_SECONDS) - (microtime(true) - $started);
            if ($left < 3) {
                $failures[$model] = 'no_time_left';
                $codes[] = LlmUnavailableException::TIMEOUT;

                continue;
            }
            $result = $this->attempt($model, $this->payload($messages, $options), $kind, (int) min((int) config('product_ai.timeout', 40), $left));
            if (! $result instanceof LlmResponse) {
                $failures[$model] = $result['reason'];
                $codes[] = $result['code'];
                if (isset($result['retry'])) {
                    $retry[] = $result['retry'];
                }

                continue;
            }
            $data = $accept ? $accept($result->content) : $result->content;
            if ($data === null) {
                $failures[$model] = 'bad_answer';
                $codes[] = 'bad_answer';
                $this->remember($kind, $model, 'failed', 'bad_answer');
                logger()->warning('freellm: unusable answer', ['model' => $model, 'routed_via' => $result->routedVia, 'start' => mb_substr($result->content, 0, 200)]);

                continue;
            }

            return [new LlmResponse($result->content, $model, $result->routedVia, $failures), $data];
        }

        $code = self::summarize($codes);
        logger()->warning('freellm: every model failed', ['kind' => $kind, 'code' => $code, 'failures' => $failures]);

        throw new LlmUnavailableException($code, $code === LlmUnavailableException::GATEWAY_DOWN && $codes !== [] && array_unique($codes) === ['bad_answer'] ? 'bad_answer' : $code,
            $failures, $retry !== [] ? min($retry) : null);
    }

    /** @return list<string> the configured models of a kind, in order */
    public function models(string $kind): array
    {
        $list = array_values(array_filter((array) config("product_ai.models.$kind"), fn ($m) => is_string($m) && trim($m) !== ''));

        return $list !== [] ? $list : ['auto'];
    }

    /**
     * One call to one model, without switching (also used by llm:check).
     *
     * @return LlmResponse|array{code:string,reason:string,retry?:int}
     */
    public function attempt(string $model, array $payload, string $kind = 'text', ?int $timeout = null): LlmResponse|array
    {
        try {
            $res = $this->http($timeout)->post('chat/completions', ['model' => $model, ...$payload]);
        } catch (ConnectionException $e) {
            $timedOut = str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false;
            $this->remember($kind, $model, 'failed', $timedOut ? 'timeout' : 'unreachable');

            return ['code' => $timedOut ? LlmUnavailableException::TIMEOUT : LlmUnavailableException::GATEWAY_DOWN, 'reason' => $timedOut ? 'timeout' : 'unreachable'];
        }

        if ($res->successful()) {
            $content = self::content($res->json('choices.0.message.content'));
            $routed = mb_substr(trim((string) $res->header('X-Routed-Via')), 0, 200) ?: null;
            if (trim($content) === '') {
                $this->remember($kind, $model, 'failed', 'empty_answer');

                return ['code' => LlmUnavailableException::GATEWAY_DOWN, 'reason' => 'empty_answer'];
            }
            $this->remember($kind, $model, 'ok', $routed);
            logger()->info('freellm: answered', ['kind' => $kind, 'model' => $model, 'routed_via' => $routed]);

            return new LlmResponse($content, $model, $routed);
        }

        $status = $res->status();
        $errorCode = (string) ($res->json('error.code') ?? '');
        if ($status === 401 || $status === 403) {
            $this->remember($kind, $model, 'failed', 'key_rejected');

            throw new LlmUnavailableException(LlmUnavailableException::NOT_CONFIGURED, 'key_rejected', [$model => 'key_rejected']);
        }
        if ($status === 429 || $errorCode === 'quota_exceeded') {
            $seconds = $this->retryAfter($res);

            return $this->startCooldown($kind, $model, LlmUnavailableException::QUOTA_EXCEEDED, 'quota_exceeded', $seconds);
        }
        if ($errorCode === 'no_vision_model') {
            return $this->startCooldown($kind, $model, LlmUnavailableException::NO_VISION_MODEL, 'no_vision_model', $this->retryAfter($res));
        }
        if ($status >= 500) {
            return $this->startCooldown($kind, $model, LlmUnavailableException::GATEWAY_DOWN, 'http_'.$status, $this->retryAfter($res));
        }
        // 400 unknown model / profile, other 4xx: this entry of the list is wrong, the next one may work
        $reason = 'http_'.$status.($errorCode !== '' ? ':'.mb_substr($errorCode, 0, 50) : '');
        $this->remember($kind, $model, 'failed', $reason);

        return ['code' => LlmUnavailableException::GATEWAY_DOWN, 'reason' => $reason];
    }

    /** A tiny call to one model (llm:check). @return array{ok:bool,code:?string,reason:?string,routed_via:?string} */
    public function probe(string $model, string $kind = 'text'): array
    {
        $content = $kind === 'vision'
            ? [['type' => 'text', 'text' => 'What colour is this image? One word.'], self::imagePart(self::probePng(), 'image/png')]
            : 'Reply with the single word OK.';
        try {
            $r = $this->attempt($model, ['messages' => [['role' => 'user', 'content' => $content]], 'max_tokens' => 10, 'temperature' => 0], $kind, 30);
        } catch (LlmUnavailableException $e) {
            return ['ok' => false, 'code' => $e->errorCode, 'reason' => $e->getMessage(), 'routed_via' => null];
        }

        return $r instanceof LlmResponse ? ['ok' => true, 'code' => null, 'reason' => null, 'routed_via' => $r->routedVia]
            : ['ok' => false, 'code' => $r['code'], 'reason' => $r['reason'], 'routed_via' => null];
    }

    /**
     * Per model of a kind: ready, cooling (seconds left) or failed (last reason), from the cache this client keeps.
     *
     * @return list<array{model:string,state:'ready'|'cooling'|'failed',seconds:?int,reason:?string}>
     */
    public function modelStates(string $kind): array
    {
        return array_map(function (string $model) use ($kind) {
            if ($cool = $this->activeCooldown($kind, $model)) {
                return ['model' => $model, 'state' => 'cooling', 'seconds' => max(1, $cool['until'] - time()), 'reason' => $cool['code']];
            }
            $last = Cache::get(self::key('state', $kind, $model));

            return ['model' => $model, 'state' => ($last['state'] ?? 'ok') === 'failed' ? 'failed' : 'ready', 'seconds' => null, 'reason' => $last['reason'] ?? null];
        }, $this->models($kind));
    }

    /**
     * GET /models?available=true.
     *
     * @return list<string> model ids
     *
     * @throws LlmUnavailableException
     */
    public function availableModels(): array
    {
        $res = $this->get('models', ['available' => 'true']);

        return array_values(array_filter(array_map(fn ($m) => is_array($m) ? (string) ($m['id'] ?? '') : (string) $m, (array) ($res->json('data') ?? $res->json('models') ?? [])), 'strlen'));
    }

    /**
     * POST /search (FreeLLMAPI web search; needs an engine key in its dashboard).
     *
     * @return list<array{title:string,url:string,snippet:string}>
     *
     * @throws LlmUnavailableException
     */
    public function search(string $query, int $limit = 10): array
    {
        if (! $this->configured()) {
            throw new LlmUnavailableException(LlmUnavailableException::NOT_CONFIGURED);
        }
        try {
            $res = $this->http(20)->post('search', ['query' => $query, 'max_results' => $limit]);
        } catch (ConnectionException) {
            throw new LlmUnavailableException(LlmUnavailableException::GATEWAY_DOWN, 'unreachable');
        }
        if (! $res->successful()) {
            throw new LlmUnavailableException($res->status() === 429 ? LlmUnavailableException::QUOTA_EXCEEDED : LlmUnavailableException::GATEWAY_DOWN, 'search_http_'.$res->status());
        }
        $rows = $res->json('results') ?? $res->json('data') ?? [];

        return array_values(array_filter(array_map(fn ($r) => is_array($r) ? ['title' => (string) ($r['title'] ?? ''), 'url' => (string) ($r['url'] ?? $r['link'] ?? ''),
            'snippet' => (string) ($r['snippet'] ?? $r['content'] ?? $r['description'] ?? '')] : null, (array) $rows)));
    }

    /**
     * GET /search/engines: the web search engines enabled in the dashboard ({data: [{id, name, provider, available}]}).
     *
     * @return list<string>
     *
     * @throws LlmUnavailableException
     */
    public function searchEngines(): array
    {
        $res = $this->get('search/engines');
        $rows = $res->json('engines') ?? $res->json('data') ?? $res->json() ?? [];

        return array_values(array_filter(array_map(function ($e) {
            if (is_string($e)) {
                return $e;
            }
            if (! is_array($e) || (isset($e['enabled']) && ! $e['enabled'])) {
                return null;
            }

            return (string) ($e['name'] ?? $e['id'] ?? '').(isset($e['available']) && ! $e['available'] ? ' (no working key)' : '');
        }, is_array($rows) ? $rows : [])));
    }

    /** @return array{type:string,image_url:array{url:string}} an OpenAI image part (base64 data URL) */
    public static function imagePart(string $bytes, string $mime): array
    {
        return ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode($bytes)]];
    }

    /** The first JSON object / array in a model answer: tolerates ```json fences and text before / after it. */
    public static function parseJson(string $content): ?array
    {
        $text = trim($content);
        if (preg_match('/```(?:json|JSON)?\s*(.*?)```/s', $text, $m)) {
            $text = trim($m[1]);
        }
        $json = json_decode($text, true);
        if (is_array($json)) {
            return $json;
        }
        foreach (['{' => '}', '[' => ']'] as $open => $close) {
            $start = strpos($text, $open);
            $end = strrpos($text, $close);
            if ($start !== false && $end !== false && $end > $start) {
                $json = json_decode(substr($text, $start, $end - $start + 1), true);
                if (is_array($json)) {
                    return $json;
                }
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ internals

    private function http(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl((string) config('product_ai.base_url'))
            ->withToken((string) config('product_ai.api_key'))
            ->acceptJson()->asJson()
            ->timeout(max(1, $timeout ?? (int) config('product_ai.timeout', 40)))
            ->connectTimeout(max(1, (int) config('product_ai.connect_timeout', 3)));
    }

    /** @throws LlmUnavailableException */
    private function get(string $path, array $query = []): Response
    {
        if (! $this->configured()) {
            throw new LlmUnavailableException(LlmUnavailableException::NOT_CONFIGURED);
        }
        try {
            $res = $this->http(10)->get($path, $query);
        } catch (ConnectionException) {
            throw new LlmUnavailableException(LlmUnavailableException::GATEWAY_DOWN, 'unreachable');
        }
        if ($res->status() === 401 || $res->status() === 403) {
            throw new LlmUnavailableException(LlmUnavailableException::NOT_CONFIGURED, 'key_rejected');
        }
        if (! $res->successful()) {
            throw new LlmUnavailableException(LlmUnavailableException::GATEWAY_DOWN, 'http_'.$res->status());
        }

        return $res;
    }

    private function payload(array $messages, array $options): array
    {
        $p = ['messages' => $messages, 'temperature' => (float) ($options['temperature'] ?? config('product_ai.temperature', 0.2))];
        if (isset($options['max_tokens'])) {
            $p['max_tokens'] = (int) $options['max_tokens'];
        }
        if (! empty($options['json'])) {
            $p['response_format'] = ['type' => 'json_object'];
        }

        return $p;
    }

    /** @return array{code:string,reason:string,retry:int} */
    private function startCooldown(string $kind, string $model, string $code, string $reason, int $seconds): array
    {
        Cache::put(self::key('cooldown', $kind, $model), ['until' => time() + $seconds, 'code' => $code], $seconds);
        $this->remember($kind, $model, 'failed', $reason);
        logger()->warning('freellm: model cooling down', ['kind' => $kind, 'model' => $model, 'reason' => $reason, 'seconds' => $seconds]);

        return ['code' => $code, 'reason' => $reason, 'retry' => $seconds];
    }

    /** @return array{until:int,code:string}|null */
    private function activeCooldown(string $kind, string $model): ?array
    {
        $c = Cache::get(self::key('cooldown', $kind, $model));

        return is_array($c) && ($c['until'] ?? 0) > time() ? $c : null;
    }

    private function remember(string $kind, string $model, string $state, ?string $reason): void
    {
        Cache::put(self::key('state', $kind, $model), ['state' => $state, 'reason' => $reason, 'at' => time()], now()->addDay());
    }

    private function retryAfter(Response $res): int
    {
        $h = trim((string) $res->header('Retry-After'));
        $seconds = ctype_digit($h) ? (int) $h : (($t = strtotime($h)) ? $t - time() : 0);

        return $seconds > 0 ? min($seconds, 3600) : max(1, (int) config('product_ai.cooldown_seconds', 60));
    }

    private static function key(string $what, string $kind, string $model): string
    {
        return "product_ai:$what:$kind:".sha1($model);
    }

    /** OpenAI content: a string, or a list of parts. */
    private static function content(mixed $c): string
    {
        if (is_array($c)) {
            return implode('', array_map(fn ($p) => is_array($p) ? (string) ($p['text'] ?? '') : (string) $p, $c));
        }

        return (string) $c;
    }

    /**
     * All failed: one code for the admin. Only no_vision_model → no_vision_model; only quota / vision → quota_exceeded;
     * only timeouts → timeout; anything else → gateway_down.
     *
     * @param  list<string>  $codes
     */
    private static function summarize(array $codes): string
    {
        $set = array_values(array_unique($codes));
        if ($set === []) {
            return LlmUnavailableException::GATEWAY_DOWN;
        }
        if ($set === [LlmUnavailableException::NO_VISION_MODEL]) {
            return LlmUnavailableException::NO_VISION_MODEL;
        }
        if (array_diff($set, [LlmUnavailableException::QUOTA_EXCEEDED, LlmUnavailableException::NO_VISION_MODEL]) === []) {
            return LlmUnavailableException::QUOTA_EXCEEDED;
        }
        if ($set === [LlmUnavailableException::TIMEOUT]) {
            return LlmUnavailableException::TIMEOUT;
        }

        return LlmUnavailableException::GATEWAY_DOWN;
    }

    /** An 8×8 red PNG for the vision probe. */
    private static function probePng(): string
    {
        $im = imagecreatetruecolor(8, 8);
        imagefill($im, 0, 0, (int) imagecolorallocate($im, 220, 20, 20));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }
}
