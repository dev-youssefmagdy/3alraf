<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\PackagingOptionRequest;
use Illuminate\Http\UploadedFile;

final readonly class PackagingOptionData
{
    public function __construct(public array $name, public array $description, public string $price, public string $cost, public int $sortOrder,
        public bool $isActive, public ?UploadedFile $image, public bool $removeImage) {}

    public static function fromRequest(PackagingOptionRequest $r): self
    {
        return new self($r->cleanTranslations($r->input('name')), $r->cleanTranslations($r->input('description')), (string) $r->input('price'),
            (string) ($r->input('cost') ?: '0'), (int) $r->input('sort_order', 0), $r->boolean('is_active'), $r->file('image'), $r->boolean('remove_image'));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'description' => $this->description ?: null, 'price' => $this->price, 'cost' => $this->cost,
            'sort_order' => $this->sortOrder, 'is_active' => $this->isActive];
    }
}
