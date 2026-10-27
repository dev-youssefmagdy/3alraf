<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SmallBatchDecisionRequest;
use App\Http\Requests\Tenant\SmallBatchRequest;
use App\Models\SmallBatchTest;
use App\Models\TenantProduct;
use App\Services\Growth\SmallBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** N5 Small-Batch Launch Lab admin (gated by growth.feature:small_batch_lab). */
class SmallBatchController extends Controller implements HasMiddleware
{
    public function __construct(private SmallBatchService $svc) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index']), new Middleware('can:growth.manage', except: ['index'])];
    }

    public function index()
    {
        $rows = SmallBatchTest::orderByDesc('id')->limit(100)->get()->each(fn ($t) => $t->stats = $this->svc->stats($t));

        return view('tenant.growth.small-batch', ['rows' => $rows, 'products' => TenantProduct::with('product')->where('is_active', true)->get()]);
    }

    public function store(SmallBatchRequest $r): JsonResponse
    {
        $this->svc->create($r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.batch.index'));
    }

    public function open(int $id): JsonResponse
    {
        $this->svc->open(SmallBatchTest::findOrFail($id), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.batch.index'));
    }

    public function decide(SmallBatchDecisionRequest $r, int $id): JsonResponse
    {
        $d = $r->validated();
        $res = $this->svc->decide(SmallBatchTest::findOrFail($id), $d['decision'], $d['note'] ?? null, auth('tenant')->id());

        return $this->ok($res instanceof SmallBatchTest ? __('admin.saved') : __('growth_hub.batch.sent_for_approval'), route('tenant.growth.batch.index'));
    }
}
