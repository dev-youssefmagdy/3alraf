<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\GrowthEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        $q = GrowthEvent::query()->latest('id');
        if ($t = $r->query('type')) {
            $q->where('type', (string) $t);
        }
        $p = $q->paginate(min(100, max(1, (int) $r->query('per_page', 25))));

        return response()->json(['success' => true, 'data' => $p->getCollection()->map(fn ($e) => ['event_id' => $e->event_id, 'type' => $e->type, 'status' => $e->status, 'attempts' => $e->attempts, 'created_at' => $e->created_at?->toIso8601String()]),
            'meta' => ['page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]]);
    }
}
