<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\LabAdsRequest;
use App\Http\Requests\Tenant\LabBudgetRequest;
use App\Http\Requests\Tenant\LabExperimentRequest;
use App\Http\Requests\Tenant\LabLinkRequest;
use App\Models\GrowthExperiment;
use App\Models\TenantProduct;
use App\Models\TrackedLink;
use App\Services\Growth\ExperimentLabService;
use App\Services\Growth\TrackedLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** N5 Marketing Experiment Lab (every route is gated by growth.feature:experiment_lab). */
class LabController extends Controller implements HasMiddleware
{
    public function __construct(private ExperimentLabService $lab) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index', 'show']), new Middleware('can:growth.manage', except: ['index', 'show'])];
    }

    public function index()
    {
        $rows = GrowthExperiment::whereNotNull('product_id')->orderByDesc('id')->limit(100)->get()->each(fn ($e) => $e->lab = $this->lab->metrics($e));

        return view('tenant.growth.lab', ['rows' => $rows, 'products' => TenantProduct::with('product')->where('is_active', true)->get(), 'cfg' => config('growth.lab')]);
    }

    public function show(int $id, TrackedLinkService $links)
    {
        $e = GrowthExperiment::whereNotNull('product_id')->findOrFail($id);
        $ls = TrackedLink::where('growth_experiment_id', $e->id)->get()->each(fn ($l) => [$l->stats = $links->stats($l), $l->short = $links->url($l)]);

        return view('tenant.growth.lab-show', ['e' => $e, 'm' => $this->lab->metrics($e), 'fatigue' => $this->lab->fatigue($e), 'angles' => $this->lab->angles((int) $e->product_id), 'links' => $ls, 'cfg' => config('growth.lab')]);
    }

    public function store(LabExperimentRequest $r): JsonResponse
    {
        $e = $this->lab->create($r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.lab.show', $e->id));
    }

    public function approveContent(int $id): JsonResponse
    {
        $this->lab->approveContent(GrowthExperiment::whereNotNull('product_id')->findOrFail($id), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.lab.show', $id));
    }

    public function start(int $id): JsonResponse
    {
        $this->lab->start(GrowthExperiment::whereNotNull('product_id')->findOrFail($id), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.lab.show', $id));
    }

    public function link(LabLinkRequest $r, int $id): JsonResponse
    {
        $this->lab->link(GrowthExperiment::whereNotNull('product_id')->findOrFail($id), $r->validated()['channel'] ?? null);

        return $this->ok(__('admin.saved'), route('tenant.growth.lab.show', $id));
    }

    public function ads(LabAdsRequest $r, int $id): JsonResponse
    {
        $d = $r->validated();
        $this->lab->importAds(GrowthExperiment::whereNotNull('product_id')->findOrFail($id), [$d], 'admin', auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.lab.show', $id));
    }

    public function budget(LabBudgetRequest $r, int $id): JsonResponse
    {
        $d = $r->validated();
        $this->lab->requestBudgetIncrease(GrowthExperiment::whereNotNull('product_id')->findOrFail($id), (float) $d['budget_cap'], $d['reason'] ?? null, auth('tenant')->id());

        return $this->ok(__('growth_hub.lab.budget_requested'), route('tenant.growth.lab.show', $id));
    }
}
