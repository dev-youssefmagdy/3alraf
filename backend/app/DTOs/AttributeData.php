<?php

namespace App\DTOs;

use App\Http\Requests\Central\AttributeRequest;

final readonly class AttributeData
{
    /** @param array<int,array{id:?int,value:array,color_hex:?string,sort_order:int}> $values */
    public function __construct(
        public array $name,
        public string $code,
        public string $type,
        public int $sortOrder,
        public array $values,
    ) {}

    public static function fromRequest(AttributeRequest $r): self
    {
        $v = $r->validated();
        $isColor = $v['type'] === 'color';
        $values = collect($r->input('values', []))->values()->map(fn ($row, $i) => [
            'id' => ! empty($row['id']) ? (int) $row['id'] : null,
            'value' => $r->cleanTranslations($row['value'] ?? []),
            'color_hex' => $isColor ? ($row['color_hex'] ?? null) : null,
            'sort_order' => $i,
        ])->all();

        return new self($r->cleanTranslations($v['name']), strtolower($v['code']), $v['type'], (int) ($v['sort_order'] ?? 0), $values);
    }
}
