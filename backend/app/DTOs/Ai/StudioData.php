<?php

namespace App\DTOs\Ai;

use App\Http\Requests\Tenant\AiStudioRequest;

final readonly class StudioData
{
    /** @param int[] $productIds @param string[] $languages */
    public function __construct(
        public string $mode, public string $platform, public string $goal, public string $tone, public array $languages, public ?string $cta,
        public string $format, public array $productIds, public ?int $bundleId, public ?int $flashSaleId, public ?string $brief,
        public ?string $campaignType, public ?string $event, public int $variants, public ?int $textModelId, public ?int $imageModelId, public ?int $videoModelId,
    ) {}

    public static function fromRequest(AiStudioRequest $r): self
    {
        $v = $r->validated();
        $marketing = $v['mode'] === 'marketing';
        $int = fn ($k) => filled($v[$k] ?? null) ? (int) $v[$k] : null;

        return new self($v['mode'], $v['platform'], trim($v['goal']), trim($v['tone']), array_values(array_unique($v['languages'])), filled($v['cta'] ?? null) ? trim($v['cta']) : null,
            $v['format'], array_map('intval', $v['product_ids'] ?? []), $int('bundle_id'), $int('flash_sale_id'), filled($v['brief'] ?? null) ? trim($v['brief']) : null,
            $marketing ? ($v['campaign_type'] ?? null) : null, $marketing ? ($v['event'] ?? null) : null, $marketing ? (int) ($v['variants'] ?? 1) : 1,
            $int('ai_model_id'), $int('image_model_id'), $int('video_model_id'));
    }

    public function toInputs(): array
    {
        return [
            'mode' => $this->mode, 'platform' => $this->platform, 'goal' => $this->goal, 'tone' => $this->tone, 'languages' => $this->languages, 'cta' => $this->cta, 'format' => $this->format,
            'product_ids' => $this->productIds, 'bundle_id' => $this->bundleId, 'flash_sale_id' => $this->flashSaleId, 'brief' => $this->brief,
            'campaign_type' => $this->campaignType, 'event' => $this->event, 'variants' => $this->variants,
            'text_model_id' => $this->textModelId, 'image_model_id' => $this->imageModelId, 'video_model_id' => $this->videoModelId,
        ];
    }

    public function wantsImage(): bool
    {
        return in_array($this->format, ['image', 'all'], true);
    }

    public function wantsVideo(): bool
    {
        return in_array($this->format, ['video', 'all'], true);
    }
}
