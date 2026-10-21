<?php

namespace App\Http\Controllers\Access;

use App\DataTables\ActivityDataTable;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Read-only audit trail for the central and tenant panels (guard from the route-name prefix). */
class AuditLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:audit.view')];
    }

    private function central(Request $r): bool
    {
        return str_starts_with((string) $r->route()->getName(), 'central.');
    }

    public function index(Request $request)
    {
        $central = $this->central($request);

        return view('access.audit', ['guard' => $central ? 'central' : 'tenant', 'table' => new ActivityDataTable($central)]);
    }

    public function datatable(Request $request): JsonResponse
    {
        return (new ActivityDataTable($this->central($request)))->ajax();
    }
}
