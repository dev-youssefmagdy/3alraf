<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\AiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AiTrendDismissRequest;
use App\Http\Requests\Tenant\AiTrendDraftRequest;
use App\Http\Requests\Tenant\AiTrendFilterRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\Ai\AiAccess;
use App\Services\Ai\TrendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AiTrendsController extends Controller implements HasMiddleware
{
    public function __construct(private TrendService $trends, private AiAccess $access) {}

    public static function middleware(): array
    {
        return [new Middleware('can:ai_trends.view'), new Middleware('can:purchases.create', only: ['purchaseDraft'])];
    }

    public function index()
    {
        return view('tenant.ai.trends', [
            'categories' => Category::query()->where('is_active', true)->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'branches' => Branch::where('is_active', true)->get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
            'models' => $this->access->modelsFor(current_tenant(), 'text'), 'webModel' => $this->trends->webModel(), 'country' => $this->trends->country(),
            'canPurchase' => auth('tenant')->user()?->can('purchases.create'),
        ]);
    }

    public function own(AiTrendFilterRequest $r): JsonResponse
    {
        $days = (int) ($r->input('period') ?: 30);
        $rows = $this->trends->own($days, $r->integer('category_id') ?: null, (string) $r->input('sort', 'qty'));
        $narrative = null;
        if ($r->boolean('narrative') && $rows) {
            try {
                $narrative = $this->trends->narrative($rows, $days, $this->narrativeModel($r->integer('ai_model_id') ?: null));
            } catch (AiException $e) {
                $narrative = null;
                $err = $e->getMessage();
            }
        }

        return response()->json(['success' => true, 'data' => ['rows' => $rows, 'narrative' => $narrative, 'narrative_error' => $err ?? null, 'period' => $days]]);
    }

    public function market(): JsonResponse
    {
        $m = $this->trends->market();
        $items = collect($m['snapshot']?->items ?? [])->reject(fn ($i) => ! empty($i['dismissed']))->values();

        return response()->json(['success' => true, 'data' => [
            'items' => $items, 'generated_at' => $m['snapshot']?->generated_at?->toIso8601String(), 'stale' => $m['stale'], 'running' => $m['running'],
            'error' => $m['error'], 'country' => $this->trends->country(), 'has_model' => (bool) $this->trends->webModel(),
        ]]);
    }

    public function refresh(AiTrendFilterRequest $r): JsonResponse
    {
        try {
            if (! $this->trends->webModel()) {
                throw new AiException(__('ai.trends.no_web_model'), 'model');
            }
            $state = $this->trends->requestRefresh($r->boolean('force'));
        } catch (AiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'reason' => $e->reason], $e->reason === 'throttled' ? 429 : 422);
        }

        return response()->json(['success' => true, 'state' => $state]);
    }

    public function dismiss(AiTrendDismissRequest $r): JsonResponse
    {
        abort_unless($this->trends->dismiss($r->input('key')), 404);

        return response()->json(['success' => true]);
    }

    public function purchaseDraft(AiTrendDraftRequest $r): JsonResponse
    {
        try {
            $po = $this->trends->purchaseDraft((int) $r->input('supplier_id'), (int) $r->input('branch_id'), $r->input('items'));
        } catch (AiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('ai.trends.po_created', ['n' => $po->number]), 'url' => route('tenant.purchases.edit', $po->id)]);
    }

    public function export(AiTrendFilterRequest $r)
    {
        $tab = $r->input('tab', 'own');
        $rows = $tab === 'own'
            ? $this->trends->own((int) ($r->input('period') ?: 30), $r->integer('category_id') ?: null, (string) $r->input('sort', 'qty'))
            : collect($this->trends->market()['snapshot']?->items ?? [])->reject(fn ($i) => ! empty($i['dismissed']))->values()->all();

        return response($this->trends->csv($rows, $tab), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="trends-'.$tab.'-'.now()->format('Ymd').'.csv"']);
    }

    private function narrativeModel(?int $id)
    {
        $models = $this->access->modelsFor(current_tenant(), 'text');

        return $models->firstWhere('id', $id) ?? $models->firstWhere('id', $this->access->settings()?->default_ai_model_id) ?? $models->firstWhere('is_default', true) ?? $models->first();
    }
}
