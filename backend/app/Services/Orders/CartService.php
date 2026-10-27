<?php

namespace App\Services\Orders;

use App\Models\Cart;
use App\Models\PackagingOption;
use App\Models\StockLevel;
use App\Repositories\Contracts\CartRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** DB-backed cart keyed by customer (web guard) or by a guest session token; guest cart merges into the customer's on login. */
class CartService
{
    public function __construct(private CartRepositoryInterface $carts, private PriceResolver $prices, private BundleResolver $bundles) {}

    public function cart(bool $create = true): ?Cart
    {
        return $this->carryAttribution($this->resolveCart($create));
    }

    /** Mobile API only (stateless session): first touch is stored on the cart and restored into the session so checkout attributes the order. */
    private function carryAttribution(?Cart $cart): ?Cart
    {
        if (! $cart || ! app()->bound('request') || ! request()->attributes->get('api_context')) {
            return $cart;
        }
        $sess = session();
        if ($cart->attribution) {
            foreach (['growth_attr' => 'attr', 'gift_session_id' => 'gift_session', 'bundle_ab' => 'bundle_ab'] as $key => $field) {
                $v = $field === 'attr' ? array_diff_key($cart->attribution, ['gift_session' => 1, 'bundle_ab' => 1]) : ($cart->attribution[$field] ?? null);
                $v && $sess->put($key, $v);
            }
        } elseif ($a = $sess->get('growth_attr')) {
            $cart->update(['attribution' => $a + array_filter(['gift_session' => $sess->get('gift_session_id'), 'bundle_ab' => $sess->get('bundle_ab')])]);
        }

        return $cart;
    }

    private function resolveCart(bool $create = true): ?Cart
    {
        $customerId = auth('web')->id();
        $token = session('cart_token');
        $guest = $token ? $this->carts->query()->whereNull('customer_id')->where('token', $token)->first() : null;

        if ($customerId) {
            $mine = $this->carts->query()->where('customer_id', $customerId)->first();
            if ($guest && $mine) {
                foreach ($guest->items as $i) {
                    $row = $mine->items()->firstOrNew(['bundle_id' => $i->bundle_id, 'product_id' => $i->product_id, 'product_variant_id' => $i->product_variant_id]);
                    $row->quantity = ($row->quantity ?? 0) + $i->quantity;
                    $row->save();
                }
                if ($guest->packaging_option_id && ! $mine->packaging_option_id) {
                    $mine->update(['packaging_option_id' => $guest->packaging_option_id]);
                }
                $guest->delete();
            } elseif ($guest) {
                $guest->update(['customer_id' => $customerId, 'token' => null]);
                $mine = $guest;
            }
            if ($mine || ! $create) {
                return $mine;
            }

            return $this->carts->create(['customer_id' => $customerId]);
        }

        if ($guest || ! $create) {
            return $guest;
        }
        $token = (string) Str::uuid();
        session(['cart_token' => $token]);

        return $this->carts->create(['token' => $token]);
    }

    public function add(int $productId, int $variantId, int $qty): Cart
    {
        $item = $this->prices->item($productId, $variantId) ?? throw ValidationException::withMessages(['product_id' => __('orders.item_unavailable')]);
        $cart = $this->cart();
        $row = $cart->items()->firstOrNew(['bundle_id' => 0, 'product_id' => $productId, 'product_variant_id' => $variantId]);
        $total = ($row->quantity ?? 0) + $qty;
        $this->assertStock($productId, $variantId, $total, $item['name']);
        $row->quantity = $total;
        $row->save();

        return $cart;
    }

    /** Add $qty bundles; refused when the bundle is not purchasable or the cart's component demand exceeds stock. */
    public function addBundle(int $bundleId, int $qty): Cart
    {
        $bundle = $this->bundles->live($bundleId);
        if (! $bundle || ! $this->bundles->components($bundle)) {
            throw ValidationException::withMessages(['bundle_id' => __('orders.item_unavailable')]);
        }
        $cart = $this->cart();
        // NULL product_id defeats the unique index, so serialise concurrent adds on the cart row (no duplicate bundle rows)
        DB::transaction(function () use ($cart, $bundle, $qty) {
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();
            $row = $cart->items()->firstOrNew(['bundle_id' => $bundle->id, 'product_id' => null, 'product_variant_id' => 0]);
            $this->assertBundleStock($cart, $bundle, ($row->quantity ?? 0) + $qty);
            $row->quantity = ($row->quantity ?? 0) + $qty;
            $row->save();
        });

        return $cart;
    }

    public function setQuantity(int $itemId, int $qty): Cart
    {
        $cart = $this->cart();
        $row = $cart->items()->findOrFail($itemId);
        if ($qty <= 0) {
            $row->delete();
        } elseif ($row->bundle_id > 0) {
            $bundle = $this->bundles->live($row->bundle_id);
            if (! $bundle) {
                throw ValidationException::withMessages(['quantity' => __('orders.item_unavailable')]);
            }
            $this->assertBundleStock($cart, $bundle, $qty);
            $row->update(['quantity' => $qty]);
        } else {
            $this->assertStock($row->product_id, $row->product_variant_id, $qty, $this->prices->item($row->product_id, $row->product_variant_id)['name'] ?? '');
            $row->update(['quantity' => $qty]);
        }

        return $cart;
    }

