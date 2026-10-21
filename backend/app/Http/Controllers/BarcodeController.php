<?php

namespace App\Http\Controllers;

use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Barcode image endpoint: /admin/barcode/{code}?format=svg|png&h=60 (Code128 render of the EAN-13 digits). */
class BarcodeController extends Controller
{
    public function __invoke(Request $request, BarcodeService $barcodes, string $code): Response
    {
        $h = min(200, max(20, $request->integer('h', 60)));
        $cache = ['Cache-Control' => 'public, max-age=86400'];
        if ($request->query('format') === 'png') {
            return response($barcodes->png($code, $h), 200, ['Content-Type' => 'image/png'] + $cache);
        }

        return response($barcodes->svg($code, $h), 200, ['Content-Type' => 'image/svg+xml'] + $cache);
    }
}
