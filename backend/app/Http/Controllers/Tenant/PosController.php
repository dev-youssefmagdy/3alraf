<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Orders\CheckoutData;
use App\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PosCustomerRequest;
use App\Http\Requests\Tenant\PosOrderRequest;
use App\Models\City;
use App\Models\Customer;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\ShippingRate;
use App\Models\StockLevel;
use App\Models\TenantProduct;
use App\Services\Orders\OrderPaymentService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderQuoteService;
use App\Services\Orders\PriceResolver;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

/** Point of sale: staff build an order for a customer (searchable / quick-created) from the tenant catalog and place it through the storefront pipeline. */
class PosController extends Controller implements HasMiddleware
{
    public function __construct(private OrderQuoteService $quotes, private OrderPlacementService $placement, private PriceResolver $prices, private OrderPaymentService $payments) {}

    public static function middleware(): array
    {
        return [new Middleware('can:pos.use')];
    }

    public function index()
    {
        $methods = PaymentMethod::available()->whereIn('type', [PaymentMethodType::COD->value, PaymentMethodType::WALLET->value])->orderBy('sort_order')->get();

        return view('tenant.pos.index', [
            'cities' => City::where('is_active', true)->orderBy('sort_order')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'methods' => $methods->mapWithKeys(fn ($m) => [$m->id => $m->name])->all(),
            'prepayMethods' => $methods->where('type', PaymentMethodType::WALLET)->mapWithKeys(fn ($m) => [$m->id => $m->name])->all(),
            'packaging' => PackagingOption::active()->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->all(),
            'phoneCode' => Phone::tenantCode(),
        ]);
    }

