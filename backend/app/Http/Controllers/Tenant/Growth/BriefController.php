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
        return [new Middleware('can:growth.view')];
    }

    public function index()
    {
        return view('tenant.growth.brief');
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
