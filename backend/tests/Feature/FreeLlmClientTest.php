<?php

// FreeLlmClient (PRODUCT_AI_FREELLMAPI_PLAN.md §2): automatic model switching over the configured model list, per-model cooldown,
// the error-contract codes, X-Routed-Via and tolerant JSON parsing. FreeLLMAPI is faked; no real network.

use App\Services\Llm\FreeLlmClient;
use App\Services\Llm\LlmUnavailableException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function llmAnswer(string $content, string $routed = 'groq/llama-3.3-70b'): PromiseInterface
{
    return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]], 200, ['X-Routed-Via' => $routed]);
}

/** @param  array<string, mixed>  $byModel  model => response (or a Closure returning one) */
function fakeLlm(array $byModel): void
{
    Http::swap(new Factory); // replace earlier fakes of the same test
    Http::fake(['llm.test/*' => function (HttpRequest $r) use ($byModel) {
        $answer = $byModel[$r->data()['model'] ?? ''] ?? Http::response(['error' => ['message' => 'unknown profile']], 400);

        return $answer instanceof Closure ? $answer($r) : $answer;
    }]);
}

function sentModels(): array
{
    return Http::recorded()->map(fn ($pair) => $pair[0]->data()['model'] ?? null)->filter()->values()->all();
}

beforeEach(function () {
    Cache::flush();
    config(['product_ai.base_url' => 'http://llm.test/v1', 'product_ai.api_key' => 'freellmapi-test',
        'product_ai.models.text' => ['auto', 'auto:fast'], 'product_ai.models.vision' => ['auto', 'gemini-2.5-flash'], 'product_ai.cooldown_seconds' => 60]);
    $this->llm = app(FreeLlmClient::class);
});

it('switches to the next model when the first is out of quota, and says which model answered', function () {
    fakeLlm([
        'auto' => Http::response(['error' => ['code' => 'quota_exceeded', 'message' => 'all keys capped']], 429, ['Retry-After' => '120']),
        'auto:fast' => llmAnswer('Hello', 'gemini/gemini-2.5-flash'),
    ]);

    $r = $this->llm->chat([['role' => 'user', 'content' => 'Hi']]);

    expect($r->content)->toBe('Hello')->and($r->model)->toBe('auto:fast')->and($r->routedVia)->toBe('gemini/gemini-2.5-flash')
        ->and($r->answeredBy())->toBe('gemini/gemini-2.5-flash')->and($r->skipped)->toBe(['auto' => 'quota_exceeded'])
        ->and(sentModels())->toBe(['auto', 'auto:fast']);
    Http::assertSent(fn (HttpRequest $req) => $req->url() === 'http://llm.test/v1/chat/completions' && $req->hasHeader('Authorization', 'Bearer freellmapi-test'));
});

it('skips a model that is cooling down, until Retry-After has passed', function () {
    fakeLlm([
        'auto' => Http::response(['error' => ['code' => 'quota_exceeded']], 429, ['Retry-After' => '120']),
        'auto:fast' => llmAnswer('one'),
    ]);
    $this->llm->chat([['role' => 'user', 'content' => 'a']]);
    $this->llm->chat([['role' => 'user', 'content' => 'b']]);

    expect(sentModels())->toBe(['auto', 'auto:fast', 'auto:fast']);   // the second call did not try "auto" again
    expect(collect($this->llm->modelStates('text'))->pluck('state', 'model')->all())->toBe(['auto' => 'cooling', 'auto:fast' => 'ready']);

    $this->travel(121)->seconds();
    $this->llm->chat([['role' => 'user', 'content' => 'c']]);
    expect(sentModels()[3])->toBe('auto');
});

