<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET {central}/api/v1/app-config: force-update + maintenance switches for the mobile app (config/mobile.php). */
class AppConfigController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $cfg = config('mobile');
        $msgs = (array) $cfg['maintenance_message'];
        $lang = strtolower(substr(trim(explode(',', (string) $request->header('Accept-Language'))[0]), 0, 2));
        $version = (string) $request->query('version', '');
        $valid = preg_match('/^\d+(\.\d+){0,2}/', $version) === 1;

        return response()->json(['success' => true, 'message' => null, 'meta' => (object) [], 'data' => [
            'min_version' => $cfg['min_version'], 'latest_version' => $cfg['latest_version'],
            'store_urls' => ['ios' => $cfg['store_urls']['ios'] ?: null, 'android' => $cfg['store_urls']['android'] ?: null],
            'maintenance' => (bool) $cfg['maintenance'],
            'maintenance_message' => $cfg['maintenance'] ? ($msgs[$lang] ?? $msgs['en'] ?? reset($msgs)) : null,
            // convenience when the app passes ?version=1.2.3
            'update_required' => $valid ? version_compare($version, $cfg['min_version'], '<') : null,
            'update_available' => $valid ? version_compare($version, $cfg['latest_version'], '<') : null,
        ]]);
    }
}
