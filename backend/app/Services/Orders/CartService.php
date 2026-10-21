<?php

namespace App\Services\Orders;

use App\Models\Cart;
use App\Models\StockLevel;
use App\Repositories\Contracts\CartRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** DB-backed cart keyed by customer (web guard) or by a guest session token; guest cart merges into the customer's on login. */
class CartService
{
    public function __construct(private CartRepositoryInterface $carts, private PriceResolver $prices) {}

    public function cart(bool $create = true): ?Cart
    {
        $customerId = auth('web')->id();
        $token = session('cart_token');
        $guest = $token ? $this->carts->query()->whereNull('customer_id')->where('token', $token)->first() : null;

        if ($customerId) {
            $mine = $this->carts->query()->where('customer_id', $customerId)->first();
            if ($guest && $mine) {
                foreach ($guest->items as $i) {
                    $row = $mine->items()->firstOrNew(['product_id' => $i->product_id, 'product_variant_id' => $i->product_variant_id]);
                    $row->quantity = ($row->quantity ?? 0) + $i->quantity;
                    $row->save();
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
        $row = $cart->items()->firstOrNew(['product_id' => $productId, 'product_variant_id' => $variantId]);
        $total = ($row->quantity ?? 0) + $qty;
        $this->assertStock($productId, $variantId, $total, $item['name']);
        $row->quantity = $total;
        $row->save();

        return $cart;
    }

    public function setQuantity(int $itemId, int $qty): Cart
    {
        $cart = $this->cart();
        $row = $cart->items()->findOrFail($itemId);
        if ($qty <= 0) {
            $row->delete();
        } else {
            $this->assertStock($row->product_id, $row->product_variant_id, $qty, $this->prices->item($row->product_id, $row->product_variant_id)['name'] ?? '');
            $row->update(['quantity' => $qty]);
        }

        return $cart;
    }

    public function clear(): void
    {
        $this->cart(false)?->items()->delete();
    }

    /** @return array<int,array{product_id:int,variant_id:int,qty:int}> */
    public function lines(): array
    {
        return ($this->cart(false)?->items ?? collect())->map(fn ($i) => ['product_id' => $i->product_id, 'variant_id' => $i->product_variant_id, 'qty' => $i->quantity])->all();
    }

    /** Cart rows with display data (id, name, qty, regular price). */
    public function view(): array
    {
        return ($this->cart(false)?->items ?? collect())->map(function ($i) {
            $item = $this->prices->item($i->product_id, $i->product_variant_id);

            return ['id' => $i->id, 'product_id' => $i->product_id, 'variant_id' => $i->product_variant_id, 'qty' => $i->quantity,
                'name' => $item['name'] ?? null, 'unit_price' => $item['price'] ?? null, 'available' => (bool) $item,
                'slug' => $item['product']->slug ?? null, 'image' => isset($item['product']) ? ($item['variant']?->images->first()?->thumb_url ?? $item['product']->primaryImageUrl()) : null];
        })->all();
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
