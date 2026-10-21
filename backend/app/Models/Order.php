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
