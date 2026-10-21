<?php

namespace App\DTOs;

use App\Http\Requests\Central\LanguageRequest;

final readonly class LanguageData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $nativeName,
        public string $direction,
        public bool $isActive,
        public bool $isDefault,
        public int $sortOrder,
    ) {}

    public static function fromRequest(LanguageRequest $r): self
    {
        $v = $r->validated();

        return new self(strtolower($v['code']), $v['name'], $v['native_name'], $v['direction'],
            $r->boolean('is_active'), $r->boolean('is_default'), (int) ($v['sort_order'] ?? 0));
    }

    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'native_name' => $this->nativeName,
            'direction' => $this->direction, 'is_active' => $this->isActive || $this->isDefault,
            'is_default' => $this->isDefault, 'sort_order' => $this->sortOrder];
    }
}
