<?php

namespace App\DTOs\Ai;

use App\Http\Requests\Central\Ai\AiModelRequest;

final readonly class AiModelData
{
    public function __construct(public int $providerId, public string $modelId, public string $label, public string $kind, public array $capabilities,
        public ?string $inputCost, public ?string $outputCost, public ?int $maxOutput, public bool $isActive, public bool $isDefault) {}

    public static function fromRequest(AiModelRequest $r): self
    {
        $v = $r->validated();
        $caps = [];
        foreach (AiModelRequest::CAPABILITIES as $c) {
            $caps[$c] = in_array($c, (array) ($v['capabilities'] ?? []), true);
        }

        return new self((int) $v['ai_provider_id'], $v['model_id'], $v['label'], $v['kind'], $caps, filled($v['input_cost'] ?? null) ? (string) $v['input_cost'] : null,
            filled($v['output_cost'] ?? null) ? (string) $v['output_cost'] : null, filled($v['max_output'] ?? null) ? (int) $v['max_output'] : null, $r->boolean('is_active'), $r->boolean('is_default'));
    }
}
