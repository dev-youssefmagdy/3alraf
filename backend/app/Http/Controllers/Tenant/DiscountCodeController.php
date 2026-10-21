<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\DiscountCodeDataTable;
use App\DTOs\DiscountCodeData;
use App\Http\Requests\Tenant\DiscountCodeRequest;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\TenantProduct;
use App\Services\DiscountCodeService;
use Illuminate\Database\Eloquent\Model;

class DiscountCodeController extends ResourceController
{
    protected static string $perm = 'discount_codes';
    protected static string $routeBase = 'tenant.discount-codes';
    protected static string $view = 'tenant.discount-codes';
    protected static string $titleKey = 'orders.discount_codes';
    protected static string $requestClass = DiscountCodeRequest::class;
    protected static string $dtoClass = DiscountCodeData::class;
    protected static string $tableClass = DiscountCodeDataTable::class;

    public function __construct(DiscountCodeService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new DiscountCode(['type' => 'percent', 'scope' => 'all', 'is_active' => true, 'min_order' => 0]);
    }

    protected function formData(Model $model): array
    {
        return [
            'products' => TenantProduct::with('product')->get()->filter(fn ($t) => $t->product)->mapWithKeys(fn ($t) => [$t->product_id => $t->product->name])->all(),
            'categories' => Category::orderBy('path')->get()->mapWithKeys(fn ($c) => [$c->id => str_repeat('— ', max(0, ($c->depth ?? 1) - 1)).$c->name])->all(),
        ];
    }
}
