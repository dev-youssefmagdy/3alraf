<?php

namespace App\Services\Casting;

use App\Models\CastingLiveProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Imports a READ-ONLY snapshot of a 3alraf.com product from the store MCP's `get_product_details` data. The MCP output is untrusted store text: every field is
 * reduced to plain, length-limited text; the photo is downloaded only over https from an allow-listed host under /media/, size-limited, verified as an image by
 * content and re-encoded as PNG on the private disk. Nothing is ever written back to the live store.
 */
class LiveProductImporter
{
    public function __construct(private CastingStorage $storage) {}

    /** @param array $d the `data` object of get_product_details (or the whole envelope) */
    public function import(array $d, ?int $imageIndex = 0): CastingLiveProduct
    {
        $d = is_array($d['data'] ?? null) ? $d['data'] : $d;
        $liveId = (int) ($d['id'] ?? 0);
        if ($liveId < 1 || ! is_array($d['name'] ?? null)) {
            throw new InvalidArgumentException('Not a get_product_details payload (missing id / name).');
        }
        $plain = fn ($v, int $n) => is_scalar($v) ? Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v))), $n, '') : '';
        $loc = fn ($a, int $n) => array_filter(collect(['ar', 'en'])->mapWithKeys(fn ($l) => [$l => $plain(is_array($a) ? ($a[$l] ?? null) : null, $n)])->all());
        $desc = collect(['ar', 'en'])->mapWithKeys(fn ($l) => [$l => $plain($d['description'][$l]['plain'] ?? ($d['description'][$l] ?? null), 1500)])->filter()->all();
        $urls = $this->photoUrls($d);

        $row = CastingLiveProduct::query()->firstOrNew(['live_id' => $liveId]);
        $row->fill([
            'sku' => $plain($d['sku'] ?? null, 100) ?: null, 'name' => $loc($d['name'], 200) ?: ['en' => '#'.$liveId], 'description' => $desc,
            'categories' => collect((array) ($d['categories'] ?? []))->map(fn ($c) => $plain($c['name']['en'] ?? $c['name']['ar'] ?? null, 100))->filter()->values()->all(),
            'tags' => collect((array) ($d['tags'] ?? []))->map(fn ($t) => $plain(is_array($t) ? ($t['name']['en'] ?? $t['name']['ar'] ?? null) : $t, 60))->filter()->values()->all(),
            'price' => isset($d['pricing']['effective_price']) && is_numeric($d['pricing']['effective_price']) ? $d['pricing']['effective_price'] : null,
            'gift_recipients' => array_values(array_filter(array_map(fn ($x) => $plain($x, 40), (array) ($d['gift']['recipients'] ?? [])))),
            'url' => $this->liveUrl($d['urls']['en'] ?? $d['urls']['ar'] ?? null), 'image_urls' => $urls, 'fetched_at' => now(),
        ])->save();

        if ($urls && $imageIndex !== null && isset($urls[$imageIndex])) {
            $png = $this->download($urls[$imageIndex]);
            $f = $this->storage->storeBytes('casting/live-products/'.$liveId, $png);
            $old = $row->image_path;
            $row->forceFill(['image_disk' => CastingStorage::diskName(), 'image_path' => $f['path']])->save();
            $this->storage->delete($old);
        }

        return $row->refresh();
    }

    /** Photo URLs (product gallery first, then variants), https + allow-listed host + /media/ only. */
    private function photoUrls(array $d): array
    {
        $all = collect((array) ($d['images'] ?? []))->pluck('url')
            ->merge(collect((array) ($d['variants'] ?? []))->flatMap(fn ($v) => collect((array) ($v['images'] ?? []))->pluck('url')));

        return $all->filter(fn ($u) => is_string($u) && $this->allowedImageUrl($u))->unique()->values()->take(12)->all();
    }

    private function allowedImageUrl(string $u): bool
    {
        $p = parse_url($u);

        return ($p['scheme'] ?? '') === 'https' && in_array(strtolower($p['host'] ?? ''), (array) config('casting.live.image_hosts'), true)
            && str_starts_with($p['path'] ?? '', '/media/') && ! isset($p['user']) && ! isset($p['port']);
    }

    private function liveUrl(?string $u): ?string
    {
        $p = is_string($u) ? parse_url($u) : null;

        return $p && ($p['scheme'] ?? '') === 'https' && in_array(strtolower($p['host'] ?? ''), (array) config('casting.live.image_hosts'), true) ? Str::limit($u, 500, '') : null;
    }

    /** Download (size-limited), verify it is an image, return PNG bytes (AVIF / WebP / JPEG are all converted; max side 1536). */
    private function download(string $url): string
    {
        if (! $this->allowedImageUrl($url)) {
            throw new InvalidArgumentException('Photo URL is not on an allowed live host.');
        }
        $r = Http::timeout(30)->connectTimeout(8)->withOptions(['allow_redirects' => false])->get($url);
        if (! $r->successful() || strlen($r->body()) > 8 * 1024 * 1024 || strlen($r->body()) < 200) {
            throw new InvalidArgumentException('Could not download the product photo (HTTP '.$r->status().').');
        }
        $im = @imagecreatefromstring($r->body());
        if (! $im) {
            throw new InvalidArgumentException('The downloaded file is not a readable image.');
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $scale = min(1, 1536 / max($w, $h));
        if ($scale < 1) {
            $im = imagescale($im, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        }
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }
}
