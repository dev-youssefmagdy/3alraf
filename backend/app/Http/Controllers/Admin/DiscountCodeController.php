<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\DiscountCodeDataTable;
use App\DTOs\DiscountCodeData;
use App\Http\Requests\Admin\DiscountCodeRequest;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Services\DiscountCodeService;
use Illuminate\Database\Eloquent\Model;

class DiscountCodeController extends ResourceController
{
    protected static string $perm = 'discount_codes';
    protected static string $routeBase = 'admin.discount-codes';
    protected static string $view = 'admin.discount-codes';
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
            'products' => Product::whereNotNull('price')->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->all(),
            'categories' => Category::orderBy('path')->get()->mapWithKeys(fn ($c) => [$c->id => str_repeat('— ', max(0, ($c->depth ?? 1) - 1)).$c->name])->all(),
        ];
    }
}