it('also switches on 5xx, connection errors and no_vision_model, and cools a 5xx model down for the default time', function () {
    fakeLlm([
        'auto' => Http::response(['error' => ['code' => 'no_vision_model']], 422),
        'gemini-2.5-flash' => llmAnswer('{"brand":"Philips"}'),
    ]);
    expect($this->llm->chat([['role' => 'user', 'content' => 'x']], 'vision')->model)->toBe('gemini-2.5-flash');

    fakeLlm(['auto' => Http::failedConnection('cURL error 7: refused'), 'auto:fast' => Http::response('bad gateway', 502)]);
    try {
        $this->llm->chat([['role' => 'user', 'content' => 'x']]);
        $this->fail('expected an exception');
    } catch (LlmUnavailableException $e) {
        expect($e->errorCode)->toBe('gateway_down')->and($e->httpStatus())->toBe(503)->and($e->failures)->toBe(['auto' => 'unreachable', 'auto:fast' => 'http_502']);
    }
    expect(collect($this->llm->modelStates('text'))->firstWhere('model', 'auto:fast'))->toMatchArray(['state' => 'cooling', 'seconds' => 60]);
});

it('throws quota_exceeded (429) only when every model failed, and no_vision_model when no model reads photos', function () {
    fakeLlm(['auto' => Http::response(['error' => ['code' => 'quota_exceeded']], 429, ['Retry-After' => '30']),
        'auto:fast' => Http::response(['error' => ['code' => 'quota_exceeded']], 429, ['Retry-After' => '90'])]);
    expect(fn () => $this->llm->chat([['role' => 'user', 'content' => 'x']]))
        ->toThrow(fn (LlmUnavailableException $e) => expect($e->errorCode)->toBe('quota_exceeded')->and($e->httpStatus())->toBe(429)->and($e->retryAfter)->toBe(30));

    fakeLlm(['auto' => Http::response(['error' => ['code' => 'no_vision_model']], 422), 'gemini-2.5-flash' => Http::response(['error' => ['code' => 'no_vision_model']], 422)]);
    expect(fn () => $this->llm->chat([['role' => 'user', 'content' => 'x']], 'vision'))
        ->toThrow(fn (LlmUnavailableException $e) => expect($e->errorCode)->toBe('no_vision_model')->and($e->httpStatus())->toBe(422));
});

it('stops at once on a rejected key, and sends nothing when not configured', function () {
    fakeLlm(['auto' => Http::response(['error' => ['message' => 'Invalid API key']], 401), 'auto:fast' => llmAnswer('x')]);
    expect(fn () => $this->llm->chat([['role' => 'user', 'content' => 'x']]))
        ->toThrow(fn (LlmUnavailableException $e) => expect($e->errorCode)->toBe('not_configured')->and($e->getMessage())->toBe('key_rejected'));
    expect(sentModels())->toBe(['auto']);

    config(['product_ai.api_key' => '']);
    Http::swap(new Factory);
    Http::fake();
    expect($this->llm->configured())->toBeFalse()
        ->and(fn () => $this->llm->chat([['role' => 'user', 'content' => 'x']]))->toThrow(fn (LlmUnavailableException $e) => expect($e->errorCode)->toBe('not_configured'));
    Http::assertNothingSent();
});

it('parses JSON inside code fences, and tries the next model when an answer is not valid JSON or fails validation', function () {
    fakeLlm(['auto' => llmAnswer('Sure! Here it is: not json'), 'auto:fast' => llmAnswer("```json\n{\"ar\": [\"عنوان\"], \"en\": [\"Title\"]}\n```")]);

    [$data, $r] = $this->llm->chatJson([['role' => 'user', 'content' => 'x']], 'text', fn (array $j) => isset($j['en']) ? $j : null);

    expect($data)->toBe(['ar' => ['عنوان'], 'en' => ['Title']])->and($r->model)->toBe('auto:fast');
    Http::assertSent(fn (HttpRequest $req) => ($req->data()['response_format'] ?? null) === ['type' => 'json_object']);
    expect(FreeLlmClient::parseJson('Answer: {"a": 1} thanks'))->toBe(['a' => 1])->and(FreeLlmClient::parseJson('nothing'))->toBeNull();

    fakeLlm(['auto' => llmAnswer('{"x":1}'), 'auto:fast' => llmAnswer('{"y":1}')]);
    expect(fn () => $this->llm->chatJson([['role' => 'user', 'content' => 'x']], 'text', fn () => null))
        ->toThrow(fn (LlmUnavailableException $e) => expect($e->errorCode)->toBe('gateway_down')->and($e->userMessage())->toBe(__('admin.product_ai.error_bad_answer')));
});
