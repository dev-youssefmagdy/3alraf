<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\PaymentMethodDataTable;
use App\DTOs\PaymentMethodData;
use App\Http\Requests\Tenant\PaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodController extends ResourceController
{
    protected static string $perm = 'payment_methods';
    protected static string $routeBase = 'tenant.payment-methods';
    protected static string $view = 'tenant.payment-methods';
    protected static string $titleKey = 'ops.payment_methods';
    protected static string $requestClass = PaymentMethodRequest::class;
    protected static string $dtoClass = PaymentMethodData::class;
    protected static string $tableClass = PaymentMethodDataTable::class;

    public function __construct(PaymentMethodService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new PaymentMethod(['is_active' => true, 'is_sandbox' => true, 'type' => 'cod', 'provider' => 'cod', 'fee_percent' => 0, 'fee_fixed' => 0, 'fee_bearer' => 'customer', 'requires_proof' => true]);
    }
}
