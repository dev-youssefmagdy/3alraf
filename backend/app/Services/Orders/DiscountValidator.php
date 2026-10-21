<?php

namespace App\Services\Orders;

use App\Enums\DiscountScope;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Validates a discount code against cart lines (docs/CALCULATIONS.md step 1). Math itself stays in OrderCalculator. */
class DiscountValidator
{
    /**
     * @param  array<int,array{key:string|int,product_id:int,unit_price:string,qty:int}>  $lines
     * @return array{model:DiscountCode,rule:array,eligible:array<string|int,bool>}
     */
    public function validate(string $code, array $lines, ?int $customerId = null, bool $lock = false, ?string $email = null, ?string $phone = null): array
    {
        $q = DiscountCode::query()->where('code', trim($code));
        $dc = ($lock ? $q->lockForUpdate() : $q)->first();
        if (! $dc || ! $dc->is_active) {
            $this->fail('orders.discount.invalid');
        }
        if ($dc->starts_at && $dc->starts_at->isFuture()) {
            $this->fail('orders.discount.not_started');
        }
        if ($dc->ends_at && $dc->ends_at->isPast()) {
            $this->fail('orders.discount.expired');
        }
        if ($dc->usage_limit !== null && $dc->used_count >= $dc->usage_limit) {
            $this->fail('orders.discount.usage_limit');
        }
        if ($dc->per_customer_limit !== null && $this->customerUses($dc, $customerId, $email, $phone) >= $dc->per_customer_limit) {
            $this->fail('orders.discount.per_customer');
        }

        $subtotal = array_reduce($lines, fn ($c, $l) => Money::add($c, Money::mul($l['unit_price'], $l['qty'])), '0');
        if (Money::cmp($subtotal, (string) $dc->min_order) < 0) {
            $this->fail('orders.discount.min_order', ['min' => Money::format((string) $dc->min_order)]);
        }

        $eligible = $this->eligible($dc, $lines);
        if (! in_array(true, $eligible, true) && ! $dc->free_shipping) {
            $this->fail('orders.discount.no_eligible');
        }

        return [
            'model' => $dc, 'eligible' => $eligible,
            'rule' => ['type' => $dc->type->value, 'value' => (string) $dc->value, 'min_order' => (string) $dc->min_order,
                'max_discount' => $dc->max_discount !== null ? (string) $dc->max_discount : null, 'free_shipping' => $dc->free_shipping],
        ];
    }

    /** Past uses by this shopper: same customer id, OR (for guests too) the same e-mail / phone on the usage's order. */
    private function customerUses(DiscountCode $dc, ?int $customerId, ?string $email, ?string $phone): int
    {
        $email = $email ? mb_strtolower(trim($email)) : null;
        $phone = $phone ? preg_replace('/\D+/', '', $phone) : null;
        if (! $customerId && ! $email && ! $phone) {
            return 0;
        }

        return DiscountCodeUsage::where('discount_code_id', $dc->id)->where(function ($q) use ($customerId, $email, $phone) {
            $orders = fn ($sub) => $sub->select('id')->from('orders');
            if ($customerId) {
                $q->orWhere('customer_id', $customerId);
            }
            if ($email) {
                $q->orWhereIn('order_id', fn ($sub) => $orders($sub)->whereRaw('LOWER(customer_email) = ?', [$email]));
            }
            if ($phone) {
                $q->orWhereIn('order_id', fn ($sub) => $orders($sub)->whereRaw("REGEXP_REPLACE(customer_phone, '[^0-9]', '') = ?", [$phone]));
            }
        })->count();
    }

    /** @return array<string|int,bool> */
    public function eligible(DiscountCode $dc, array $lines): array
    {
        $out = [];
        $catIds = $dc->scope === DiscountScope::CATEGORIES ? $this->withDescendants($dc->category_ids ?? []) : [];
        $productCats = [];
        if ($catIds) {
            foreach (DB::table('category_product')->whereIn('product_id', array_column($lines, 'product_id'))->get() as $r) {
                $productCats[$r->product_id][] = (int) $r->category_id;
            }
        }
        foreach ($lines as $l) {
            $out[$l['key']] = match ($dc->scope) {
                DiscountScope::ALL => true,
                DiscountScope::PRODUCTS => in_array($l['product_id'], array_map('intval', $dc->product_ids ?? []), true),
                DiscountScope::CATEGORIES => (bool) array_intersect($productCats[$l['product_id']] ?? [], $catIds),
            };
        }

        return $out;
    }

    private function withDescendants(array $ids): array
    {
        $ids = array_map('intval', $ids);
        $all = $ids;
        foreach ($ids as $id) {
            $all = array_merge($all, Category::query()->where(fn ($q) => $q->where('path', 'like', "$id/%")->orWhere('path', 'like', "%/$id/%")->orWhere('path', 'like', "%/$id"))->pluck('id')->all());
        }

        return array_values(array_unique($all));
    }

    private function fail(string $key, array $params = []): never
    {
        throw ValidationException::withMessages(['discount_code' => __($key, $params)]);
    }
}
