<?php

namespace App\DTOs\Ai;

use App\Http\Requests\Tenant\AiProductEditRequest;

final readonly class ProductEditData
{
    /** @param int[] $productIds @param string[] $fields @param string[] $locales */
    public function __construct(public array $productIds, public array $fields, public array $locales, public string $preset, public ?string $instruction, public ?int $textModelId) {}

    public static function fromRequest(AiProductEditRequest $r): self
    {
        $v = $r->validated();

        return new self(array_values(array_unique(array_map('intval', $v['product_ids']))), array_values(array_unique($v['fields'])), array_values(array_unique($v['locales'])),
            $v['preset'], filled($v['instruction'] ?? null) ? trim($v['instruction']) : null, filled($v['ai_model_id'] ?? null) ? (int) $v['ai_model_id'] : null);
    }
}
