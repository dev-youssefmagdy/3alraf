<?php

namespace App\DTOs\Ai;

use App\Http\Requests\Tenant\AiGeneratorRequest;

final readonly class GeneratorData
{
    /** @param int[] $productIds */
    public function __construct(public string $kind, public string $goal, public ?string $season, public float $marginFloor, public array $productIds, public ?string $brief,
        public ?string $startsAt, public ?string $endsAt, public ?string $placement, public ?int $textModelId, public ?int $imageModelId) {}

    public static function fromRequest(AiGeneratorRequest $r): self
    {
        return new self($r->input('kind'), trim((string) $r->input('goal')), $r->filled('season') ? trim((string) $r->input('season')) : null,
            (float) ($r->filled('margin_floor') ? $r->input('margin_floor') : 10), array_values(array_unique(array_map('intval', (array) $r->input('product_ids', [])))),
            $r->filled('brief') ? trim((string) $r->input('brief')) : null, $r->input('starts_at') ?: null, $r->input('ends_at') ?: null, $r->input('placement') ?: null,
            $r->filled('ai_model_id') ? (int) $r->input('ai_model_id') : null, $r->filled('image_model_id') ? (int) $r->input('image_model_id') : null);
    }

    public function toInputs(): array
    {
        return ['kind' => $this->kind, 'goal' => $this->goal, 'season' => $this->season, 'margin_floor' => $this->marginFloor, 'product_ids' => $this->productIds, 'brief' => $this->brief,
            'starts_at' => $this->startsAt, 'ends_at' => $this->endsAt, 'placement' => $this->placement, 'text_model_id' => $this->textModelId, 'image_model_id' => $this->imageModelId];
    }

    public static function fromInputs(array $i): self
    {
        return new self($i['kind'], $i['goal'], $i['season'] ?? null, (float) $i['margin_floor'], (array) ($i['product_ids'] ?? []), $i['brief'] ?? null, $i['starts_at'] ?? null,
            $i['ends_at'] ?? null, $i['placement'] ?? null, $i['text_model_id'] ?? null, $i['image_model_id'] ?? null);
    }
}