    /** Product / variant search by name, SKU or barcode (empty query = latest products). */
    public function products(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        $tps = TenantProduct::with(['product.variants.values'])->where('is_active', true)
            ->whereHas('product', function ($p) use ($q) {
                $p->where('is_active', true);
                if ($q !== '') {
                    $like = '%'.addcslashes($q, '%_\\').'%';
                    $p->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like)
                        ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)->orWhere('barcode', 'like', $like)));
                }
            })->latest('id')->limit(24)->get();

        $rows = [];
        foreach ($tps as $tp) {
            $p = $tp->product;
            foreach ($p->isVariable() ? $p->variants->where('is_active', true)->pluck('id') : [0] as $vid) {
                if ($item = $this->prices->item($p->id, (int) $vid)) {
                    $rows[] = ['product_id' => $p->id, 'variant_id' => (int) $vid, 'key' => $p->id.'-'.$vid, 'name' => $item['name'], 'sku' => $item['sku'],
                        'price' => $item['price'], 'image' => $p->primaryImageUrl()];
                }
            }
        }
        $stock = StockLevel::query()->whereIn('product_id', array_unique(array_column($rows, 'product_id')))
            ->whereHas('branch', fn ($b) => $b->where('is_active', true))
            ->selectRaw('product_id, product_variant_id, SUM(on_hand - reserved) as free_qty')->groupBy('product_id', 'product_variant_id')->get()
            ->mapWithKeys(fn ($s) => [$s->product_id.'-'.$s->product_variant_id => (int) $s->free_qty]);

        return response()->json(['items' => array_map(fn ($r) => $r + ['stock' => (int) ($stock[$r['key']] ?? 0)], $rows)]);
    }

    /** Customer search (name / phone / e-mail) for the Tom Select remote loader. */
    public function customers(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        $query = Customer::query()->with(['addresses' => fn ($a) => $a->limit(1)])->withCount('orders')->latest('id')->limit(20);
        if ($q !== '') {
            $like = '%'.addcslashes(Phone::ascii($q), '%_\\').'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
        }

        return response()->json(['items' => $query->get()->map(fn ($c) => $this->customerRow($c))->all()]);
    }

    public function storeCustomer(PosCustomerRequest $r): JsonResponse
    {
        $c = Customer::create([
            'name' => $r->input('name'), 'phone' => $r->input('phone'), 'phone_country_code' => $r->input('phone_country_code'),
            'email' => $r->filled('email') ? $r->input('email') : 'pos-'.Str::lower(Str::random(12)).'@pos.invalid', // column is NOT NULL + unique; placeholder never receives mail
            'password' => Str::random(40), // no storefront login until the customer uses "forgot password"
            'is_active' => true,
        ]);

        return $this->ok(__('pos.customer_created'), null, ['customer' => $this->customerRow($c->loadCount('orders'))]);
    }

    /** Shipping companies for the city, each with the order total it would produce for this cart. */
    public function shipping(PosOrderRequest $r): JsonResponse
    {
        $out = [];
        $cityId = (int) $r->input('city_id');
        if ($cityId && $r->filled('items')) {
            $lines = $this->lines($r);
            $rates = ShippingRate::with('company')->where('city_id', $cityId)->whereHas('company', fn ($q) => $q->where('is_active', true))->get()->sortBy('company.sort_order');
            foreach ($rates as $rate) {
                $q = $this->quotes->quote($lines, new CheckoutData(cityId: $cityId, shippingCompanyId: $rate->shipping_company_id));
                $out[] = ['id' => $rate->shipping_company_id, 'name' => $rate->company->name, 'eta' => $rate->etaLabel(), 'supports_cod' => $rate->supports_cod,
                    'fee' => $q['totals']->shippingNet, 'total' => $q['totals']->total];
            }
        }

        return $this->ok(null, null, ['options' => array_values($out)]);
    }

    /** Live totals (customer-safe figures only). */
    public function quote(PosOrderRequest $r): JsonResponse
    {
        $q = $this->quotes->quote($this->lines($r), $this->data($r));
        $t = $q['totals'];

        return $this->ok(null, null, [
            'totals' => $t->forCustomer(),
            'discount_code' => $q['discount']?->code,
            'needs_proof' => $this->payments->offlineAtCheckout($q['method'], $q['prepay'], $t->prepaidShipping),
            'needs_prepay' => $q['method']?->type === PaymentMethodType::COD && bccomp($t->prepaidShipping, '0', 2) > 0,
            'lines' => collect($q['lines'])->map(fn ($l) => ['name' => $l['name'], 'qty' => $l['qty'], 'unit_price' => $l['unit_price']])->all(),
        ]);
    }

    public function store(PosOrderRequest $r): JsonResponse
    {
        $res = $this->placement->place($this->data($r), $this->lines($r));

        return $this->ok(__('pos.order_created', ['number' => $res['order']->number]), route('tenant.orders.show', $res['order']->id));
    }

    /** @return array<int,array{product_id:int,variant_id:int,qty:int}> same product/variant rows merged */
    private function lines(PosOrderRequest $r): array
    {
        $merged = [];
        foreach ((array) $r->input('items', []) as $i) {
            $k = (int) $i['product_id'].'-'.(int) ($i['variant_id'] ?? 0);
            $merged[$k] = ['product_id' => (int) $i['product_id'], 'variant_id' => (int) ($i['variant_id'] ?? 0), 'qty' => ($merged[$k]['qty'] ?? 0) + (int) $i['qty']];
        }

        return array_values($merged);
    }

    private function data(PosOrderRequest $r): CheckoutData
    {
        $customer = $r->filled('customer_id') ? Customer::find((int) $r->input('customer_id')) : null;
        $email = $r->input('email') ?: $customer?->email;

        return new CheckoutData(
            $customer?->id, (string) $r->input('name', $customer?->name ?? ''), (string) $r->input('phone', $customer?->phone ?? ''),
            ($email && ! str_ends_with($email, '@pos.invalid')) ? $email : null,
            $r->filled('city_id') ? (int) $r->input('city_id') : null, (string) $r->input('address', ''), $r->input('notes'),
            $r->filled('shipping_company_id') ? (int) $r->input('shipping_company_id') : null,
            $r->filled('payment_method_id') ? (int) $r->input('payment_method_id') : null,
            $r->filled('discount_code') ? trim($r->input('discount_code')) : null, null,
            $r->filled('prepay_method_id') ? (int) $r->input('prepay_method_id') : null, $r->file('proof'),
            $r->filled('packaging_option_id') ? (int) $r->input('packaging_option_id') : null,
            $r->input('phone_country_code'),
        );
    }

    private function customerRow(Customer $c): array
    {
        $a = $c->addresses->first();
        $email = $c->email && ! str_ends_with($c->email, '@pos.invalid') ? $c->email : null;

        return ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'phone_display' => $c->phone_display, 'email' => $email, 'orders' => (int) ($c->orders_count ?? 0),
            'city_id' => $a?->city_id, 'address' => $a?->address];
    }
}
