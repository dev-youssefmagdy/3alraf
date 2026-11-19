<?php

namespace App\Services\Marketing\Suggestions;

use App\Enums\Marketing\SuggestionStatus;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\MarketingSuggestion;
use App\Services\Images\ImageOptimizer;
use App\Services\Images\InvalidImageException;
use App\Support\Http\SafeImageFetcher;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Images proposed by URL in a new_product (images), category (image) or product_update (images_add) suggestion. Each URL is downloaded ONCE, when the suggestion is created or edited (SafeImageFetcher),
 * validated as a real image (ImageOptimizer::inspect) and kept on the PRIVATE disk under its content hash (suggestion-images/{sha256}.{ext}).
 * The payload stores that hash, so the approval is bound to the exact bytes the reviewer saw; apply re-checks the hash before using the file.
 * The public site never sees these files: reviewers get them through an authenticated marketer route, the catalog gets them through
 * ImageUploadService when the suggestion is applied. `marketing:prune-suggestion-images` removes files no open suggestion needs any more.
 */
class SuggestionImages
{
    public const DIR = 'suggestion-images';

    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/gif' => 'gif', 'image/bmp' => 'bmp'];

    /** Statuses whose staged images are still needed (review, approval or a retry of apply). */
    private const OPEN = [SuggestionStatus::PENDING, SuggestionStatus::UNDER_REVIEW, SuggestionStatus::EDITED, SuggestionStatus::APPROVED, SuggestionStatus::APPLYING, SuggestionStatus::FAILED];

    public function __construct(private SafeImageFetcher $fetcher, private ImageOptimizer $optimizer) {}

    public static function disk(): Filesystem
    {
        return Storage::disk(config('media.private_disk'));
    }

    /**
     * @return array{url:string,sha256:string,mime:string,width:int,height:int,bytes:int}|array{url:string,error:string}
     */
    public function stage(string $url): array
    {
        $url = trim($url);
        $index = self::DIR.'/urls/'.sha1($url).'.json';
        $disk = self::disk();
        if ($disk->exists($index) && is_array($meta = json_decode((string) $disk->get($index), true)) && isset($meta['sha256'])
            && $disk->exists($this->path($meta['sha256'], $meta['mime']))) {
            return ['url' => $url] + $meta;   // already downloaded: never fetched twice (edits re-normalise the payload)
        }

        try {
            $bytes = $this->fetcher->fetch($url, (int) config('images.max_source_bytes'));
            $tmp = tempnam(sys_get_temp_dir(), 'sgi');
            file_put_contents($tmp, $bytes);
            try {
                $info = $this->optimizer->inspect($tmp);   // real content type, size, pixel cap, no animated GIF
            } finally {
                @unlink($tmp);
            }
        } catch (InvalidImageException) {
            return ['url' => $url, 'error' => 'not_image'];
        } catch (RuntimeException $e) {
            $code = $e->getMessage();

            return ['url' => $url, 'error' => str_starts_with($code, 'http_') ? 'http_error' : $code];
        }

        $sha = hash('sha256', $bytes);
        $path = $this->path($sha, $info['mime']);
        $disk->exists($path) || $disk->put($path, $bytes);
        $meta = ['sha256' => $sha, 'mime' => $info['mime'], 'width' => $info['w'], 'height' => $info['h'], 'bytes' => $info['bytes']];
        $disk->put($index, json_encode($meta));

        return ['url' => $url] + $meta;
    }

    public function path(string $sha256, string $mime): string
    {
        return self::DIR.'/'.$sha256.'.'.(self::EXT[$mime] ?? 'img');
    }

    public function exists(array $image): bool
    {
        return isset($image['sha256'], $image['mime']) && self::disk()->exists($this->path($image['sha256'], $image['mime']));
    }

    /** Staged image as an UploadedFile for ImageUploadService, after checking the bytes still match the approved hash. */
    public function toUploadedFile(array $image, string $name): UploadedFile
    {
        $bytes = $this->exists($image) ? (string) self::disk()->get($this->path($image['sha256'], $image['mime'])) : '';
        if ($bytes === '' || ! hash_equals($image['sha256'], hash('sha256', $bytes))) {
            throw SuggestionException::make('image_missing', 409, ['url' => $image['url'] ?? null]);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'sgu');
        file_put_contents($tmp, $bytes);

        return new UploadedFile($tmp, $name.'.'.(self::EXT[$image['mime']] ?? 'img'), $image['mime'], null, true);
    }

    /** Remove staged files (and URL index entries) older than $days that no open new_product / category / product_update suggestion references. @return int files removed */
    public function prune(int $days = 7): int
    {
        $keep = [];
        MarketingSuggestion::query()->whereIn('type', [SuggestionTypeKey::NEW_PRODUCT->value, SuggestionTypeKey::CATEGORY->value, SuggestionTypeKey::PRODUCT_UPDATE->value])
            ->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN))
            ->select(['id', 'payload'])->chunkById(200, function ($rows) use (&$keep) {
                foreach ($rows as $s) {
                    $images = [...(array) ($s->payload['images'] ?? []), ...(array) ($s->payload['images_add'] ?? [])];   // new_product / product_update
                    isset($s->payload['image']) && $images[] = (array) $s->payload['image'];   // category: one image
                    foreach ($images as $img) {
                        isset($img['sha256']) && $keep[$img['sha256']] = true;
                    }
                }
            });
        $disk = self::disk();
        $cutoff = now()->subDays($days)->getTimestamp();
        $removed = 0;
        foreach ($disk->files(self::DIR) as $file) {
            $sha = pathinfo($file, PATHINFO_FILENAME);
            if (! isset($keep[$sha]) && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $removed++;
            }
        }
        foreach ($disk->files(self::DIR.'/urls') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }

        return $removed;
    }
}
