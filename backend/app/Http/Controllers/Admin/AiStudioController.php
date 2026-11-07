<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Ai\StudioData;
use App\Enums\SocialPlatform;
use App\Exceptions\AiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AiStudioRequest;
use App\Models\Bundle;
use App\Models\FlashSale;
use App\Models\TenantProduct;
use App\Services\Ai\StudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AiStudioController extends Controller implements HasMiddleware
{
    public function __construct(private StudioService $studio) {}

    public static function middleware(): array
    {
        return [new Middleware('can:ai_studio.use')];
    }

    public function index()
    {
        $o = $this->studio->options();

        return view('tenant.ai.studio', [
            'platforms' => collect(SocialPlatform::cases())->mapWithKeys(fn ($p) => [$p->value => ['label' => $p->label()] + $p->rules()])->all(),
            'products' => TenantProduct::with('product')->where('is_active', true)->latest('id')->limit(200)->get()->mapWithKeys(fn ($tp) => [$tp->product_id => $tp->product->name])->all(),
            'bundles' => Bundle::orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
            'sales' => FlashSale::latest('id')->limit(50)->get()->mapWithKeys(fn ($f) => [$f->id => $f->name])->all(),
            'textModels' => $o['text'], 'imageModels' => $o['image'], 'videoModels' => $o['video'], 'events' => $o['events'],
            'locales' => (array) current_tenant()->locales, 'defaultPlatforms' => (array) (app(\App\Services\Ai\AiAccess::class)->settings()?->default_platforms ?? []),
            'campaigns' => AiStudioRequest::CAMPAIGNS,
        ]);
    }

    public function store(AiStudioRequest $r): JsonResponse
    {
        try {
            $g = $this->studio->create(StudioData::fromRequest($r), auth('tenant')->id());
        } catch (AiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'reason' => $e->reason], $e->reason === 'throttled' ? 429 : 422);
        }

        return response()->json(['success' => true, 'data' => $this->studio->present($g)], 201);
    }

    public function show(int $id): JsonResponse
    {
        $g = $this->studio->find($id);
        abort_unless($g, 404);

        return response()->json(['success' => true, 'data' => $this->studio->present($g)]);
    }

    public function regenerate(int $id): JsonResponse
    {
        $g = $this->studio->find($id);
        abort_unless($g, 404);
        try {
            $n = $this->studio->regenerate($g, auth('tenant')->id());
        } catch (AiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $this->studio->present($n)], 201);
    }

    public function history(): JsonResponse
    {
        $p = $this->studio->history();

        return response()->json(['success' => true, 'data' => $p->getCollection()->map(fn ($g) => $this->studio->present($g))->values(), 'meta' => ['page' => $p->currentPage(), 'last' => $p->lastPage()]]);
    }

    public function destroy(int $id): JsonResponse
    {
        $g = $this->studio->find($id);
        abort_unless($g, 404);
        $this->studio->delete($g);

        return response()->json(['success' => true]);
    }
}
