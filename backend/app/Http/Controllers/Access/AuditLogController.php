<?php

namespace App\Http\Controllers\Access;

use App\DataTables\ActivityDataTable;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Read-only audit trail of the admin panel (every activity_log row). */
class AuditLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:audit.view')];
    }

    public function index(Request $request)
    {
        return view('access.audit', ['guard' => 'admin', 'table' => new ActivityDataTable]);
    }

    public function datatable(Request $request): JsonResponse
    {
        return (new ActivityDataTable)->ajax();
    }
}
