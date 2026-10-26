<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\AiGenerationDataTable;
use App\DTOs\Ai\GeneratorData;
use App\Enums\BannerPlacement;
use App\Exceptions\AiDraftInvalidException;
use App\Exceptions\AiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AiGeneratorDraftRequest;
use App\Http\Requests\Tenant\AiGeneratorRejectRequest;
use App\Http\Requests\Tenant\AiGeneratorRequest;
use App\Models\TenantProduct;
use App\Services\Ai\AiAccess;
use App\Services\Ai\GeneratorService;
use App\Services\Stock\StockItemCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AiGeneratorController extends Controller implements HasMiddleware
{
    public function __construct(private GeneratorService $gen) {}

    /** use = generate/view/edit drafts; approve = approve + reject (permission split). */
    public static function middleware(): array
    {
        return [new Middleware('can:ai_generators.use'), new Middleware('can:ai_generators.approve', only: ['approve', 'reject'])];
    }

    public function index(AiGenerationDataTable $table)
    {
        $access = app(AiAccess::class);
        $t = current_tenant();

        return view('tenant.ai.generators.index', [
            'table' => $table,
            'products' => TenantProduct::with('product')->where('is_active', true)->latest('id')->limit(200)->get()->mapWithKeys(fn ($tp) => [$tp->product_id => $tp->product->name])->all(),
            'textModels' => $access->modelsFor($t, 'text'), 'imageModels' => $access->modelsFor($t, 'image'), 'placements' => BannerPlacement::options(),
        ]);
    }

    public function datatable(AiGenerationDataTable $table)
    {
        return $table->ajax();
    }

    public function store(AiGeneratorRequest $r): JsonResponse
    {
        try {
            $g = $this->gen->create(GeneratorData::fromRequest($r), auth('tenant')->id());
        } catch (AiException $e) {
            return $this->fail($e);
        }

        return response()->json(['success' => true, 'data' => $this->gen->present($g), 'url' => route('tenant.ai.generators.show', $g->id)], 201);
    }

    public function show(int $id)
    {
        $g = $this->one($id);

        return view('tenant.ai.generators.show', ['g' => $this->gen->present($g), 'items' => collect(app(StockItemCatalog::class)->options())->pluck('label', 'key')->all(),
            'locales' => (array) current_tenant()->locales, 'placements' => BannerPlacement::options(), 'canApprove' => (bool) auth('tenant')->user()?->can('ai_generators.approve')]);
    }

    public function json(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->gen->present($this->one($id))]);
    }

    public function update(int $id, AiGeneratorDraftRequest $r): JsonResponse
    {
        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->gen->present($this->gen->updateDraft($this->one($id), (array) $r->input('draft')))]));
    }

    public function approve(int $id): JsonResponse
    {
        $this->one($id);

        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->gen->present($this->gen->approve($id, auth('tenant')->id())), 'message' => __('ai.gen.approved_msg')]));
    }

    public function reject(int $id, AiGeneratorRejectRequest $r): JsonResponse
    {
        $this->one($id);

        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->gen->present($this->gen->reject($id, $r->validated('reason'), auth('tenant')->id()))]));
    }

    public function regenerate(int $id): JsonResponse
    {
        $g = $this->one($id);

        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->gen->present($n = $this->gen->regenerate($g, auth('tenant')->id())), 'url' => route('tenant.ai.generators.show', $n->id)], 201));
    }

    private function one(int $id)
    {
        $g = $this->gen->find($id);
        abort_unless($g, 404);

        return $g;
    }

    private function guard(\Closure $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (AiException $e) {
            return $this->fail($e);
        }
    }

    private function fail(AiException $e): JsonResponse
    {
        $errors = $e instanceof AiDraftInvalidException ? $this->gen->messages($e->errors) : [];

        return response()->json(['success' => false, 'message' => $e->getMessage(), 'reason' => $e->reason, 'errors' => $errors], $e->reason === 'throttled' ? 429 : 422);
    }
}
