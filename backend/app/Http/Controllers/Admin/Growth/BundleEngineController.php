<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\BundleAbRequest;
use App\Http\Requests\Tenant\BundleProposeRequest;
use App\Models\Bundle;
use App\Models\BundleAbTest;
use App\Models\GrowthApproval;
use App\Models\SeasonalEvent;
use App\Services\Growth\BundleAbService;
use App\Services\Growth\BundleMetricsService;
use App\Services\Growth\BundleProposalService;
use App\Services\Growth\GrowthFeatures;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BundleEngineController extends Controller implements HasMiddleware
{
    public function __construct(private BundleProposalService $proposals, private BundleAbService $ab, private BundleMetricsService $metrics) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index']), new Middleware('can:growth.manage', except: ['index'])];
    }

    public function index()
    {
        $abTests = app(GrowthFeatures::class)->active('bundle_ab') ? BundleAbTest::with('variants', 'bundle')->orderByDesc('id')->limit(30)->get()->each(fn ($t) => $t->results = $this->ab->results($t)) : collect();

        return view('tenant.growth.bundles', [
            'modes' => BundleProposalService::MODES, 'events' => SeasonalEvent::orderByDesc('year')->orderBy('starts_on')->limit(40)->get(),
            'pending' => GrowthApproval::where('type', 'bundle_draft')->where('status', GrowthApproval::PENDING)->orderByDesc('id')->limit(30)->get(),
            'report' => $this->metrics->report(now()->subDays(29), now()), 'bundles' => Bundle::orderByDesc('id')->limit(100)->get(['id', 'name']),
            'abTests' => $abTests, 'abOn' => app(GrowthFeatures::class)->active('bundle_ab'),
        ]);
    }

    public function propose(BundleProposeRequest $r): JsonResponse
    {
        $res = $this->proposals->propose($r->input('mode'), ['budgets' => $r->filled('budgets') ? preg_split('/[,\s،]+/u', $r->input('budgets')) : [], 'event_id' => $r->input('event_id')], auth('tenant')->id());

        return $this->ok(__('growth_hub.bundles.proposed', ['n' => count($res['approval_ids']), 'rej' => count($res['rejected'])]), route('tenant.growth.bundles.index'), $res);
    }

    public function abStart(BundleAbRequest $r, ImageUploadService $images): JsonResponse
    {
        $d = $r->validated();
        $d['image'] = $r->file('image') ? $images->storeSingle($r->file('image'), 'bundles') : null;
        $this->ab->start((int) $d['bundle_id'], $d, (int) $d['split_b'], (int) $d['min_sample'], auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.bundles.index'));
    }

    public function abConclude(int $id): JsonResponse
    {
        $w = request()->validate(['winner' => ['required', 'in:A,B']])['winner'];
        $this->ab->conclude($id, $w, auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.bundles.index'));
    }
}
