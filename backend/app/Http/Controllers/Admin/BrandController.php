<?php

namespace App\Http\Controllers\Central;

use App\DataTables\BrandDataTable;
use App\DTOs\BrandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\BrandRequest;
use App\Models\Brand;
use App\Models\Language;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BrandController extends Controller implements HasMiddleware
{
    public function __construct(private BrandService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:brands.view', only: ['index', 'datatable']),
            new Middleware('can:brands.create', only: ['create', 'store']),
            new Middleware('can:brands.edit', only: ['edit', 'update']),
            new Middleware('can:brands.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('central.brands.index', ['table' => new BrandDataTable]);
    }

    public function datatable(BrandDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function create()
    {
        return view('central.brands.form', ['brand' => new Brand(['is_active' => true, 'sort_order' => 0]), 'languages' => Language::activeList()]);
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $this->service->create(BrandData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.brands.index'));
    }

    public function edit(Brand $brand)
    {
        return view('central.brands.form', ['brand' => $brand, 'languages' => Language::activeList()]);
    }

    public function update(BrandRequest $request, Brand $brand): JsonResponse
    {
        $this->service->update($brand, BrandData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.brands.index'));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->service->delete($brand);

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(BrandRequest $request, ?Brand $brand = null): JsonResponse
    {
        return $this->ok();
    }
}
