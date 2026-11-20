<?php

namespace App\Services\ImagePicker;

use App\DTOs\ImagePickerImportData;
use App\Models\Admin;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageUploadService;
use App\Services\ProductService;
use App\Support\Http\SafeImageFetcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Product edit → "Find images" (config image_picker). Free web image search through tools/image-picker (Bing / DuckDuckGo via `ddgs`,
 * no API key), then an import of the picture the admin chose: downloaded with the SSRF-safe fetcher, validated and converted by the
 * normal upload pipeline (ImageUploadService: AVIF + thumbnails), and logged with the page it came from. Nothing is imported automatically.
 */
class ImagePickerService
{
    public function __construct(private ProductService $products, private ImageUploadService $uploads, private ?SafeImageFetcher $fetcher = null)
    {
        $this->fetcher ??= app(SafeImageFetcher::class);
    }

    // ------------------------------------------------------------------ panel data

    /** The product and its active variants, each with its own default search text. */
    public function targets(Product $product): array
    {
        $product->loadMissing(['brand', 'images', 'variants.values', 'variants.images']);
        $out = [['type' => 'product', 'id' => $product->id, 'label' => (string) $product->name, 'sku' => $product->sku,
            'query' => $this->query($product), 'thumb' => $product->primaryImageUrl()]];
        foreach ($product->variants->where('is_active', true) as $v) {
            $out[] = ['type' => 'variant', 'id' => $v->id, 'label' => $v->label(), 'sku' => $v->sku, 'query' => $this->query($product, $v),
                'thumb' => $v->primaryImageUrl() ?? $product->primaryImageUrl()];
        }

        return $out;
    }

    /** "Brand Name Variant-values", in English when the product has an English name (better web results), else the first language. */
    public function query(Product $product, ?ProductVariant $variant = null): string
    {
        $names = array_filter(array_map('trim', (array) $product->getTranslations('name')), 'strlen');
        $lang = isset($names['en']) ? 'en' : (array_key_first($names) ?? app()->getLocale());
        $name = $names[$lang] ?? '';
        $parts = [];
        $brand = $product->brand ? trim((string) ($product->brand->getTranslations('name')[$lang] ?? collect($product->brand->getTranslations('name'))->first())) : '';
        if ($brand !== '' && ! str_contains(mb_strtolower($name), mb_strtolower($brand))) {
            $parts[] = $brand;
        }
        $parts[] = $name;
        foreach ($variant?->values ?? [] as $value) {
            $t = (array) $value->getTranslations('value');
            $text = trim((string) ($t[$lang] ?? reset($t) ?: ''));
            if ($text !== '' && ! str_contains(mb_strtolower(implode(' ', $parts)), mb_strtolower($text))) {
                $parts[] = $text;
            }
        }

        return mb_substr(trim(preg_replace('/\s+/u', ' ', implode(' ', $parts))), 0, 200);
    }

    // ------------------------------------------------------------------ search

    /**
     * @return array{results: list<array>, has_more: bool, errors: array<string,string>}
     *
     * @throws ImagePickerUnavailableException
     */
    public function search(string $query, int $page = 1): array
    {
        $query = trim($query);
        $page = max(1, min(10, $page));
        $key = 'image_picker:'.hash('sha256', mb_strtolower($query).'|'.$page.'|'.config('image_picker.engines'));
        if (is_array($hit = Cache::get($key))) {
            return $hit;
        }

        $result = Process::timeout((int) config('image_picker.timeout', 45))->run([
            (string) config('image_picker.python'), (string) config('image_picker.script'), 'search', $query,
            '-n', '30', '--page', (string) $page, '--engines', (string) config('image_picker.engines'), '--no-cache',
        ]);
        $json = json_decode($result->output(), true);
        if (! $result->successful() || ! is_array($json) || ! is_array($json['results'] ?? null)) {
            report(new ImagePickerUnavailableException('image picker failed: '.mb_substr($result->errorOutput() ?: $result->output(), 0, 500)));
            throw new ImagePickerUnavailableException(__('admin.image_picker.unavailable'));
        }

        $results = [];
        foreach ($json['results'] as $r) {
            $image = self::url($r['image'] ?? null);
            if (! $image) {
                continue;
            }
            $results[] = ['id' => substr(hash('sha1', $image), 0, 12), 'image' => $image, 'thumbnail' => self::url($r['thumbnail'] ?? null) ?? $image,
                'page' => self::url($r['page'] ?? null), 'title' => mb_substr(strip_tags((string) ($r['title'] ?? '')), 0, 300),
                'source' => mb_substr((string) ($r['source'] ?? parse_url($image, PHP_URL_HOST)), 0, 255),
                'width' => isset($r['width']) ? (int) $r['width'] : null, 'height' => isset($r['height']) ? (int) $r['height'] : null,
                'engine' => (string) ($r['engine'] ?? '')];
        }
        $out = ['results' => $results, 'has_more' => (bool) ($json['has_more'] ?? false),
            'errors' => array_map(fn ($e) => mb_substr((string) $e, 0, 200), (array) ($json['errors'] ?? []))];
        if ($results !== []) { // never cache a failed / empty answer: the next try may work
            Cache::put($key, $out, now()->addHours((int) config('image_picker.cache_hours', 168)));
        }

        return $out;
    }

