<?php

namespace Database\Seeders\Support;

use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\PaymentMethod;
use App\Models\ShippingCompany;
use App\Models\StaticPage;
use App\Models\Supplier;
use Database\Seeders\GiftCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Removes the fake rows the dev seeders (FoundationSeeder, OperationsSeeder, OrdersSeeder, MarketingDemoSeeder, GiftCatalogSeeder opening stock) create, and only those:
 * every row is matched by the exact marker the seeder writes (demo e-mail + the still-unchanged demo password, "Demo Supplier", WELCOME10, the placeholder
 * page text, the seeded opening-stock note...). Anything a person changed or that real activity references (orders, purchases, later stock movements) is kept.
 * The store row, branches, cities, countries, the catalog and the default COD method are never touched.
 */
class DemoDataPurger
{
    /** Demo admin logins (FoundationSeeder) - removed only while they still use the demo password. */
    public const DEMO_ADMIN_EMAILS = ['admin@3alraf.test', 'owner@egy.test', 'owner@sa.test'];

    /** Demo customer / affiliate e-mails (OrdersSeeder / MarketingDemoSeeder; `sa` = the former second demo store). */
    private const DEMO_CUSTOMERS = ['customer@egy.test', 'customer@sa.test'];

    private const DEMO_AFFILIATES = ['aff@egy.test', 'aff@sa.test'];

    private const DEMO_PASSWORD = 'password';

    /** @var array<string,int> */
    private array $found = [];

    /** @return array<string,int> what was found (apply = false) or removed (apply = true), by label; empty when the database is clean */
    public function run(bool $apply): array
    {
        $this->found = [];
        DB::transaction(function () use ($apply) {
            $this->admins($apply);
            $this->customers($apply);
            $this->discountCodes($apply);
            $this->affiliates($apply);
            $this->suppliers($apply);
            $this->shippingCompanies($apply);
            $this->paymentMethods($apply);
            $this->staticPages($apply);
            $this->openingStock($apply);
        });

        return $this->found;
    }

    private function count(string $label, int $n): void
    {
        $n && $this->found[$label] = ($this->found[$label] ?? 0) + $n;
    }

    private function demoLogin($admin): bool
    {
        return Hash::check(self::DEMO_PASSWORD, (string) $admin->password);
    }

    private function dropAdmin($admin): void
    {
        $admin->roles()->detach();
        $admin->permissions()->detach();
        method_exists($admin, 'tokens') && $admin->tokens()->delete();
        $admin->delete();
    }

    private function admins(bool $apply): void
    {
        $admins = Admin::query()->whereIn('email', self::DEMO_ADMIN_EMAILS)->get()->filter(fn ($a) => $this->demoLogin($a));
        $this->count('demo admins (admin@3alraf.test / owner@egy.test)', $admins->count());
        $apply && $admins->each(fn ($a) => $this->dropAdmin($a));
    }

    private function customers(bool $apply): void
    {
        $rows = Customer::query()->withTrashed()->whereIn('email', self::DEMO_CUSTOMERS)->get()
            ->reject(fn ($c) => DB::table('orders')->where('customer_id', $c->id)->exists());
        $this->count('demo customers', $rows->count());
        $apply && $rows->each->forceDelete();
    }

    private function discountCodes(bool $apply): void
    {
        $rows = DiscountCode::query()->withTrashed()->where('code', 'WELCOME10')->get()
            ->reject(fn ($d) => DB::table('discount_code_usages')->where('discount_code_id', $d->id)->exists() || DB::table('orders')->where('discount_code_id', $d->id)->exists());
        $this->count('demo discount code WELCOME10', $rows->count());
        $apply && $rows->each->forceDelete();
    }

    private function affiliates(bool $apply): void
    {
        $rows = Affiliate::query()->whereIn('email', self::DEMO_AFFILIATES)->get()
            ->reject(fn ($a) => DB::table('orders')->where('affiliate_id', $a->id)->exists());
        $this->count('demo affiliates', $rows->count());
        $apply && $rows->each(function ($a) {
            $a->tokens()->delete();
            $a->delete();
        });
    }

    private function suppliers(bool $apply): void
    {
        $rows = Supplier::query()->withTrashed()->where('name', 'Demo Supplier')->get()
            ->reject(fn ($s) => DB::table('purchase_orders')->where('supplier_id', $s->id)->exists());
        $this->count('demo supplier', $rows->count());
        $apply && $rows->each->forceDelete();
    }

    private function shippingCompanies(bool $apply): void
    {
        $rows = ShippingCompany::query()->withTrashed()->get()
            ->filter(fn ($c) => ($c->getTranslations('name')['en'] ?? null) === 'Fast Shipping')
            ->reject(fn ($c) => DB::table('orders')->where('shipping_company_id', $c->id)->exists());
        $this->count('demo shipping company (+ its rates)', $rows->count());
        $apply && $rows->each->forceDelete();
    }

    /** The seeded wallet / bank / sandbox-card methods; the COD method is the store's real default and stays. */
    private function paymentMethods(bool $apply): void
    {
        $demoAccounts = ['01000000000', 'demo@instapay', 'SA00DEMO'];
        $rows = PaymentMethod::query()->withTrashed()->where('type', '!=', 'cod')->get()
            ->filter(fn ($m) => in_array($m->account_number, $demoAccounts, true)
                || ($m->is_sandbox && ! $m->is_active && in_array($m->provider->value, ['paymob', 'stripe'], true) && (float) $m->fee_percent === 2.5 && (float) $m->fee_fixed === 1.0))
            ->reject(fn ($m) => DB::table('orders')->where('payment_method_id', $m->id)->exists());
        $this->count('demo payment methods (wallets / bank / sandbox cards)', $rows->count());
        $apply && $rows->each->forceDelete();
    }

    /** Only pages still holding the seeder's placeholder text ("محتوى …" / "Content of …"); a page someone wrote is kept. */
    private function staticPages(bool $apply): void
    {
        $rows = StaticPage::query()->whereIn('slug', ['privacy-policy', 'terms', 'about'])->get()
            ->filter(function ($p) {
                $title = $p->getTranslations('title');

                return $p->getTranslations('content') === ['ar' => 'محتوى '.($title['ar'] ?? ''), 'en' => 'Content of '.($title['en'] ?? '')];
            });
        $this->count('placeholder static pages (privacy / terms / about)', $rows->count());
        $apply && $rows->each->delete();
    }

    /** The gift-catalog opening stock: the movement and its level go together, but only while that movement is the product's whole history. */
    private function openingStock(bool $apply): void
    {
        $seed = DB::table('stock_movements as m')->where('m.note', GiftCatalogSeeder::OPENING_NOTE)
            ->whereNotExists(fn ($q) => $q->from('stock_movements as o')->whereColumn('o.branch_id', 'm.branch_id')
                ->whereColumn('o.product_id', 'm.product_id')->whereColumn('o.product_variant_id', 'm.product_variant_id')->whereColumn('o.id', '!=', 'm.id'))
            ->select('m.id', 'm.branch_id', 'm.product_id', 'm.product_variant_id');
        $this->count('opening stock entries (gift catalog qty)', (clone $seed)->count());
        if (! $apply) {
            return;
        }
        $seed->orderBy('m.id')->chunk(500, function ($chunk) {
            foreach ($chunk as $m) {
                DB::table('stock_levels')->where(['branch_id' => $m->branch_id, 'product_id' => $m->product_id, 'product_variant_id' => $m->product_variant_id])->delete();
            }
            DB::table('stock_movements')->whereIn('id', $chunk->pluck('id'))->delete();
        });
    }
}
