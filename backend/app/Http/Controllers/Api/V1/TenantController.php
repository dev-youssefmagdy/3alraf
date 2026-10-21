<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantDirectory;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    /** GET /api/v1/tenants: public country/store picker for the mobile app. */
    public function index(TenantDirectory $directory): JsonResponse
    {
        $data = array_map(fn ($t) => [
            'slug' => $t['slug'], 'name' => $t['name'], 'country' => $t['country'], 'currency' => $t['currency'],
            'default_locale' => $t['default_locale'], 'locales' => $t['locales'], 'base_url' => $t['base_url'],
        ], $directory->active());

        return response()->json(['data' => $data]);
    }
}
