<?php

namespace App\Http\Controllers\Admin\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChallengeReviewRequest;
use App\Http\Requests\Admin\InventoryReorderRequest;
use App\Http\Requests\Admin\ReturnReasonRequest;
use App\Http\Requests\Admin\ReturnTaskRequest;
use App\Http\Requests\Admin\SupplierPriceRequest;
use App\Http\Requests\Admin\SupplyTermsRequest;
use App\Models\Branch;
use App\Models\ImprovementTask;
use App\Models\ProductSupplyTerm;
use App\Models\Supplier;
use App\Models\SupplierProductPrice;
use App\Services\Growth\CeoChallengeService;
use App\Services\Growth\InventoryDecisionService;
use App\Services\Growth\ReturnIntelService;
use App\Services\Growth\SupplierPriceService;
use App\Services\Growth\TrendRadarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** N6 admin pages. Each route is gated by its own growth.feature switch (routes/admin.php). */
class CapitalAdminController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['inventory', 'returns', 'trends', 'ceo']), new Middleware('can:growth.manage', except: ['inventory', 'returns', 'trends', 'ceo'])];
    }

    public function inventory(InventoryDecisionService $svc)
    {
        return view('admin.growth.inventory', $svc->analyze() + ['suppliers' => Supplier::where('is_active', true)->pluck('name', 'id')->all(), 'branches' => Branch::pluck('name', 'id')->all(), 'terms' => ProductSupplyTerm::get()->keyBy('product_id'),
            'prices' => SupplierProductPrice::with('supplier')->latest('id')->limit(100)->get(),
            'tasks' => ImprovementTask::where('rule', 'stock_to_story')->whereIn('status', ['open', 'doing'])->latest('id')->limit(30)->get()]);
    }

    public function reorder(InventoryReorderRequest $r, InventoryDecisionService $svc): JsonResponse
    {
        $d = $r->validated();
        $svc->proposeReorder($d['product_id'], $d['branch_id'] ?? null, $d['supplier_id'] ?? null, auth('admin')->id());

        return $this->ok(__('growth_capital.inv.sent'), route('admin.growth.inventory.index'));
    }

    public function terms(SupplyTermsRequest $r, InventoryDecisionService $svc): JsonResponse
    {
        $d = $r->validated();
        $svc->saveTerms($d['product_id'], $d, auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.inventory.index'));
    }

    public function savePrice(SupplierPriceRequest $r, SupplierPriceService $svc): JsonResponse
    {
        $svc->save($r->validated(), auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.inventory.index'));
    }

    public function deletePrice(int $id, SupplierPriceService $svc): JsonResponse
    {
        $svc->delete($id, auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.inventory.index'));
    }

    public function story(InventoryDecisionService $svc): JsonResponse
    {
        return $this->ok(__('growth_capital.story.scanned', ['n' => $svc->stockToStory()]), route('admin.growth.inventory.index'));
    }

    public function returns(ReturnIntelService $svc)
    {
        return view('admin.growth.returns-intel', ['agg' => $svc->aggregate(), 'untagged' => $svc->untagged(), 'tasks' => $svc->tasks()]);
    }

    public function reason(ReturnReasonRequest $r, ReturnIntelService $svc): JsonResponse
    {
        $d = $r->validated();
        $d['kind'] === 'return' ? $svc->tagReturn($d['id'], $d['code'], auth('admin')->id()) : $svc->tagCancellation($d['id'], $d['code'], auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.returns-intel.index'));
    }

    public function returnTasks(ReturnIntelService $svc): JsonResponse
    {
        return $this->ok(__('growth_capital.ret.generated', ['n' => $svc->generateTasks()]), route('admin.growth.returns-intel.index'));
    }

    public function returnTask(ReturnTaskRequest $r, ReturnIntelService $svc, int $id): JsonResponse
    {
        $svc->updateTask($id, $r->validated(), auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.returns-intel.index'));
    }

    public function trends(TrendRadarService $svc)
    {
        return view('admin.growth.trends', ['rows' => $svc->candidates()]);
    }

    public function trendScan(TrendRadarService $svc): JsonResponse
    {
        return $this->ok(__('growth_capital.trend.scanned', ['n' => $svc->scan()]), route('admin.growth.trends.index'));
    }

    public function trendTest(TrendRadarService $svc, int $id): JsonResponse
    {
        $svc->toTest($id, auth('admin')->id());

        return $this->ok(__('growth_capital.trend.test_created'), route('admin.growth.trends.index'));
    }

    public function trendDismiss(TrendRadarService $svc, int $id): JsonResponse
    {
        $svc->dismiss($id, auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.trends.index'));
    }

    public function ceo(CeoChallengeService $svc)
    {
        return view('admin.growth.ceo', ['rows' => $svc->history()]);
    }

    public function ceoGenerate(CeoChallengeService $svc): JsonResponse
    {
        $svc->generate();

        return $this->ok(__('admin.saved'), route('admin.growth.ceo.index'));
    }

    public function ceoReview(ChallengeReviewRequest $r, CeoChallengeService $svc, int $id): JsonResponse
    {
        $svc->review($id, $r->validated(), auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.ceo.index'));
    }

    public function ceoExperiment(CeoChallengeService $svc, int $id): JsonResponse
    {
        $svc->toExperiment($id, auth('admin')->id());

        return $this->ok(__('growth_capital.trend.test_created'), route('admin.growth.ceo.index'));
    }
}
