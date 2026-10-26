<?php

namespace App\Services\Ai\Tools;

use App\Models\Banner;
use App\Models\DiscountCode;
use App\Models\FlashSale;
use App\Services\Ai\AiContext;

class ListOffersTool extends AbstractTool
{
    public function name(): string { return 'list_offers'; }

    public function description(): string { return 'Current offers: flash sales (with items), discount codes (type, value, limits, usage) and banners. active_only defaults to true.'; }

    public function schema(): array
    {
        return $this->obj(['active_only' => ['type' => 'boolean'], 'limit' => ['type' => 'integer']]);
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $active = ! array_key_exists('active_only', $a) || (bool) $a['active_only'];
        $n = $this->limit($a);
        $flash = FlashSale::query()->with('items.product')->when($active, fn ($q) => $q->where('is_active', true)->where('ends_at', '>=', now()))->orderByDesc('starts_at')->limit($n)->get();
        $codes = DiscountCode::query()->when($active, fn ($q) => $q->where('is_active', true)->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now())))->orderByDesc('id')->limit($n)->get();
        $banners = Banner::query()->when($active, fn ($q) => $q->where('is_active', true)->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now())))->orderBy('sort_order')->limit($n)->get();

        return [
            'currency' => $ctx->currency,
            'flash_sales' => $flash->map(fn ($f) => [
                'id' => $f->id, 'name' => $this->data($f->getTranslation('name'), 100), 'starts_at' => $f->starts_at?->toDateTimeString(), 'ends_at' => $f->ends_at?->toDateTimeString(), 'running' => $f->isRunning(),
                'items' => $f->items->take(15)->map(fn ($i) => ['product_id' => $i->product_id, 'name' => $this->data($i->product?->getTranslation('name'), 80), 'discount_type' => $i->discount_type?->value, 'value' => $i->value, 'qty_limit' => $i->qty_limit, 'sold' => $i->sold])->all(),
            ])->all(),
            'discount_codes' => $codes->map(fn ($c) => [
                'code' => $c->code, 'type' => $c->type?->value, 'value' => $c->value, 'min_order' => $c->min_order, 'max_discount' => $c->max_discount, 'scope' => $c->scope?->value, 'free_shipping' => (bool) $c->free_shipping,
                'usage_limit' => $c->usage_limit, 'used_count' => $c->used_count, 'starts_at' => $c->starts_at?->toDateString(), 'ends_at' => $c->ends_at?->toDateString(), 'active' => (bool) $c->is_active,
            ])->all(),
            'banners' => $banners->map(fn ($b) => ['id' => $b->id, 'title' => $this->data($b->getTranslation('title'), 100), 'placement' => $b->placement?->value, 'starts_at' => $b->starts_at?->toDateString(), 'ends_at' => $b->ends_at?->toDateString()])->all(),
        ];
    }
}