    // ------------------------------------------------------------------ import

    /** @throws ValidationException */
    public function import(Product $product, ImagePickerImportData $d, ?int $adminId): Image
    {
        $variant = null;
        if ($d->variantId) {
            $variant = $product->variants()->whereKey($d->variantId)->first()
                ?? throw ValidationException::withMessages(['variant_id' => __('admin.image_picker.bad_variant')]);
        }
        $bytes = $this->download($d);

        $tmp = tempnam(sys_get_temp_dir(), 'imgpick');
        file_put_contents($tmp, $bytes);
        try {
            // the upload pipeline checks the real content (type, size, pixels, animation) and converts it, exactly as for a manual upload
            $file = new UploadedFile($tmp, 'web-image', null, null, true);
            $image = DB::transaction(function () use ($product, $variant, $file, $d) {
                if ($variant && $d->as === 'main') {
                    $old = $variant->images()->pluck('id')->all();
                    $this->uploads->attach($variant, [$file], 'variants'); // new first, then the replaced photo (same as the product form)
                    $this->uploads->removeByIds($variant, $old);

                    return $variant->images()->reorder()->latest('id')->firstOrFail();
                }
                $ids = $this->products->addImages($product, [$file]);
                if ($d->as === 'main') {
                    $this->products->setMainImage($product, $ids[0]);
                }

                return Image::query()->findOrFail($ids[0]);
            });
        } finally {
            @unlink($tmp);
        }

        activity('image_picker')->causedBy($adminId ? Admin::find($adminId) : null)->performedOn($product)
            ->withProperties(['image_id' => $image->id, 'variant_id' => $variant?->id, 'as' => $d->as, 'source_image' => $d->imageUrl,
                'source_page' => $d->pageUrl, 'title' => $d->title])
            ->log('image_picker.imported');

        return $image;
    }

    /**
     * The original picture; when its site answers with something that is not a supported image (a bot check / hotlink page, SVG, …)
     * the search engine's copy (thumbnail) is used instead. Browser-like headers + the result page as Referer get past most hotlink blocks.
     *
     * @throws ValidationException
     */
    private function download(ImagePickerImportData $d): string
    {
        $max = (int) config('image_picker.download_max_bytes');
        $headers = ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
            'Accept' => 'image/webp,image/png,image/jpeg,image/avif,image/*;q=0.8'];
        $tries = array_values(array_unique(array_filter([$d->imageUrl, $d->thumbnailUrl])));
        $reason = null;
        foreach ($tries as $i => $url) {
            try {
                $bytes = $this->fetcher->fetch($url, $max, $i === 0 && $d->pageUrl ? [...$headers, 'Referer' => $d->pageUrl] : $headers);
            } catch (RuntimeException $e) {
                $reason ??= __('admin.image_picker.download_failed', ['reason' => $e->getMessage()]);

                continue;
            }
            $info = @getimagesizefromstring($bytes);
            if ($info !== false && in_array($info['mime'] ?? '', config('images.allowed_mimes'), true)) {
                return $bytes;
            }
            $reason ??= __('admin.image_picker.not_image');
            logger()->info('image_picker: not an image', ['url' => $url, 'mime' => $info['mime'] ?? null, 'start' => substr(preg_replace('/\s+/', ' ', $bytes), 0, 80)]);
        }

        throw ValidationException::withMessages(['image_url' => $reason ?? __('admin.image_picker.not_image')]);
    }

    private static function url(mixed $u): ?string
    {
        return is_string($u) && strlen($u) <= 2048 && preg_match('#^https?://[^\s<>"]+$#i', $u) ? $u : null;
    }
}
