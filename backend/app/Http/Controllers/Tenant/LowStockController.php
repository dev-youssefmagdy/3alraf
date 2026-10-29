<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Admin\LowStockService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LowStockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:stock.view')];
    }

    public function index(LowStockService $service)
    {
        return view('tenant.stock.low', ['rows' => $service->rows()]);
    }
}
