<?php

namespace App\Services\Casting;

use App\Services\Casting\Exceptions\CastingProviderException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** All casting files live on the private disk under casting/… with random names; uploads are verified by content and re-encoded (metadata / polyglot payloads dropped). */
class CastingStorage
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('casting.private_disk'));
    }

    public static function diskName(): string
    {
        return (string) config('casting.private_disk');
    }

    /**
     * Write a casting file and make it (and its casting/… folders) group-readable: the queue worker and the web server run as different users in the same
     * group, and Laravel's default private permissions (0700 / 0600) would leave the web process unable to stream what the worker generated.
     */
    public static function put(string $path, string $bytes): void
    {
        self::disk()->put($path, $bytes);
        $root = config('filesystems.disks.'.self::diskName().'.root');
        if (! $root) {
            return;
        }
        $dir = dirname($path);
        $acc = '';
        foreach (explode('/', $dir) as $seg) {
            $acc .= ($acc === '' ? '' : '/').$seg;
            @chmod($root.'/'.$acc, 02775);
        }
        @chmod($root.'/'.$path, 0664);
    }

    /** @return array{path:string,mime:string,size:int,width:int,height:int,checksum:string} */
    public function storeBytes(string $dir, string $bytes, ?string $expectedMime = null): array
    {
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? null;
        $ext = config('casting.upload.mimes')[$mime] ?? null;
        if (! $info || ! $ext) {
            throw new CastingProviderException(__('casting.errors.bad_image'), false);
        }
        $path = trim($dir, '/').'/'.Str::random(40).'.'.$ext;
        self::put($path, $bytes);

        return ['path' => $path, 'mime' => $mime, 'size' => strlen($bytes), 'width' => $info[0], 'height' => $info[1], 'checksum' => hash('sha256', $bytes)];
    }

    /** Validate (real MIME by content, dimensions, size) and re-encode an uploaded image. */
    public function storeUpload(string $dir, UploadedFile $file): array
    {
        $fail = fn (string $k) => throw ValidationException::withMessages(['file' => __('casting.errors.'.$k)]);
        if (! $file->isValid() || $file->getSize() > config('casting.upload.max_kb') * 1024) {
            $fail('too_big');
        }
        $raw = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($raw);
        $finfo = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);
        $mimes = config('casting.upload.mimes');
        if (! $info || ! isset($mimes[$info['mime']]) || $finfo !== $info['mime']) {
            $fail('bad_image');
        }
        if (max($info[0], $info[1]) > config('casting.upload.max_px') || min($info[0], $info[1]) < config('casting.upload.min_px')) {
            $fail('bad_dimensions');
        }
        $im = @imagecreatefromstring($raw);
        if (! $im) {
            $fail('bad_image');
        }
        ob_start();
        match ($info['mime']) {
            'image/png' => imagepng($im),
            'image/webp' => imagewebp($im, null, 90),
            default => imagejpeg($im, null, 92),
        };
        $clean = (string) ob_get_clean();
        imagedestroy($im);

        return $this->storeBytes($dir, $clean);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            self::disk()->delete($path);
        }
    }
}
