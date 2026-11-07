<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\DataTables\GrowthEventDataTable;
use App\Http\Controllers\Controller;
use App\Models\GrowthEvent;
use App\Services\Growth\ActionLogger;
use App\Services\Growth\GrowthEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EventController extends Controller implements HasMiddleware
{
    public function __construct(private GrowthEventService $events, private ActionLogger $log) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index', 'datatable']), new Middleware('can:growth.manage', only: ['replay'])];
    }

    public function index(GrowthEventDataTable $table)
    {
        return view('tenant.growth.events', ['table' => $table]);
    }

    public function datatable(GrowthEventDataTable $table)
    {
        return $table->ajax();
    }

    public function replay(int $id): JsonResponse
    {
        $e = $this->events->replay($this->events->find($id));
        $this->log->log('event.replayed', 'admin', auth('tenant')->id(), null, $e);

        return $this->ok(__('growth_hub.events.replayed'));
    }
}
