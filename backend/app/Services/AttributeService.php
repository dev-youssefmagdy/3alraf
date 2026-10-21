<?php

namespace App\Services;

use App\DTOs\AttributeData;
use App\Models\Attribute;
use App\Repositories\Contracts\AttributeRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributeService
{
    public function __construct(private AttributeRepositoryInterface $attributes) {}

    public function create(AttributeData $dto): Attribute
    {
        return DB::transaction(function () use ($dto) {
            $attr = $this->attributes->create($this->payload($dto));
            $this->syncValues($attr, $dto->values);

            return $attr;
        });
    }

    public function update(Attribute $attribute, AttributeData $dto): Attribute
    {
        return DB::transaction(function () use ($attribute, $dto) {
            $attr = $this->attributes->update($attribute, $this->payload($dto));
            $this->syncValues($attr, $dto->values);

            return $attr;
        });
    }

    public function delete(Attribute $attribute): void
    {
        if ($this->usedValueIds($attribute->values()->pluck('id')->all())) {
            throw ValidationException::withMessages(['attribute' => __('admin.attribute_in_use')]);
        }
        $this->attributes->delete($attribute);
    }

    private function payload(AttributeData $dto): array
    {
        return ['name' => $dto->name, 'code' => $dto->code, 'type' => $dto->type, 'sort_order' => $dto->sortOrder];
    }

    private function syncValues(Attribute $attr, array $rows): void
    {
        $existing = $attr->values()->get()->keyBy('id');
        $keep = [];
        foreach ($rows as $row) {
            $data = ['value' => $row['value'], 'color_hex' => $row['color_hex'], 'sort_order' => $row['sort_order']];
            if ($row['id'] && $existing->has($row['id'])) {
                $existing[$row['id']]->update($data);
                $keep[] = $row['id'];
            } else {
                $keep[] = $attr->values()->create($data)->id;
            }
        }
        $remove = $existing->keys()->diff($keep)->all();
        if ($used = $this->usedValueIds($remove)) {
            throw ValidationException::withMessages(['values' => __('admin.attribute_value_in_use')]);
        }
        $attr->values()->whereIn('id', $remove)->delete();
    }

    /** @return int[] ids of values referenced by variants */
    private function usedValueIds(array $ids): array
    {
        return $ids ? DB::table('product_variant_values')->whereIn('attribute_value_id', $ids)->distinct()->pluck('attribute_value_id')->all() : [];
    }
}
