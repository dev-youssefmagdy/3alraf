<?php

namespace App\Services\Ai\Support;

/**
 * Provider-neutral request.
 * messages: list of ['role' => user|assistant|tool, 'content' => string, 'tool_calls' => [[id,name,arguments]] (assistant), 'tool_call_id' => string (tool)]
 * tools: list of ['name', 'description', 'schema' (JSON schema array)]
 */
final class AiRequest
{
    public function __construct(
        public string $model,
        public array $messages,
        public ?string $system = null,
        public array $tools = [],
        public int $maxTokens = 1024,
        public ?float $temperature = null,
        public bool $json = false,
        public bool $webSearch = false,
    ) {}
}
