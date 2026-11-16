<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;

/** Admin → Settings → System health: connection checks, run one by one over AJAX so a slow service never blocks the page. */
class HealthController extends Controller
{
    public function index()
    {
        return view('admin.health.index', ['checks' => HealthService::CHECKS]);
    }

    public function run(HealthService $health, string $key): JsonResponse
    {
        return response()->json($health->run($key));
    }
}
