<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Streams private-disk files for a signed, expiring URL (no path traversal). */
class PrivateFileController extends Controller
{
    public function show(Request $request, string $path): Response
    {
        abort_if($path === '' || str_contains($path, '..') || str_starts_with($path, '/'), 403);
        $disk = Storage::disk(config('media.private_disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
