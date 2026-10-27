<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Services\Growth\GrowthFeatures;
use Illuminate\Http\JsonResponse;

class FeatureController extends Controller
{
    public function index(GrowthFeatures $features): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['features' => $features->states(current_tenant())]]);
    }
}
