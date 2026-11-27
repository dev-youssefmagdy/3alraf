<?php

// php artisan llm:check (PRODUCT_AI_FREELLMAPI_PLAN.md §5.2): OK when the gateway, the models and the search answer; otherwise it
// names the failing step and exits non-zero. FreeLLMAPI and the search script are faked.

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    config(['product_ai.base_url' => 'http://llm.test/v1', 'product_ai.api_key' => 'freellmapi-test', 'product_ai.models.text' => ['auto'],
        'product_ai.models.vision' => ['auto']]);
    Process::fake(['*' => Process::result(json_encode(['results' => [['title' => 'Fryer', 'url' => 'https://amazon.eg/dp/1', 'snippet' => 'x']], 'errors' => []]))]);
});

it('reports OK when the gateway, a text and a vision model and the search answer', function () {
    Http::fake([
        'llm.test/v1/models*' => Http::response(['data' => [['id' => 'gemini-2.5-flash']]]),
        'llm.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'OK']]]], 200, ['X-Routed-Via' => 'gemini/gemini-2.5-flash']),
        'llm.test/v1/search/engines' => Http::response(['data' => []]),
    ]);

    $this->artisan('llm:check')->expectsOutputToContain('vision model available')->expectsOutputToContain('llm:check OK')->assertSuccessful();
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'chat/completions') && is_array($r->data()['messages'][0]['content']));
});

it('names the failing step and exits non-zero', function (array $config, array $fake, string $message) {
    config($config);
    Http::fake($fake);

    $this->artisan('llm:check')->expectsOutputToContain($message)->assertFailed();
})->with([
    'no key' => [['product_ai.api_key' => ''], fn () => [], '[FAIL] configured'],
    'key rejected' => [[], fn () => ['llm.test/*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)], '[FAIL] key valid'],
    'gateway down' => [[], fn () => ['llm.test/*' => Http::failedConnection()], '[FAIL] reachable'],
    'no vision model' => [[], fn () => [
        'llm.test/v1/models*' => Http::response(['data' => [['id' => 'llama']]]),
        'llm.test/v1/chat/completions' => fn (HttpRequest $r) => is_array($r->data()['messages'][0]['content'])
            ? Http::response(['error' => ['code' => 'no_vision_model']], 422)
            : Http::response(['choices' => [['message' => ['content' => 'OK']]]]),
        'llm.test/v1/search/engines' => Http::response(['data' => []]),
    ], '[FAIL] vision model available'],
]);
