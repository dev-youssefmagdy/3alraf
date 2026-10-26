<?php

namespace App\DTOs\Ai;

use App\Http\Requests\Central\Ai\AiProviderRequest;

final readonly class AiProviderData
{
    public function __construct(public string $driver, public string $name, public ?string $apiKey, public ?string $baseUrl, public bool $isActive) {}

    public static function fromRequest(AiProviderRequest $r): self
    {
        $v = $r->validated();

        return new self($v['driver'], $v['name'], filled($v['api_key'] ?? null) ? $v['api_key'] : null, filled($v['base_url'] ?? null) ? $v['base_url'] : null, $r->boolean('is_active'));
    }
}
