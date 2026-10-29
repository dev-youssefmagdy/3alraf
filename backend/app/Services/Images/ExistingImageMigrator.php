<?php

namespace App\Services\Images;

use App\Models\ImageOptimization;
use App\Services\ImageUploadService;
use App\Support\MediaPaths;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Converts already-stored images to AVIF without ever touching the originals, then (optionally) repoints DB references.
 *
 *  1. sources()   : every image path the DB references, by category (authoritative: what the site actually serves)
 *  2. convert()   : source -> `<stem>.avif` beside it, verified (valid AVIF, within limit), ledger row written
 *  3. apply()     : UPDATE references old -> new, only for verified rows; revert() restores them from the ledger
 */
class ExistingImageMigrator
{
    /** category => [table, columns] for plain string columns. */
    public const COLUMNS = [
        'images' => ['images', ['path', 'thumb_path']],
        'brands' => ['brands', ['logo']],
        'categories' => ['categories', ['image']],
        'bundles' => ['bundles', ['image']],
        'packaging' => ['packaging_options', ['image']],
        'bundle_ab' => ['bundle_ab_variants', ['image']],
    ];

    /** category => [table, json column, keys holding paths ('*' = values of a locale map)]. */
    public const JSON = [
        'banners' => ['banners', 'images', ['*']],
        'ai' => ['ai_generations', 'media', ['path', 'thumb']],
    ];

