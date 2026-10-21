<?php

namespace App\Http\Controllers\Central;

use App\DataTables\ProductDataTable;
use App\DTOs\ProductData;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ProductRequest;
use App\Models\Brand;
use App\Models\Language;
use App\Models\Product;
use App\Repositories\Contracts\AttributeRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductController extends Controller implements HasMiddleware
{
    public function __construct(
        private ProductService $service,
        private CategoryRepositoryInterface $categories,
        private AttributeRepositoryInterface $attributes,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:products.view', only: ['index', 'datatable']),
            new Middleware('can:products.create', only: ['create', 'store']),
            new Middleware('can:products.edit', only: ['edit', 'update']),
            new Middleware('can:products.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('central.products.index', ['table' => new ProductDataTable]);
    }

    public function datatable(ProductDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function create()
    {
        return view('central.products.form', $this->formData(new Product(['type' => ProductType::SIMPLE, 'is_active' => true])));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $this->service->create(ProductData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.products.index'));
    }

    public function edit(Product $product)
    {
        return view('central.products.form', $this->formData($product->load(['images', 'categories', 'variants.values', 'variants.images'])));
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $this->service->update($product, ProductData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.products.index'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->service->delete($product);

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(ProductRequest $request, ?Product $product = null): JsonResponse
    {
        return $this->ok();
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'languages' => Language::activeList(),
            'types' => ProductType::options(),
            'brands' => Brand::orderBy('sort_order')->get()->pluck('name', 'id')->all(),
            'categoryTree' => $this->categories->flatTree(),
            'attributes' => $this->attributes->withValues(),
        ];
    }
}
