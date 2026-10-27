<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SeasonalCloseRequest;
use App\Http\Requests\Tenant\SeasonalEventRequest;
use App\Models\Category;
use App\Models\SeasonalEvent;
use App\Services\Growth\SeasonalCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SeasonalController extends Controller implements HasMiddleware
{
    public function __construct(private SeasonalCalendarService $svc) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index']), new Middleware('can:growth.manage', except: ['index'])];
    }

    public function index()
    {
        $year = (int) request('year', now()->year);
        $events = SeasonalEvent::with('tasks', 'memory')->where('year', $year)->orderBy('starts_on')->get();
        foreach ($events as $e) {
            $e->readiness = $this->svc->readiness($e);
            $e->last = $this->svc->lastMemory($e);
        }

        return view('tenant.growth.seasonal', ['year' => $year, 'events' => $events, 'categories' => Category::orderBy('id')->get(['id', 'name'])]);
    }

    public function ensure(): JsonResponse
    {
        $year = (int) request()->validate(['year' => ['required', 'integer', 'between:2024,2100']])['year'];
        $this->svc->ensureYear($year, auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.seasonal.index', ['year' => $year]));
    }

    public function update(SeasonalEventRequest $r, int $id): JsonResponse
    {
        $e = $this->svc->update($id, $r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.seasonal.index', ['year' => $e->year]));
    }

    public function confirm(int $id): JsonResponse
    {
        $e = $this->svc->confirmDate($id, auth('tenant')->id());

        return $this->ok(__('growth_hub.seasonal.confirmed'), route('tenant.growth.seasonal.index', ['year' => $e->year]));
    }

    public function task(int $id): JsonResponse
    {
        $t = $this->svc->toggleTask($id, auth('tenant')->id());

        return $this->ok(null, null, ['done' => (bool) $t->done_at]);
    }

    public function bundles(int $id): JsonResponse
    {
        SeasonalEvent::findOrFail($id);
        $res = $this->svc->proposeBundles($id, auth('tenant')->id());

        return $this->ok(__('growth_hub.bundles.proposed', ['n' => count($res['approval_ids']), 'rej' => count($res['rejected'])]), route('tenant.growth.bundles.index'));
    }

    public function close(SeasonalCloseRequest $r, int $id): JsonResponse
    {
        $this->svc->close($id, $r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.seasonal.index', ['year' => SeasonalEvent::findOrFail($id)->year]));
    }
}