    private const CONVERTIBLE = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'bmp'];

    public function __construct(private ImageOptimizer $optimizer) {}

    public static function categories(): array
    {
        return array_merge(array_keys(self::COLUMNS), array_keys(self::JSON));
    }

    /** @return \Generator<int,array{category:string,path:string}> distinct-by-caller; streams rows with a cursor (bounded memory) */
    public function sources(array $categories): \Generator
    {
        foreach ($categories as $cat) {
            if (isset(self::COLUMNS[$cat])) {
                [$table, $cols] = self::COLUMNS[$cat];
                foreach ($cols as $col) {
                    foreach (DB::table($table)->whereNotNull($col)->where($col, '!=', '')->select($col)->distinct()->cursor() as $row) {
                        yield ['category' => $cat, 'path' => $row->$col];
                    }
                }
            } else {
                [$table, $col, $keys] = self::JSON[$cat];
                foreach (DB::table($table)->whereNotNull($col)->select('id', $col)->cursor() as $row) {
                    foreach ($this->jsonPaths(json_decode($row->$col, true), $keys) as $p) {
                        yield ['category' => $cat, 'path' => $p];
                    }
                }
            }
        }
    }

    /** Convert one source. Idempotent: a verified, existing output is reused. Never deletes or overwrites the source. */
    public function convert(string $category, string $path, string $diskName, bool $retryFailed = false): ImageOptimization
    {
        $row = ImageOptimization::firstOrNew(['disk' => $diskName, 'source_path' => $path], ['category' => $category, 'profile' => ImageOptimizer::profileForPath($path)]);
        $disk = Storage::disk($diskName);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $final = $retryFailed ? ['optimized', 'skipped'] : ['optimized', 'skipped', 'exceeds_limit', 'invalid', 'failed', 'missing'];
        if ($row->exists && in_array($row->status, $final, true)) {
            return $row; // already handled: idempotent rerun
        }
        if (preg_match('#^https?://#i', $path) || $ext === 'avif' || ! in_array($ext, self::CONVERTIBLE, true)) {
            return $row->fill(['status' => 'skipped', 'reason' => 'external URL, already AVIF, or not a convertible raster type']); // not persisted: nothing to track
        }
        if (! $disk->exists($path)) {
            return $this->finish($row, 'missing', null, 'referenced file not found on disk');
        }

        $lock = Cache::lock("images:convert:$diskName:".md5($path), 600);
        if (! $lock->get()) {
            return $row; // another worker owns it
        }
        $tmp = tempnam(sys_get_temp_dir(), 'imgsrc');
        $out = MediaPaths::withExtension($path, 'avif');
        try {
            $this->download($disk, $path, $tmp);
            $r = $this->optimizer->optimize($tmp, $row->profile);
            $row->fill(['source_bytes' => $r->sourceBytes, 'output_bytes' => $r->size(), 'width' => $r->width, 'height' => $r->height, 'quality' => $r->quality]);
            if (! $r->withinLimit()) {
                return $this->finish($row, 'exceeds_limit', null, "best attempt {$r->size()} B > limit {$r->limit} B (q{$r->quality}, scale {$r->scale})");
            }
            $disk->put("$out.tmp", $r->bytes) || throw new \RuntimeException("Could not write $out.tmp (check directory permissions for the queue/web user).");
            $this->verify($disk, "$out.tmp", $r->limit);
            $disk->move("$out.tmp", $out);

            return $this->finish($row, 'optimized', $out);
        } catch (InvalidImageException $e) {
            return $this->finish($row, 'invalid', null, $e->getMessage());
        } catch (Throwable $e) {
            $disk->exists("$out.tmp") && $disk->delete("$out.tmp");

            return $this->finish($row, 'failed', null, substr($e::class.': '.$e->getMessage(), 0, 480));
        } finally {
            @unlink($tmp);
            $lock->release();
        }
    }

    /** Point DB references at the verified AVIF. Returns rows changed. Skipped for anything not `optimized`. */
    public function apply(ImageOptimization $row): int
    {
        if ($row->status !== 'optimized' || ! $row->output_path || $row->applied_at) {
            return 0;
        }
        if (! Storage::disk($row->disk)->exists($row->output_path)) {
            return 0; // output vanished: keep serving the original
        }
        $n = $this->swap($row->category, $row->source_path, $row->output_path);
        $row->update(['references_updated' => $n, 'applied_at' => now()]);

        return $n;
    }

    /** Roll references back to the originals (which are never deleted). */
    public function revert(ImageOptimization $row): int
    {
        if (! $row->applied_at) {
            return 0;
        }
        $n = $this->swap($row->category, $row->output_path, $row->source_path);
        $row->update(['applied_at' => null]);

        return $n;
    }

    private function swap(string $category, string $from, string $to): int
    {
        return DB::transaction(function () use ($category, $from, $to) {
            $n = 0;
            if (isset(self::COLUMNS[$category])) {
                [$table, $cols] = self::COLUMNS[$category];
                foreach ($cols as $col) {
                    $n += DB::table($table)->where($col, $from)->update([$col => $to]);
                }

                return $n;
            }
            [$table, $col, $keys] = self::JSON[$category];
            foreach (DB::table($table)->whereNotNull($col)->select('id', $col)->lockForUpdate()->get() as $row) {
                $data = json_decode($row->$col, true);
                $count = 0;
                $new = $this->mapJson($data, $keys, function ($p) use ($from, $to, &$count) {
                    if ($p !== $from) {
                        return $p;
                    }
                    $count++;

                    return $to;
                });
                if ($count) {
                    DB::table($table)->where('id', $row->id)->update([$col => json_encode($new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
                    $n += $count;
                }
            }

            return $n;
        });
    }

    /** @return string[] */
    private function jsonPaths(mixed $data, array $keys): array
    {
        $out = [];
        $this->mapJson($data, $keys, function ($p) use (&$out) {
            $out[] = $p;

            return $p;
        });

        return $out;
    }

    /** Walk a locale map (['*']) or a list of arrays keyed by $keys, applying $fn to each path string. */
    private function mapJson(mixed $data, array $keys, callable $fn): mixed
    {
        if (! is_array($data)) {
            return $data;
        }
        if ($keys === ['*']) {
            return array_map(fn ($p) => is_string($p) && $p !== '' ? $fn($p) : $p, $data);
        }

        return array_map(function ($item) use ($keys, $fn) {
            if (! is_array($item)) {
                return $item;
            }
            foreach ($keys as $k) {
                if (isset($item[$k]) && is_string($item[$k]) && $item[$k] !== '') {
                    $item[$k] = $fn($item[$k]);
                }
            }

            return $item;
        }, $data);
    }

    private function finish(ImageOptimization $row, string $status, ?string $out, ?string $reason = null): ImageOptimization
    {
        $row->fill(['status' => $status, 'output_path' => $out, 'reason' => $reason])->save();

        return $row;
    }

    private function download($disk, string $path, string $tmp): void
    {
        $in = $disk->readStream($path);
        is_resource($in) || throw new \RuntimeException("Could not open $path for reading.");
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        is_resource($in) && fclose($in);
    }

    /** The written file must decode as AVIF and fit the limit — checked on what is actually on the disk. */
    private function verify($disk, string $path, int $limit): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'imgver');
        try {
            $this->download($disk, $path, $tmp);
            $i = @getimagesize($tmp);
            if (filesize($tmp) > $limit || $i === false || $i['mime'] !== 'image/avif') {
                throw new \RuntimeException('Written output failed verification.');
            }
        } catch (Throwable $e) {
            $disk->delete($path);
            throw $e;
        } finally {
            @unlink($tmp);
        }
    }
}
