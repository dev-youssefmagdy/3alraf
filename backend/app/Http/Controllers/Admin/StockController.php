<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\StockLevelDataTable;
use App\DataTables\StockMovementDataTable;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Read-only stock views: per-branch levels and the movement ledger. */
class StockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:stock.view')];
    }

    public function index()
    {
        $valuation = (string) (StockLevel::query()->selectRaw('COALESCE(SUM(on_hand * avg_cost), 0) as v')->value('v') ?? '0');

        return view('tenant.stock.index', [
            'table' => new StockLevelDataTable, 'branches' => Branch::get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
            'valuation' => \App\Support\Money::round($valuation), 'units' => (int) StockLevel::sum('on_hand'),
        ]);
    }

    public function levels(): JsonResponse
    {
        return (new StockLevelDataTable)->ajax();
    }

    public function movements()
    {
        return view('tenant.stock.movements', [
            'table' => new StockMovementDataTable, 'branches' => Branch::get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(), 'types' => StockMovementType::options(),
        ]);
    }

    public function movementsData(): JsonResponse
    {
        return (new StockMovementDataTable)->ajax();
    }
}
