<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\AiGenerationDataTable;
use App\DTOs\Ai\GeneratorData;
use App\Enums\BannerPlacement;
use App\Exceptions\AiDraftInvalidException;
use App\Exceptions\AiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiGeneratorDraftRequest;
use App\Http\Requests\Admin\AiGeneratorRejectRequest;
use App\Http\Requests\Admin\AiGeneratorRequest;
use App\Models\Product;
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

        return view('admin.ai.generators.index', [
            'table' => $table,
            'products' => Product::where('is_active', true)->latest('id')->limit(200)->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->all(),
            'textModels' => $access->modelsFor('text'), 'imageModels' => $access->modelsFor('image'), 'placements' => BannerPlacement::options(),
        ]);
    }

    public function datatable(AiGenerationDataTable $table)
    {
        return $table->ajax();
    }

    public function store(AiGeneratorRequest $r): JsonResponse
    {
        try {
            $g = $this->gen->create(GeneratorData::fromRequest($r), auth('admin')->id());
        } catch (AiException $e) {
            return $this->fail($e);
        }

        return response()->json(['success' => true, 'data' => $this->gen->present($g), 'url' => route('admin.ai.generators.show', $g->id)], 201);
    }

    public function show(int $id)
    {
        $g = $this->one($id);

        return view('admin.ai.generators.show', ['g' => $this->gen->present($g), 'items' => collect(app(StockItemCatalog::class)->options())->pluck('label', 'key')->all(),
            'locales' => (array) setting('locales', []), 'placements' => BannerPlacement::options(), 'canApprove' => (bool) auth('admin')->user()?->can('ai_generators.approve')]);
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

        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->gen->present($this->gen->approve($id, auth('admin')->id())), 'message' => __('ai.gen.approved_msg')]));
    }

    public function reject(int $id, AiGeneratorRejectRequest $r): JsonResponse
    {
        $this->one($id);

        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->gen->present($this->gen->reject($id, $r->validated('reason'), auth('admin')->id()))]));
    }

    public function regenerate(int $id): JsonResponse
    {
        $g = $this->one($id);

        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->gen->present($n = $this->gen->regenerate($g, auth('admin')->id())), 'url' => route('admin.ai.generators.show', $n->id)], 201));
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
