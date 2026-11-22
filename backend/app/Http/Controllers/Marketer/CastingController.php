<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\CastingCandidate;
use App\Models\CastingLiveProduct;
use App\Models\CastingSession;
use App\Models\Product;
use App\Services\Casting\CastingDirector;
use App\Services\Casting\CastingStorage;
use App\Services\Casting\ProviderManager;
use App\Services\Casting\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** AI Casting Studio — sessions and candidates (/marketer/casting/*). Permissions: view / create / edit / approve (ai_casting.*), set on the routes. */
class CastingController extends Controller
{
    public function __construct(private CastingDirector $director, private ProviderManager $providers) {}

    public function index()
    {
        return view('marketer.casting.index', [
            'sessions' => CastingSession::query()->with(['product:id,name,sku', 'liveProduct'])->withCount('candidates')->latest('id')->paginate(12),
            'products' => Product::query()->orderByDesc('id')->limit(1500)->get(['id', 'name', 'sku']), 'liveProducts' => CastingLiveProduct::query()->orderByDesc('id')->limit(500)->get(),
            'providers' => $this->providers->selectable(), 'defaultProvider' => config('casting.provider'),
            'health' => $this->providers->make()->health(),
        ]);
    }

    public function store(Request $r)
    {
        $v = $r->validate(['product_id' => ['required_without:live_product_id', 'nullable', 'integer', 'exists:products,id'], 'live_product_id' => ['nullable', 'integer', 'exists:casting_live_products,id'], 'count' => ['nullable', 'integer', 'between:'.config('casting.candidates.min').','.config('casting.candidates.max')],
            'provider' => ['nullable', 'string', 'max:20'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $s = $this->director->start(Subject::resolve($v['product_id'] ?? null, $v['live_product_id'] ?? null), (int) ($v['count'] ?? config('casting.candidates.default')), $r->user('marketer'), $v['provider'] ?? null, $v['notes'] ?? null);

        return $r->expectsJson() ? response()->json(['id' => $s->id, 'url' => route('marketer.casting.show', $s->id)], 201) : redirect()->route('marketer.casting.show', $s->id);
    }

    public function show(int $id)
    {
        $s = CastingSession::query()->with(['product:id,name,sku', 'liveProduct', 'candidates'])->findOrFail($id);

        return view('marketer.casting.show', ['s' => $s, 'canApprove' => auth('marketer')->user()->can('ai_casting.approve'), 'canEdit' => auth('marketer')->user()->can('ai_casting.edit'),
            'caps' => $this->providers->make($s->provider)->capabilities()]);
    }

    /** Polled by the gallery while candidates are generating. Never exposes paths or tokens ($hidden). */
    public function status(int $id): JsonResponse
    {
        $s = CastingSession::query()->with('candidates')->findOrFail($id);

        return response()->json(['status' => $s->status, 'candidates' => $s->candidates->map(fn ($c) => ['id' => $c->id, 'status' => $c->status, 'error' => $c->error, 'attempts' => $c->attempts,
            'has_image' => (bool) $c->getRawOriginal('portrait_path'), 'v' => $c->updated_at?->timestamp])]);
    }

    public function image(int $id): Response
    {
        $c = CastingCandidate::query()->findOrFail($id);
        $path = $c->getRawOriginal('portrait_path');
        abort_unless($path && CastingStorage::disk()->exists($path), 404);

        return CastingStorage::disk()->response($path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
    }

    public function regenerate(int $id): JsonResponse
    {
        $c = $this->director->regenerate(CastingCandidate::findOrFail($id));

        return response()->json(['id' => $c->id, 'status' => $c->status]);
    }

    public function update(Request $r, int $id): JsonResponse
    {
        $v = $r->validate(['name' => ['nullable', 'string', 'max:120'], 'age_range' => ['nullable', 'string', 'max:20'], 'appearance' => ['nullable', 'string', 'max:600'], 'hairstyle' => ['nullable', 'string', 'max:200'],
            'clothing' => ['nullable', 'string', 'max:300'], 'personality' => ['nullable', 'string', 'max:300'], 'ad_role' => ['nullable', 'string', 'max:200'], 'regenerate' => ['nullable', 'boolean']]);
        $c = $this->director->edit(CastingCandidate::findOrFail($id), $v, (bool) ($v['regenerate'] ?? false));

        return response()->json(['id' => $c->id, 'status' => $c->status]);
    }

    public function reject(int $id): JsonResponse
    {
        return response()->json(['status' => $this->director->reject(CastingCandidate::findOrFail($id))->status]);
    }

    public function approve(Request $r, int $id): JsonResponse
    {
        $r->validate(['name' => ['nullable', 'string', 'max:120']]);
        $m = $this->director->approve(CastingCandidate::findOrFail($id), $r->user('marketer'), $r->input('name'));

        return response()->json(['model_id' => $m->id, 'url' => route('marketer.casting.models.show', $m->id)]);
    }
}
