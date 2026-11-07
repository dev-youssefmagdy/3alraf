<?php

namespace App\Http\Controllers\Admin\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandMemoryRequest;
use App\Http\Requests\Admin\ProductClaimRequest;
use App\Models\ApprovedProductClaim;
use App\Models\Product;
use App\Services\Growth\BrandMemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BrandMemoryController extends Controller implements HasMiddleware
{
    public function __construct(private BrandMemoryService $memory) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index', 'versions']), new Middleware('can:growth.manage', except: ['index', 'versions'])];
    }

    public function index()
    {
        return view('admin.growth.brand-memory', [
            'entries' => $this->memory->entries()->groupBy('kind'), 'kinds' => BrandMemoryService::KINDS, 'claimTypes' => BrandMemoryService::CLAIM_TYPES,
            'claims' => ApprovedProductClaim::orderBy('product_id')->orderBy('id')->get()->groupBy('product_id'),
            'products' => Product::whereNotNull('price')->orderBy('id')->limit(500)->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->all(),
        ]);
    }

    public function store(BrandMemoryRequest $r): JsonResponse
    {
        $this->memory->save($r->validated(), null, auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.brand-memory.index'));
    }

    public function update(BrandMemoryRequest $r, int $id): JsonResponse
    {
        $this->memory->save($r->validated(), $id, auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.brand-memory.index'));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->memory->delete($id, auth('admin')->id());

        return $this->ok(__('admin.deleted'), route('admin.growth.brand-memory.index'));
    }

    public function versions(int $id): JsonResponse
    {
        return $this->ok(null, null, ['versions' => $this->memory->versions($id)->map(fn ($v) => ['version' => $v->version, 'title' => $v->title, 'body' => $v->body, 'at' => $v->created_at?->toDateTimeString()])->all()]);
    }

    public function storeClaim(ProductClaimRequest $r): JsonResponse
    {
        $this->memory->addClaim((int) $r->validated('product_id'), $r->validated('type'), $r->validated('claim'), auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.brand-memory.index'));
    }

    public function destroyClaim(int $id): JsonResponse
    {
        $this->memory->removeClaim($id, auth('admin')->id());

        return $this->ok(__('admin.deleted'), route('admin.growth.brand-memory.index'));
    }
}
