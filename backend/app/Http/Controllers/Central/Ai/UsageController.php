<?php

namespace App\Http\Controllers\Central\Ai;

use App\DataTables\AiUsageDataTable;
use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UsageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:ai_usage.view')];
    }

    public function index()
    {
        $m = AiUsageLog::query()->where('created_at', '>=', now()->startOfMonth());   // honours the switched tenant
        $totals = ['tokens' => (int) (clone $m)->selectRaw('COALESCE(SUM(tokens_in + tokens_out),0) t')->value('t'), 'images' => (int) (clone $m)->sum('images'),
            'cost' => (float) (clone $m)->sum('est_cost'), 'calls' => (clone $m)->count()];

        return view('central.ai.usage.index', ['table' => new AiUsageDataTable, 'totals' => $totals]);
    }

    public function datatable(AiUsageDataTable $table): JsonResponse
    {
        return $table->ajax();
    }
}
