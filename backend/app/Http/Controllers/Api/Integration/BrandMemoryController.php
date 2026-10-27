<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\TenantProduct;
use App\Services\Growth\BrandMemoryService;
use App\Services\Growth\TruthGuardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read-only brand memory for n8n workflows (scope `read`) + a Truth Guard check endpoint so external drafts can be validated before a human sees them. */
class BrandMemoryController extends Controller
{
    public function __construct(private BrandMemoryService $memory, private TruthGuardService $guard) {}

    public function index(Request $r): JsonResponse
    {
        $r->validate(['kind' => 'nullable|in:'.implode(',', BrandMemoryService::KINDS), 'product_id' => 'nullable|integer']);
        $entries = $this->memory->entries($r->query('kind'), true)->map(fn ($e) => ['id' => $e->id, 'kind' => $e->kind, 'title' => $e->title, 'body' => $e->body, 'version' => $e->version, 'updated_at' => $e->updated_at?->toIso8601String()])->values();
        $claims = [];
        if ($r->filled('product_id')) {
            abort_unless(TenantProduct::where('product_id', (int) $r->query('product_id'))->exists(), 404);
            $claims = $this->memory->claims((int) $r->query('product_id'))->map(fn ($c) => ['id' => $c->id, 'type' => $c->type, 'claim' => $c->claim])->values();
        }

        return response()->json(['data' => ['entries' => $entries, 'approved_claims' => $claims], 'meta' => ['kinds' => BrandMemoryService::KINDS]]);
    }

    public function check(Request $r): JsonResponse
    {
        $d = $r->validate(['text' => 'required|string|max:5000', 'product_id' => 'nullable|integer']);
        $pid = isset($d['product_id']) ? (int) $d['product_id'] : null;
        abort_if($pid && ! Product::whereKey($pid)->exists() || $pid && ! TenantProduct::where('product_id', $pid)->exists(), 404);

        return response()->json(['data' => $this->guard->check($d['text'], $pid)]);
    }
}
