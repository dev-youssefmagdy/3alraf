<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CustomPageDataTable;
use App\DTOs\CustomPageData;
use App\Http\Requests\Admin\CustomPageRequest;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomPage;
use App\Models\Product;
use App\Services\CustomPageService;
use Illuminate\Database\Eloquent\Model;

class CustomPageController extends ResourceController
{
    protected static string $perm = 'custom_pages';
    protected static string $routeBase = 'admin.custom-pages';
    protected static string $view = 'admin.custom-pages';
    protected static string $titleKey = 'growth.custom_pages';
    protected static string $requestClass = CustomPageRequest::class;
    protected static string $dtoClass = CustomPageData::class;
    protected static string $tableClass = CustomPageDataTable::class;

    public function __construct(CustomPageService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new CustomPage(['is_active' => true, 'filters' => []]);
    }

    protected function formData(Model $model): array
    {
        return [
            'categories' => Category::query()->orderBy('path')->get()->mapWithKeys(fn ($c) => [$c->id => str_repeat('— ', $c->depth).$c->name])->all(),
            'brands' => Brand::query()->get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
            'attributeValues' => Attribute::query()->with('values')->get()->flatMap(fn ($a) => $a->values->mapWithKeys(fn ($v) => [$v->id => $a->name.': '.$v->value]))->all(),
            'products' => Product::query()->whereNotNull('price')->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->filter()->all(),
            'pinned' => $model->exists ? $model->products->pluck('id')->all() : [],
        ];
    }
}
