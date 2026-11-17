<?php

namespace App\Services;

use App\DTOs\AttributeData;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Repositories\Contracts\AttributeRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttributeService
{
    public const DRAFT_CODE_PREFIX = 'draft-';

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
            $attr = $this->attributes->update($attribute, $this->payload($dto, $attribute));
            $this->syncValues($attr, $dto->values);

            return $attr;
        });
    }

    /** Add one value at the end of an existing attribute (new_product suggestions propose values the store lacks). */
    public function addValue(Attribute $attribute, array $value, ?string $colorHex = null): AttributeValue
    {
        return $attribute->values()->create(['value' => $value, 'color_hex' => $colorHex, 'sort_order' => (int) $attribute->values()->max('sort_order') + 1]);
    }

    /** Soft-delete a value unless a live variant uses it (rollback of a new_product suggestion). */
    public function removeValueIfUnused(AttributeValue $value): bool
    {
        if ($this->usedValueIds([$value->id])) {
            return false;
        }
        $value->delete();

        return true;
    }

    public function delete(Attribute $attribute): void
    {
        if ($this->usedValueIds($attribute->values()->pluck('id')->all())) {
            throw ValidationException::withMessages(['attribute' => __('admin.attribute_in_use')]);
        }
        DB::transaction(function () use ($attribute) {
            $attribute->values()->delete(); // soft: removed variants keep their labels
            $this->attributes->delete($attribute);
        });
    }

    private function payload(AttributeData $dto, ?Attribute $attr = null): array
    {
        // only a draft may leave the code blank: a placeholder is stored, shown blank in the form, and publishing requires a real one
        $code = $dto->code ?? $attr?->code ?? self::DRAFT_CODE_PREFIX.Str::lower(Str::random(10));

        return ['name' => $dto->name, 'code' => $code, 'type' => $dto->type, 'sort_order' => $dto->sortOrder,
            'is_draft' => $dto->isDraft ?? (bool) $attr?->is_draft];
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
    /**
     * Values used by a LIVE variant of a LIVE product. Variants replaced in the product form (or of a deleted product) are only
     * soft-deleted for history: they must not block deleting the attribute. Attributes / values are soft-deleted too, so those
     * removed variants keep their value links and labels; retention:purge drops a value only once no variant references it.
     */
    private function usedValueIds(array $ids): array
    {
        return $ids ? DB::table('product_variant_values as pvv')
            ->join('product_variants as v', 'v.id', '=', 'pvv.product_variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->whereIn('pvv.attribute_value_id', $ids)->whereNull('v.deleted_at')->whereNull('p.deleted_at')
            ->distinct()->pluck('pvv.attribute_value_id')->all() : [];
    }
}
