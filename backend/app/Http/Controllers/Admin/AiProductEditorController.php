<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Ai\ProductEditData;
use App\Exceptions\AiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AiProductEditApplyRequest;
use App\Http\Requests\Tenant\AiProductEditRequest;
use App\Services\Ai\ProductEditorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AiProductEditorController extends Controller implements HasMiddleware
{
    public function __construct(private ProductEditorService $editor) {}

    public static function middleware(): array
    {
        // generating needs the AI permission; writing storefront text additionally needs the same right as editing the catalog today
        return [new Middleware('can:ai_editor.use'), new Middleware('can:catalog.edit', only: ['apply', 'undo'])];
    }

    public function index()
    {
        return view('tenant.ai.editor', $this->editor->options() + ['presets' => ProductEditorService::PRESETS, 'fields' => \App\Models\TenantProduct::CONTENT_FIELDS]);
    }

    public function products(Request $r): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->editor->search(mb_substr((string) $r->query('q'), 0, 80))]);
    }

    public function store(AiProductEditRequest $r): JsonResponse
    {
        return $this->guard(fn () => response()->json(['success' => true, 'data' => collect($this->editor->create(ProductEditData::fromRequest($r), auth('tenant')->id()))
            ->map(fn ($g) => $this->editor->present($g))->values()], 201));
    }

    public function history(): JsonResponse
    {
        $p = $this->editor->history();

        return response()->json(['success' => true, 'data' => $p->getCollection()->map(fn ($g) => $this->editor->present($g))->values(), 'meta' => ['page' => $p->currentPage(), 'last' => $p->lastPage()]]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->editor->present($this->one($id))]);
    }

    public function apply(AiProductEditApplyRequest $r, int $id): JsonResponse
    {
        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->editor->present($this->editor->apply($this->one($id), $r->validated('accept'), auth('tenant')->id()))]));
    }

    public function reject(int $id): JsonResponse
    {
        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->editor->present($this->editor->reject($this->one($id), auth('tenant')->id()))]));
    }

    public function undo(int $id): JsonResponse
    {
        return $this->guard(fn () => response()->json(['success' => true, 'data' => $this->editor->present($this->editor->undo($this->one($id)))]));
    }

    private function one(int $id)
    {
        $g = $this->editor->find($id);
        abort_unless($g, 404);

        return $g;
    }

    private function guard(\Closure $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (AiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'reason' => $e->reason], $e->reason === 'throttled' ? 429 : 422);
        }
    }
}
