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
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Stock\StockLedger;
use Database\Seeders\Support\PlaceholderImage;
use Illuminate\Database\Seeder;

/** Per-tenant storefront demo: enabled products + prices + stock, banners, flash sale, sample orders & reviews. Runs after Operations/Orders seeders. */
class TenantDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::whereIn('slug', ['egy', 'sa'])->get() as $t) {
            app()->instance('currentTenant', $t);
            app()->setLocale($t->default_locale);
            $this->products($t);
            $this->banners($t);
            $this->flash($t);
            $this->orders($t);
            app()->forgetInstance('currentTenant');
        }
    }

    private function products(Tenant $t): void
    {
        if (TenantProduct::exists()) {
            return;
        }
        $rate = $t->slug === 'sa' ? 0.1 : 1;
        $ledger = app(StockLedger::class);
        $branch = Branch::where('is_default', true)->first() ?? Branch::first();
        foreach (Product::with('variants')->orderBy('id')->get() as $i => $p) {
            $price = round(($this->basePrice($p) * $rate) / 5) * 5 ?: 5;
            $tp = TenantProduct::create(['product_id' => $p->id, 'price' => $price, 'sale_price' => $i % 5 === 0 ? round($price * .9) : null, 'cost' => round($price * .7, 2), 'is_active' => true]);
            $items = $p->isVariable() ? $p->variants : collect([null]);
            foreach ($items as $v) {
                if ($v) {
                    TenantProductVariant::create(['tenant_product_id' => $tp->id, 'product_variant_id' => $v->id, 'is_active' => true]);
                }
                $ledger->record($branch->id, $p->id, $v?->id ?? 0, StockMovementType::PURCHASE_IN, 40 + ($i % 5) * 10, (string) $tp->cost, null, 'Demo opening stock');
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

            return collect($rows)->mapWithKeys(fn ($x) => [\Illuminate\Support\Str::slug($x[1]) => $x[4]])->all();
        })();

        return (float) ($map[$p->slug] ?? 500);
    }

    private function banners(Tenant $t): void
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
                $images[$loc] = PlaceholderImage::banner('banners', $t->slug.'-'.$i.'-'.$loc, $en, $w, $h, $i);
            }
            Banner::create(['placement' => $place, 'title' => ['ar' => $ar, 'en' => $en], 'images' => $images, 'link_url' => $link, 'sort_order' => $i, 'is_active' => true]);
        }
    }

    private function flash(Tenant $t): void
    {
        if (FlashSale::exists()) {
            return;
        }
        $fs = FlashSale::create(['name' => ['ar' => 'عرض اليوم', 'en' => 'Deal of the day'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addDays(30), 'is_active' => true]);
        foreach (TenantProduct::whereHas('product', fn ($q) => $q->where('type', 'simple'))->orderBy('id')->limit(4)->get() as $tp) {
            FlashSaleItem::create(['tenant_id' => $t->id, 'flash_sale_id' => $fs->id, 'product_id' => $tp->product_id, 'product_variant_id' => 0, 'discount_type' => 'percent', 'value' => 15, 'qty_limit' => 20]);
        }
    }

    private function orders(Tenant $t): void
    {
        if (Order::exists()) {
            return;
        }
        $placer = app(OrderPlacementService::class);
        $status = app(OrderStatusService::class);
        $customer = Customer::where('email', "customer@{$t->slug}.test")->first();
        $city = City::orderBy('id')->first();
        $company = ShippingCompany::first();
        $cod = PaymentMethod::where('type', 'cod')->first();
        $wallet = PaymentMethod::where('type', 'wallet')->first(); // demo: shipping prepaid by an (already approved) wallet transfer
        $tps = TenantProduct::with('product.variants')->orderBy('id')->get();
        $path = [OrderStatus::CONFIRMED, OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED];
        // final status index: -1 pending, 0..3 along $path, 9 cancelled
        $plans = [3, 3, 3, 3, 2, 1, 0, -1, -1, 9];
        foreach ($plans as $n => $final) {
            $lines = [];
            foreach ([$n * 3 % $tps->count(), ($n * 3 + 7) % $tps->count()] as $idx) {
                $p = $tps[$idx]->product;
                $lines[] = ['product_id' => $p->id, 'variant_id' => $p->isVariable() ? $p->variants->first()->id : 0, 'qty' => 1 + $n % 2];
            }
            $d = new CheckoutData($customer?->id, $customer?->name ?? 'Demo', '0100000000'.$n, $customer?->email, $city->id, 'Demo street '.$n, null, $company->id, $cod->id, prepayMethodId: $wallet?->id);
            $order = $placer->place($d, $lines)['order'];
            if ($order->prepay_method_id) {
                $order->forceFill(['proof_status' => 'approved', 'payment_status' => 'partially_paid', 'paid_amount' => $order->prepaid_shipping])->save();
            }
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
    }
}
