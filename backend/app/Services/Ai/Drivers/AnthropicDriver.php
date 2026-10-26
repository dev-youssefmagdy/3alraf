<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Support\AiRequest;
use App\Services\Ai\Support\AiResponse;

/** Anthropic Messages API (tool_use / tool_result blocks normalised to AiRequest/AiResponse). */
class AnthropicDriver extends BaseDriver
{
    protected function defaultBaseUrl(): string
    {
        return 'https://api.anthropic.com';
    }

    protected function headers(): array
    {
        return ['x-api-key' => (string) $this->provider->api_key, 'anthropic-version' => (string) config('ai.anthropic_version', '2023-06-01')];
    }

    public function payload(AiRequest $r): array
    {
        $messages = [];
        foreach ($r->messages as $m) {
            if ($m['role'] === 'tool') {
                $block = ['type' => 'tool_result', 'tool_use_id' => $m['tool_call_id'], 'content' => (string) $m['content']];
                $last = end($messages);
                if ($last && $last['role'] === 'user' && is_array($last['content']) && ($last['content'][0]['type'] ?? null) === 'tool_result') {
                    $messages[array_key_last($messages)]['content'][] = $block;   // parallel results share one user turn
                } else {
                    $messages[] = ['role' => 'user', 'content' => [$block]];
                }
            } elseif ($m['role'] === 'assistant' && ! empty($m['tool_calls'])) {
                $blocks = [];
                if (($m['content'] ?? '') !== '') {
                    $blocks[] = ['type' => 'text', 'text' => $m['content']];
                }
                foreach ($m['tool_calls'] as $c) {
                    $blocks[] = ['type' => 'tool_use', 'id' => $c['id'], 'name' => $c['name'], 'input' => (object) ($c['arguments'] ?? [])];
                }
                $messages[] = ['role' => 'assistant', 'content' => $blocks];
            } else {
                $messages[] = ['role' => $m['role'], 'content' => (string) $m['content']];
            }
        }
        $p = ['model' => $r->model, 'max_tokens' => $r->maxTokens, 'messages' => $messages];
        if ($r->system) {
            $p['system'] = $r->system;
        }
        if ($r->temperature !== null) {
            $p['temperature'] = $r->temperature;
        }
        $tools = array_map(fn ($t) => ['name' => $t['name'], 'description' => $t['description'], 'input_schema' => $t['schema'] ?: ['type' => 'object', 'properties' => (object) []]], $r->tools);
        if ($r->webSearch) {
            $tools[] = ['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 5];
        }
        if ($tools) {
            $p['tools'] = $tools;
        }

        return $p;
    }

    public function chat(AiRequest $request): AiResponse
    {
        $d = $this->check($this->call(fn () => $this->http()->post($this->base().'/v1/messages', $this->payload($request))));
        $text = '';
        $calls = [];
        foreach ($d['content'] ?? [] as $b) {
            if (($b['type'] ?? '') === 'text') {
                $text .= $b['text'];
            } elseif (($b['type'] ?? '') === 'tool_use') {
                $calls[] = ['id' => $b['id'], 'name' => $b['name'], 'arguments' => (array) ($b['input'] ?? [])];
            }
        }

        return new AiResponse($text, $calls, (int) ($d['usage']['input_tokens'] ?? 0), (int) ($d['usage']['output_tokens'] ?? 0), $d['stop_reason'] ?? null);
    }

    public function test(): bool
    {
        return $this->call(fn () => $this->http()->get($this->base().'/v1/models'))->successful();
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['chat', 'tools', 'vision', 'web_search'], true);
    }
}
