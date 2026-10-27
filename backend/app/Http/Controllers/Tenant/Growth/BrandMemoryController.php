<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\BrandMemoryRequest;
use App\Http\Requests\Tenant\ProductClaimRequest;
use App\Models\ApprovedProductClaim;
use App\Models\TenantProduct;
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
        return view('tenant.growth.brand-memory', [
            'entries' => $this->memory->entries()->groupBy('kind'), 'kinds' => BrandMemoryService::KINDS, 'claimTypes' => BrandMemoryService::CLAIM_TYPES,
            'claims' => ApprovedProductClaim::orderBy('product_id')->orderBy('id')->get()->groupBy('product_id'),
            'products' => TenantProduct::with('product')->orderBy('id')->limit(500)->get()->mapWithKeys(fn ($t) => [$t->product_id => $t->product?->name])->all(),
        ]);
    }

    public function store(BrandMemoryRequest $r): JsonResponse
    {
        $this->memory->save($r->validated(), null, auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.brand-memory.index'));
    }

    public function update(BrandMemoryRequest $r, int $id): JsonResponse
    {
        $this->memory->save($r->validated(), $id, auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.brand-memory.index'));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->memory->delete($id, auth('tenant')->id());

        return $this->ok(__('admin.deleted'), route('tenant.growth.brand-memory.index'));
    }

    public function versions(int $id): JsonResponse
    {
        return $this->ok(null, null, ['versions' => $this->memory->versions($id)->map(fn ($v) => ['version' => $v->version, 'title' => $v->title, 'body' => $v->body, 'at' => $v->created_at?->toDateTimeString()])->all()]);
    }

    public function storeClaim(ProductClaimRequest $r): JsonResponse
    {
        $this->memory->addClaim((int) $r->validated('product_id'), $r->validated('type'), $r->validated('claim'), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.brand-memory.index'));
    }

    public function destroyClaim(int $id): JsonResponse
    {
        $this->memory->removeClaim($id, auth('tenant')->id());

        return $this->ok(__('admin.deleted'), route('tenant.growth.brand-memory.index'));
    }
}