    public function clear(): void
    {
        $cart = $this->cart(false);
        $cart?->items()->delete();
        $cart?->update(['packaging_option_id' => null]);
    }

    /** The cart's chosen packaging option id, only while that option is still active (tenant scoped). */
    public function packagingOptionId(): ?int
    {
        $id = $this->cart(false)?->packaging_option_id;

        return $id && PackagingOption::active()->whereKey($id)->exists() ? (int) $id : null;
    }

    /** Persist the packaging choice on the cart (null = none). The option must be an active one of this tenant. */
    public function setPackaging(?int $optionId): void
    {
        if ($optionId && ! PackagingOption::active()->whereKey($optionId)->exists()) {
            throw ValidationException::withMessages(['packaging_option_id' => __('orders.packaging.unavailable')]);
        }
        $this->cart($optionId !== null)?->update(['packaging_option_id' => $optionId]);
    }

    /** @return array<int,array{product_id:int,variant_id:int,qty:int,bundle_id:int}> */
    public function lines(): array
    {
        return ($this->cart(false)?->items ?? collect())->map(fn ($i) => ['product_id' => (int) $i->product_id, 'variant_id' => (int) $i->product_variant_id, 'qty' => $i->quantity, 'bundle_id' => (int) $i->bundle_id])->all();
    }

    /** Cart rows with display data (id, name, qty, regular price); bundle rows carry is_bundle + components. */
    public function view(): array
    {
        $cart = $this->cart(false);
        if (! $cart) {
            return [];
        }

        return $cart->items()->with('bundle.items')->get()->map(function ($i) {
            if ($i->bundle_id > 0) {
                return $this->bundleRow($i);
            }
            $item = $this->prices->item($i->product_id, $i->product_variant_id);

            return ['id' => $i->id, 'product_id' => $i->product_id, 'variant_id' => $i->product_variant_id, 'bundle_id' => 0, 'is_bundle' => false, 'components' => [], 'qty' => $i->quantity,
                'name' => $item['name'] ?? null, 'unit_price' => $item['price'] ?? null, 'available' => (bool) $item,
                'slug' => $item['product']->slug ?? null, 'image' => isset($item['product']) ? ($item['variant']?->images->first()?->thumb_url ?? $item['product']->primaryImageUrl()) : null];
        })->all();
    }

    private function bundleRow($i): array
    {
        $bundle = $i->bundle;
        $live = $bundle && $bundle->isLive() ? $bundle : null;
        $components = $live ? $this->bundles->components($live) : null;

        return ['id' => $i->id, 'product_id' => 0, 'variant_id' => 0, 'bundle_id' => (int) $i->bundle_id, 'is_bundle' => true, 'qty' => $i->quantity,
            'name' => $bundle?->name, 'unit_price' => $bundle ? (string) $bundle->price : null, 'available' => (bool) $components, 'slug' => $bundle?->slug, 'image' => $bundle?->imageUrl(),
            'components' => collect($components ?? [])->map(fn ($c) => ['name' => $c['item']['name'], 'quantity' => $c['per']])->values()->all()];
    }

    /** Component demand of the whole cart (plain lines + every bundle) with $bundle set to $qty must fit the stock of each product/variant. */
    private function assertBundleStock(Cart $cart, $bundle, int $qty): void
    {
        if ($this->bundles->allowsBackorder()) {
            return;
        }
        $demand = [];
        $add = function (int $p, int $v, int $n) use (&$demand) {
            $demand["$p-$v"] = ($demand["$p-$v"] ?? 0) + $n;
        };
        foreach ($cart->items()->get() as $row) {
            if ($row->bundle_id > 0 && $row->bundle_id !== $bundle->id) {
                $b = $this->bundles->live($row->bundle_id);
                foreach ($b?->items ?? [] as $bi) {
                    $add($bi->product_id, $bi->product_variant_id, $bi->quantity * $row->quantity);
                }
            } elseif ($row->bundle_id == 0) {
                $add((int) $row->product_id, (int) $row->product_variant_id, $row->quantity);
            }
        }
        foreach ($bundle->items as $bi) {
            $add($bi->product_id, $bi->product_variant_id, $bi->quantity * $qty);
        }
        foreach ($bundle->items as $bi) {
            $available = $this->bundles->stock($bi->product_id, $bi->product_variant_id);
            $need = $demand[$bi->product_id.'-'.$bi->product_variant_id];
            if ($need > $available) {
                throw ValidationException::withMessages(['quantity' => __('orders.bundle_insufficient_stock', ['max' => max(0, intdiv(max(0, $available - ($need - $bi->quantity * $qty)), max(1, $bi->quantity)))])]);
            }
        }
    }

    private function assertStock(int $productId, int $variantId, int $qty, string $name): void
    {
        if (current_tenant()?->settings['allow_backorder'] ?? false) {
            return;
        }
        $available = (int) StockLevel::where('product_id', $productId)->where('product_variant_id', $variantId)
            ->whereHas('branch', fn ($b) => $b->where('is_active', true))->selectRaw('COALESCE(SUM(on_hand - reserved),0) a')->value('a');
        if ($qty > $available) {
            throw ValidationException::withMessages(['quantity' => __('ops.insufficient_stock', ['available' => max(0, $available), 'qty' => $qty])]);
        }
    }
}
