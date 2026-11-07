<?php

namespace Database\Seeders;

use App\DTOs\Orders\CheckoutData;
use App\Enums\BannerPlacement;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Review;
use App\Models\ShippingCompany;
use App\Models\Setting;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Stock\StockLedger;
use Database\Seeders\Support\PlaceholderImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Storefront demo: listed products + prices + stock, banners, flash sale, sample orders & reviews. Runs after Operations/Orders seeders. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        app()->setLocale((string) setting('default_locale', 'ar'));
        $this->products();
        $this->banners();
        $this->flash();
        $this->orders();
    }

    private function products(): void
    {
        if (Product::whereNotNull('price')->exists()) {
            return;
        }
        $rate = setting('currency_code') === 'SAR' ? 0.1 : 1; // catalog base prices are EGP
        $ledger = app(StockLedger::class);
        $branch = Branch::where('is_default', true)->first() ?? Branch::first();
        foreach (Product::with('variants')->orderBy('id')->get() as $i => $p) {
            $price = round(($this->basePrice($p) * $rate) / 5) * 5 ?: 5;
            $p->update(['price' => $price, 'sale_price' => $i % 5 === 0 ? round($price * .9) : null, 'cost' => round($price * .7, 2), 'is_active' => true]);
            $items = $p->isVariable() ? $p->variants : collect([null]);
            foreach ($items as $v) {
                $ledger->record($branch->id, $p->id, $v?->id ?? 0, StockMovementType::PURCHASE_IN, 40 + ($i % 5) * 10, (string) $p->cost, null, 'Demo opening stock');
            }
        }
    }

    /** Base (EGP) price is derived from the catalog seeder list via a stable per-product map. */
    private function basePrice(Product $p): float
    {
        static $map;
        $map ??= (function () {
            $r = new \ReflectionClass(CatalogSeeder::class);
            $rows = $r->getProperty('products')->getValue(new CatalogSeeder);

            return collect($rows)->mapWithKeys(fn ($x) => [Str::slug($x[1]) => $x[4]])->all();
        })();

        return (float) ($map[$p->slug] ?? 500);
    }

    private function banners(): void
    {
        if (Banner::exists()) {
            return;
        }
        $defs = [
            [BannerPlacement::HOME_HERO, 'Mega Appliances Sale', 'تخفيضات الأجهزة', 1600, 520, '/ar/category/appliances'],
            [BannerPlacement::HOME_HERO, 'New Mobiles Arrived', 'موبايلات جديدة', 1600, 520, '/ar/category/mobiles'],
            [BannerPlacement::HOME_HERO, 'Fashion Week', 'أسبوع الأزياء', 1600, 520, '/ar/category/fashion'],
            [BannerPlacement::HOME_STRIP, 'Free shipping over 1000', 'شحن مجاني', 1600, 300, '/ar/search'],
            [BannerPlacement::HOME_STRIP, 'Pay on delivery', 'الدفع عند الاستلام', 1600, 300, '/ar/search'],
            [BannerPlacement::CATEGORY_TILE, 'Furniture', 'الأثاث', 800, 600, '/ar/category/furniture'],
            [BannerPlacement::CATEGORY_TILE, 'Home Textiles', 'المفروشات', 800, 600, '/ar/category/home-textiles'],
            [BannerPlacement::CATEGORY_TILE, 'Beauty', 'الجمال', 800, 600, '/ar/category/beauty'],
            [BannerPlacement::CATEGORY_TILE, 'Auto Tools', 'أدوات السيارات', 800, 600, '/ar/category/auto-tools'],
        ];
        foreach ($defs as $i => [$place, $en, $ar, $w, $h, $link]) {
            $images = [];
            foreach (['ar', 'en'] as $loc) {
                $images[$loc] = PlaceholderImage::banner('banners', 'demo-'.$i.'-'.$loc, $en, $w, $h, $i);
            }
            Banner::create(['placement' => $place, 'title' => ['ar' => $ar, 'en' => $en], 'images' => $images, 'link_url' => $link, 'sort_order' => $i, 'is_active' => true]);
        }
    }

    private function flash(): void
    {
        if (FlashSale::exists()) {
            return;
        }
        $fs = FlashSale::create(['name' => ['ar' => 'عرض اليوم', 'en' => 'Deal of the day'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addDays(30), 'is_active' => true]);
        foreach (Product::where('type', 'simple')->whereNotNull('price')->orderBy('id')->limit(4)->get() as $tp) {
            FlashSaleItem::create(['flash_sale_id' => $fs->id, 'product_id' => $tp->id, 'product_variant_id' => 0, 'discount_type' => 'percent', 'value' => 15, 'qty_limit' => 20]);
        }
    }

    private function orders(): void
    {
        if (Order::exists()) {
            return;
        }
        $placer = app(OrderPlacementService::class);
        $status = app(OrderStatusService::class);
        $customer = Customer::where('email', 'customer@egy.test')->first();
        $city = City::orderBy('id')->first();
        $company = ShippingCompany::first();
        $cod = PaymentMethod::where('type', 'cod')->first();
        $tps = Product::with('variants')->whereNotNull('price')->orderBy('id')->get();
        // Plain COD demo orders need the carrier-collects-everything mode; restore the store's own setting afterwards.
        $before = setting('cod_collect_shipping_upfront');
        Setting::put('cod_collect_shipping_upfront', false);
        $path = [OrderStatus::CONFIRMED, OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED];
        // final status index: -1 pending, 0..3 along $path, 9 cancelled
        $plans = [3, 3, 3, 3, 2, 1, 0, -1, -1, 9];
        foreach ($plans as $n => $final) {
            $lines = [];
            foreach ([$n * 3 % $tps->count(), ($n * 3 + 7) % $tps->count()] as $idx) {
                $p = $tps[$idx];
                $lines[] = ['product_id' => $p->id, 'variant_id' => $p->isVariable() ? $p->variants->first()->id : 0, 'qty' => 1 + $n % 2];
            }
            $d = new CheckoutData($customer?->id, $customer?->name ?? 'Demo', '0100000000'.$n, $customer?->email, $city->id, 'Demo street '.$n, null, $company->id, $cod->id);
            $order = $placer->place($d, $lines)['order'];
            // Plain COD demo orders: a prepay method would (rightly) require an uploaded proof, and money columns stay as the calculator set them.
            if ($final === 9) {
                $status->change($order, OrderStatus::CANCELLED, 'Demo cancel');
            } else {
                foreach (array_slice($path, 0, $final + 1) as $st) {
                    $status->change($order, $st);
                }
            }
            $when = now()->subDays(12 - $n)->subHours($n);
            $order->forceFill(['placed_at' => $when, 'created_at' => $when])->save();
            if ($final === 3 && $customer && $n < 3) {
                $pid = $order->items->first()->product_id;
                Review::firstOrCreate(['product_id' => $pid, 'customer_id' => $customer->id], ['order_id' => $order->id, 'rating' => 5 - $n % 2, 'body' => 'Great product, fast delivery.', 'status' => 'approved']);
            }
        }
        $before === null ? Setting::query()->where('key', 'cod_collect_shipping_upfront')->delete() : Setting::put('cod_collect_shipping_upfront', $before);
    }
}
