<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Whatsapp\Concerns\ResolvesChatOwner;
use App\Models\WhatsappMedia;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Authorized media streaming. The file is looked up through ownedBy(): another owner's id is a plain 404. */
class ChatMediaController extends Controller implements HasMiddleware
{
    use ResolvesChatOwner;

    public static function middleware(): array
    {
        return [new Middleware('can:whatsapp.chat.view'), new Middleware('can:whatsapp.chat.media')];
    }

    /** GET media/{id}?thumb=1&download=1 (supports a single Range). */
    public function show(Request $request, int $id)
    {
        $media = WhatsappMedia::ownedBy($this->owner($request))->findOrFail($id);
        $thumb = $request->boolean('thumb');
        $path = $thumb ? $media->thumbnail_path : ($media->status === 'ready' ? $media->path : null);
        $disk = Storage::disk($media->disk);
        abort_unless($path && $disk->exists($path), 404);

        $size = $disk->size($path);
        $mime = $thumb ? ($disk->mimeType($path) ?: 'image/jpeg') : ($media->mime ?: 'application/octet-stream');
        $inline = ! $request->boolean('download') && in_array($mime, config('whatsapp_chat.inline_mimes'), true);
        $name = $thumb ? 'thumbnail' : ($media->original_name ?: 'file');
        $headers = [
            'Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Accept-Ranges' => 'bytes',
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $name, $this->asciiName($name)),
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, max-age=3600', 'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        $start = 0;
        $end = $size - 1;
        $status = 200;
        if (($range = $request->header('Range')) && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') { // suffix: last N bytes
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] !== '' ? min((int) $m[2], $size - 1) : $end;
            }
            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => "bytes */$size"]);
            }
            $status = 206;
            $headers['Content-Range'] = "bytes $start-$end/$size";
        }
        $headers['Content-Length'] = (string) ($end - $start + 1);

        return new StreamedResponse(function () use ($disk, $path, $start, $end) {
            $h = $disk->readStream($path);
            if (! $h) {
                return;
            }
            if ($start > 0) {
                fseek($h, $start);
            }
            $left = $end - $start + 1;
            while ($left > 0 && ! feof($h)) {
                $chunk = fread($h, min(65536, $left));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $left -= strlen($chunk);
                flush();
            }
            fclose($h);
        }, $status, $headers);
    }

    private function asciiName(string $n): string
    {
        $a = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $n);

        return $a !== '' && $a !== null ? $a : 'file';
    }
}
