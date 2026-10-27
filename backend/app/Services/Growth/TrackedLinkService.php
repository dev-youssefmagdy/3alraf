<?php

namespace App\Services\Growth;

use App\Models\Order;
use App\Models\Product;
use App\Models\TenantProduct;
use App\Models\TrackedLink;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Comment-to-Cart / Intent-to-Sale: short tracked link -> product page with UTM (first-touch attribution picks it up) -> click / order stats. */
class TrackedLinkService
{
    public function create(int $productId, ?string $channel, ?int $interactionId, ?string $campaign): TrackedLink
    {
        if (! TenantProduct::where('product_id', $productId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['product_id' => __('growth_hub.links.bad_product')]);
        }
        do {
            $code = Str::lower(Str::random(8));
        } while (TrackedLink::withoutGlobalScopes()->where('code', $code)->exists());

        return TrackedLink::create(['code' => $code, 'channel' => $channel, 'interaction_id' => $interactionId, 'product_id' => $productId, 'utm_source' => $channel ?: 'social', 'utm_campaign' => $campaign ? Str::limit(preg_replace('/[^\w\-]/u', '', $campaign), 60, '') : 'comment_to_cart']);
    }

    public function url(TrackedLink $l): string
    {
        return route('storefront.tracked-link', ['code' => $l->code]);
    }

    /** Where the short link lands (null if the product is gone). */
    public function target(TrackedLink $l): ?string
    {
        $slug = Product::whereKey($l->product_id)->value('slug');
        if (! $slug) {
            return null;
        }

        return route('storefront.product.show', ['locale' => current_tenant()->default_locale, 'slug' => $slug])
            .'?'.http_build_query(['utm_source' => $l->utm_source, 'utm_medium' => 'social', 'utm_campaign' => $l->utm_campaign, 'utm_content' => 'tl'.$l->code]);
    }

    public function click(TrackedLink $l, ?string $ua): void
    {
        if (! AttributionService::isBot($ua)) {
            $l->increment('clicks');
        }
    }

    public function stats(TrackedLink $l): array
    {
        $o = Order::where('status', '!=', 'cancelled')->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(attribution, '$.utm_content')) = ?", ['tl'.$l->code]);

        return ['clicks' => $l->clicks, 'orders' => (clone $o)->count(), 'revenue' => round((float) (clone $o)->sum('total'), 2)];
    }
}
