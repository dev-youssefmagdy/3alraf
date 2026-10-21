<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProofStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToTenant;

    protected $fillable = ['number', 'public_token', 'customer_id', 'customer_name', 'customer_phone', 'customer_email', 'city_id', 'address', 'notes', 'branch_id', 'shipping_company_id', 'payment_method_id', 'discount_code_id', 'affiliate_id', 'status', 'payment_status', 'payment_proof', 'proof_status', 'gateway_reference', 'subtotal', 'discount', 'shipping', 'shipping_discount', 'shipping_net', 'shipping_cost', 'tax', 'payment_fee', 'payment_fee_charged', 'total', 'paid_amount', 'refunded_amount', 'cogs', 'affiliate_commission', 'net_profit', 'currency', 'stock_deducted', 'placed_at', 'delivered_at', 'cancelled_at'];

    protected function casts(): array
    {
        $dec = array_fill_keys(['subtotal', 'discount', 'shipping', 'shipping_discount', 'shipping_net', 'shipping_cost', 'tax', 'payment_fee', 'payment_fee_charged',
            'total', 'paid_amount', 'refunded_amount', 'cogs', 'affiliate_commission', 'net_profit'], 'decimal:2');

        return $dec + ['status' => OrderStatus::class, 'payment_status' => PaymentStatus::class, 'proof_status' => ProofStatus::class,
            'stock_deducted' => 'bool', 'placed_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime'];
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
        return $this->belongsTo(Customer::class);
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

    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
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
