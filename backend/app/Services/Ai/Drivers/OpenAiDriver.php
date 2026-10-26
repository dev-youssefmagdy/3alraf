<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Support\AiRequest;
use App\Services\Ai\Support\AiResponse;

/** OpenAI Chat Completions (also used by any OpenAI-compatible vendor via base_url). */
class OpenAiDriver extends BaseDriver
{
    protected function defaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->provider->api_key];
    }

    public function payload(AiRequest $r): array
    {
        $messages = [];
        if ($r->system) {
            $messages[] = ['role' => 'system', 'content' => $r->system];
        }
        foreach ($r->messages as $m) {
            if ($m['role'] === 'tool') {
                $messages[] = ['role' => 'tool', 'tool_call_id' => $m['tool_call_id'], 'content' => (string) $m['content']];
            } elseif ($m['role'] === 'assistant' && ! empty($m['tool_calls'])) {
                $messages[] = ['role' => 'assistant', 'content' => ($m['content'] ?? '') ?: null, 'tool_calls' => array_map(fn ($c) => [
                    'id' => $c['id'], 'type' => 'function', 'function' => ['name' => $c['name'], 'arguments' => json_encode((object) ($c['arguments'] ?? []))],
                ], $m['tool_calls'])];
            } else {
                $messages[] = ['role' => $m['role'], 'content' => (string) $m['content']];
            }
        }
        $p = ['model' => $r->model, 'messages' => $messages, 'max_tokens' => $r->maxTokens];
        if ($r->temperature !== null) {
            $p['temperature'] = $r->temperature;
        }
        if ($r->tools) {
            $p['tools'] = array_map(fn ($t) => ['type' => 'function', 'function' => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['schema'] ?: ['type' => 'object', 'properties' => (object) []]]], $r->tools);
        }
        if ($r->json) {
            $p['response_format'] = ['type' => 'json_object'];
        }

        return $p;
    }

    public function chat(AiRequest $request): AiResponse
    {
        $d = $this->check($this->call(fn () => $this->http()->post($this->base().'/chat/completions', $this->payload($request))));
        $msg = $d['choices'][0]['message'] ?? [];
        $calls = [];
        foreach ($msg['tool_calls'] ?? [] as $c) {
            $args = json_decode($c['function']['arguments'] ?? '{}', true);
            $calls[] = ['id' => $c['id'], 'name' => $c['function']['name'], 'arguments' => is_array($args) ? $args : []];
        }

        return new AiResponse((string) ($msg['content'] ?? ''), $calls, (int) ($d['usage']['prompt_tokens'] ?? 0), (int) ($d['usage']['completion_tokens'] ?? 0), $d['choices'][0]['finish_reason'] ?? null);
    }

    public function image(string $model, string $prompt, array $options = []): AiResponse
    {
        $body = ['model' => $model, 'prompt' => $prompt, 'n' => (int) ($options['n'] ?? 1)] + array_filter(['size' => $options['size'] ?? null]);
        $d = $this->check($this->call(fn () => $this->http()->post($this->base().'/images/generations', $body)));
        $media = array_map(fn ($i) => isset($i['b64_json']) ? ['b64' => $i['b64_json']] : ['url' => $i['url'] ?? null], $d['data'] ?? []);

        return new AiResponse(media: $media, images: count($media), tokensIn: (int) ($d['usage']['input_tokens'] ?? 0), tokensOut: (int) ($d['usage']['output_tokens'] ?? 0));
    }

    public function test(): bool
    {
        return $this->call(fn () => $this->http()->get($this->base().'/models'))->successful();
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['chat', 'tools', 'image', 'json_mode'], true);
    }
}
