<?php

namespace App\Services;

use App\DTOs\BrandData;
use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Support\Slug;
use Illuminate\Validation\ValidationException;

class BrandService
{
    public function __construct(private BrandRepositoryInterface $brands, private ImageUploadService $images) {}

    public function create(BrandData $dto): Brand
    {
        return $this->brands->create($this->payload($dto));
    }

    public function update(Brand $brand, BrandData $dto): Brand
    {
        return $this->brands->update($brand, $this->payload($dto, $brand));
    }

    public function delete(Brand $brand): void
    {
        if ($brand->products()->exists()) {
            throw ValidationException::withMessages(['brand' => __('admin.brand_has_products')]);
        }
        $this->brands->delete($brand);
    }

    private function payload(BrandData $dto, ?Brand $brand = null): array
    {
        $data = [
            'name' => $dto->name,
            'slug' => Slug::unique(Brand::class, $dto->slug ?: ($brand?->slug), $dto->name, $brand?->id),
            'is_active' => $dto->isActive,
            'sort_order' => $dto->sortOrder,
        ];
        if ($dto->logo) {
            $data['logo'] = $this->images->storeSingle($dto->logo, 'brands', $brand?->logo);
        } elseif ($dto->removeLogo && $brand?->logo) {
            $this->images->deletePath($brand->logo);
            $data['logo'] = null;
        }

        return $data;
    }
}
