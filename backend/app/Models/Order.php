<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProofStatus;
use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use Audited, BelongsToTenant, SoftDeletes;

    protected $fillable = ['number', 'public_token', 'customer_id', 'customer_name', 'customer_phone', 'customer_email', 'city_id', 'address', 'notes', 'branch_id', 'shipping_company_id', 'payment_method_id', 'prepay_method_id', 'cod_amount', 'prepaid_shipping', 'discount_code_id', 'affiliate_id', 'status', 'payment_status', 'payment_proof', 'proof_status', 'gateway_reference', 'payment_attempts', 'subtotal', 'discount', 'shipping', 'shipping_discount', 'shipping_net', 'shipping_cost', 'tax', 'payment_fee', 'payment_fee_charged', 'cod_fee', 'cod_fee_label', 'total', 'paid_amount', 'refunded_amount', 'cogs', 'affiliate_commission', 'net_profit', 'currency', 'stock_deducted', 'placed_at', 'delivered_at', 'carrier_settled_at', 'cancelled_at'];

    /** Kept out of the audit log: guest-access secret, uploaded proof path and customer PII (customer_id stays for traceability). */
    protected array $auditExcept = ['public_token', 'payment_proof', 'customer_name', 'customer_phone', 'customer_email', 'address'];

    protected function casts(): array
    {
        $dec = array_fill_keys(['subtotal', 'discount', 'shipping', 'shipping_discount', 'shipping_net', 'shipping_cost', 'tax', 'payment_fee', 'payment_fee_charged', 'cod_fee',
            'total', 'paid_amount', 'refunded_amount', 'cogs', 'affiliate_commission', 'net_profit'], 'decimal:2');

        return $dec + ['cod_fee_label' => 'array', 'status' => OrderStatus::class, 'payment_status' => PaymentStatus::class, 'proof_status' => ProofStatus::class,
            'stock_deducted' => 'bool', 'placed_at' => 'datetime', 'delivered_at' => 'datetime', 'carrier_settled_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** Snapshot label of the COD fee line in the current locale. */
    public function codFeeLabel(): string
    {
        $l = (array) $this->cod_fee_label;

        return $l[app()->getLocale()] ?? $l[config('app.fallback_locale')] ?? (reset($l) ?: __('orders.cod_fee_default'));
    }

    /** Prices included VAT: tax > 0 and it was NOT added on top of the goods (CALCULATIONS.md step 3). */
    public function taxIncluded(): bool
    {
        if (\App\Support\Money::cmp((string) $this->tax, '0') <= 0) {
            return false;
        }
        $goods = \App\Support\Money::sub((string) $this->subtotal, (string) $this->discount);
        $added = \App\Support\Money::sub((string) $this->total, \App\Support\Money::add($goods, (string) $this->shipping_net, (string) $this->payment_fee_charged, (string) $this->cod_fee));

        return \App\Support\Money::cmp($added, (string) $this->tax) < 0;
    }

    /** revenue_goods = subtotal − discount − tax_in_goods (VAT is never revenue). */
    public function revenueGoods(): string
    {
        $goods = \App\Support\Money::sub((string) $this->subtotal, (string) $this->discount);

        return $this->taxIncluded() ? \App\Support\Money::sub($goods, (string) $this->tax) : $goods;
    }

    /** Cumulative tax portion of returned goods value $value given $prior already-counted returned value: round(tax×(P+v)/B) − round(tax×P/B). */
    public function returnTax(string $value, string $prior = '0'): string
    {
        $base = \App\Support\Money::sub((string) $this->subtotal, (string) $this->discount);
        if (\App\Support\Money::cmp((string) $this->tax, '0') <= 0 || \App\Support\Money::cmp($base, '0') <= 0) {
            return '0.00';
        }
        $at = fn (string $v) => \App\Support\Money::round(bcdiv(bcmul((string) $this->tax, $v, 8), $base, 8));

        return \App\Support\Money::sub($at(\App\Support\Money::add($prior, $value)), $at($prior));
    }

    /** Returned value net of VAT: inclusive orders remove the (cumulative) VAT inside it; exclusive orders return it unchanged. */
    public function returnNetOfTax(string $value, string $prior = '0'): string
    {
        return $this->taxIncluded() ? \App\Support\Money::sub($value, $this->returnTax($value, $prior)) : \App\Support\Money::round($value);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function city()
    {
        return $this->belongsTo(City::class)->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function shippingCompany()
    {
        return $this->belongsTo(ShippingCompany::class)->withTrashed();
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class)->withTrashed();
    }

    public function prepayMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'prepay_method_id')->withTrashed();
    }

    /** The method the customer pays through right now: the prepay method for COD-with-upfront-shipping, else the order's method. */
    public function payingMethod(): ?PaymentMethod
    {
        return $this->prepay_method_id ? $this->prepayMethod : $this->paymentMethod;
    }

    /** Amount the customer must pay online / by wallet now (prepaid shipping for COD-upfront, else the total). */
    public function payableNow(): string
    {
        return $this->prepay_method_id ? (string) $this->prepaid_shipping : (string) $this->total;
    }

    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class)->withTrashed();
    }

    public function returns()
    {
        return $this->hasMany(OrderReturn::class)->latest('id');
    }

    public function due(): string
    {
        return \App\Support\Money::sub((string) $this->total, (string) $this->paid_amount);
    }
}
