<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Models\TenantProduct;
use App\Services\Growth\BriefService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BriefController extends Controller implements HasMiddleware
{
    public function __construct(private BriefService $brief) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view'), new Middleware('can:growth.manage', only: ['decide'])];
    }

    public function index()
    {
        return view('tenant.growth.brief');
    }

    public function decide(Request $r, \App\Services\Growth\BriefDecisionService $decisions): JsonResponse
    {
        $d = $r->validate(['recommendation_key' => 'required|string|max:120', 'status' => 'required|in:accepted,dismissed,done', 'kind' => 'nullable|string|max:60', 'owner' => 'nullable|string|max:100', 'note' => 'nullable|string|max:500',
            'metric_after' => 'nullable|numeric', 'create_experiment' => 'nullable|boolean', 'evidence' => 'nullable|array', 'evidence.*.source' => 'string|max:60', 'evidence.*.metric' => 'string|max:60', 'evidence.*.value' => 'nullable', 'evidence.*.period' => 'nullable|string|max:40']);
        $d['create_experiment'] = $r->boolean('create_experiment') && app(\App\Services\Growth\GrowthFeatures::class)->active('experiments', current_tenant());
        $row = $decisions->decide($d, auth('tenant')->id());

        return $this->ok(__('admin.saved'), null, ['status' => $row->status, 'experiment_id' => $row->experiment_id]);
    }

    public function data(Request $r): JsonResponse
    {
        $r->validate(['type' => 'required|in:daily,weekly', 'date' => 'nullable|date', 'ai' => 'nullable|boolean']);
        $d = $r->filled('date') ? Carbon::parse($r->query('date')) : null;
        $b = $r->query('type') === 'weekly' ? $this->brief->weekly($d, $r->boolean('ai')) : $this->brief->daily($d, $r->boolean('ai'));
        $names = TenantProduct::with('product')->whereIn('product_id', collect($b['recommendations'] ?? [])->pluck('params.product_id')->merge(collect($b['inventory_issues'] ?? [])->pluck('product_id'))->merge(collect($b['products_gaining_interest'] ?? [])->pluck('product_id'))->merge(collect($b['reorder_candidates'] ?? [])->pluck('product_id'))->filter()->unique())
            ->get()->mapWithKeys(fn ($t) => [$t->product_id => $t->product?->name])->all();

        return $this->ok(null, null, ['brief' => $b, 'names' => $names]);
    }
}
