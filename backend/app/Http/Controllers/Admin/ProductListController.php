<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\ProductListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductListRequest;
use App\Models\Product;
use App\Services\Lists\ProductListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Pin UI for recommended / most sold / most reviewed (pinned first, ordered; the rest is filled algorithmically). */
class ProductListController extends Controller implements HasMiddleware
{
    public function __construct(private ProductListService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:product_lists.view', only: ['index']), new Middleware('can:product_lists.edit', only: ['validateForm', 'update'])];
    }

    public function index()
    {
        return view('admin.product-lists.index', [
            'lists' => $this->service->overview(),
            'options' => Product::query()->where('is_active', true)->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->filter()->all(),
        ]);
    }

    public function validateForm(ProductListRequest $r, string $type): JsonResponse
    {
        return $this->ok();
    }

    public function update(ProductListRequest $r, string $type): JsonResponse
    {
        $this->service->save(ProductListData::fromRequest($r));

        return $this->ok(__('admin.saved'));
    }
}
