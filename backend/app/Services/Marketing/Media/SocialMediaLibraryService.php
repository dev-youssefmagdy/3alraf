<?php

namespace App\Services\Marketing\Media;

use App\DTOs\Marketing\SocialMediaAssetData;
use App\Enums\Marketing\MediaAssetKind as K;
use App\Enums\Marketing\MediaAssetStatus as S;
use App\Models\Admin;
use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Models\SocialMediaAssetVersion;
use App\Repositories\Contracts\SocialMediaAssetRepositoryInterface;
use App\Services\Marketing\MarketingActionException as E;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Private product social-media library (P6, MCP.md §21; docs/marketing/SOCIAL_MEDIA_LIBRARY.md).
 * - Files go to the PRIVATE disk (config media.private_disk) under social-media/{product}/…, random names; never a public URL.
 * - Upload validation beyond the FormRequest: real MIME by content (finfo), image decoded (getimagesize, dimension limits), video container
 *   signature (MP4/MOV `ftyp`, WebM EBML), size per kind. SVG / HTML / anything else refused.
 * - Every new file = a new version row (history kept); a new file or caption change on an approved item sends it back to review.
 * - Approval (approve / reject) is a separate permission (ai_media.approve), checked by the routes and again here.
 * - Items can be created from an approved social_content / media_asset suggestion (never published anywhere).
 */
class SocialMediaLibraryService
{
    public function __construct(private SocialMediaAssetRepositoryInterface $repo) {}

    public static function disk(): Filesystem
    {
        return Storage::disk(config('media.private_disk'));
    }

    public function repo(): SocialMediaAssetRepositoryInterface
    {
        return $this->repo;
    }

    /** Library filters from a raw query array (marketer page + admin product tab). Unknown values are dropped. */
    public static function filtersFrom(array $q): array
    {
        $str = fn (string $k, int $max) => is_string($q[$k] ?? null) && trim($q[$k]) !== '' ? mb_substr(trim($q[$k]), 0, $max) : null;

        return array_filter([
            'product_id' => (int) (is_scalar($q['product_id'] ?? null) ? $q['product_id'] : 0) ?: null,
            'kind' => in_array($q['kind'] ?? null, K::values(), true) ? $q['kind'] : null,
            'status' => in_array($q['status'] ?? null, S::values(), true) ? $q['status'] : null,
            'platform' => in_array($q['platform'] ?? null, SocialMediaAssetData::PLATFORMS, true) ? $q['platform'] : null,
            'campaign' => $str('campaign', 120), 'tag' => $str('tag', 60), 'search' => $str('q', 100),
        ], fn ($v) => $v !== null);
    }

    /** View data of the shared library partial (marketer.media._library). $product = the admin product tab (library scoped to it). */
    public function libraryData(array $query, ?Product $product = null): array
    {
        $f = self::filtersFrom($query);
        if ($product) {
            $f['product_id'] = $product->id;
        }

        return ['items' => $this->repo->filtered($f)->paginate((int) config('marketing.media.per_page', 24))->withQueryString(), 'filters' => $f,
            'view' => ($query['view'] ?? null) === 'list' ? 'list' : 'grid', 'product' => $product, 'campaigns' => $this->repo->campaigns($product?->id),
            'counts' => $this->repo->counts($product?->id), 'products' => $product ? collect() : Product::query()->orderByDesc('id')->limit(2000)->get(['id', 'name', 'sku'])];
    }

    // ------------------------------------------------------------------ create / update

    /** New item: a file (image / video) or a text-only post draft (kind=post, file optional). */
    public function create(Product $product, SocialMediaAssetData $d, ?UploadedFile $file, ?Admin $by, string $source = 'upload', ?int $suggestionId = null): SocialMediaAsset
    {
        $info = $file ? $this->inspect($file) : null;
        $kind = $info['kind'] ?? ($d->kind ?: K::POST->value);
        if (! $info && $kind !== K::POST->value) {
            throw E::validation(['file' => __('marketing.media.errors.file_required')]);
        }
        if ($info && $d->kind && $d->kind !== K::POST->value && $d->kind !== $info['kind']) {
            throw E::validation(['file' => __('marketing.media.errors.kind_mismatch')]);
        }
        $stored = $info ? $this->store($product->id, $file, $info) : null;

        try {
            return DB::transaction(function () use ($product, $d, $info, $stored, $kind, $by, $source, $suggestionId) {
                $cols = $d->columns();
                $a = $this->repo->create(array_merge($cols, [
                    'product_id' => $product->id, 'kind' => $kind, 'title' => $d->title ?: ($stored['original_name'] ?? __('marketing.media.untitled')),
                    'status' => $stored ? S::PENDING_REVIEW : S::DRAFT, 'version' => 1, 'source' => $source, 'suggestion_id' => $suggestionId, 'created_by' => $by?->id,
                ], $stored ? $this->fileColumns($stored, $d) : []));
                if ($stored) {
                    $this->version($a, $stored, $d, $by, 1);
                }

                return $a;
            });
        } catch (\Throwable $e) {
            $stored && $this->deleteFiles($stored);
            throw $e;
        }
    }

    /** Upload a new file for an existing item: version + 1, back to review. */
    public function addVersion(SocialMediaAsset $a, UploadedFile $file, SocialMediaAssetData $d, ?Admin $by): SocialMediaAsset
    {
        $this->ensureNotArchived($a);
        $info = $this->inspect($file);
        if ($a->kind !== K::POST && $a->kind->value !== $info['kind']) {
            throw E::validation(['file' => __('marketing.media.errors.kind_mismatch')]);
        }
        $stored = $this->store($a->product_id, $file, $info);
        try {
            return DB::transaction(function () use ($a, $stored, $d, $by, $info) {
                $a = $this->repo->lockForUpdate($a->id);
                $v = $a->version + ($a->hasFile() ? 1 : 0);
                $this->repo->update($a, $this->fileColumns($stored, $d) + ['kind' => $info['kind'], 'version' => $v, 'status' => S::PENDING_REVIEW,
                    'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null]);
                $this->version($a, $stored, $d, $by, $v);

                return $a->refresh();
            });
        } catch (\Throwable $e) {
            $this->deleteFiles($stored);
            throw $e;
        }
    }

    /** Metadata edit (partial). Caption / title / brief change on an approved item ⇒ back to review. */
    public function update(SocialMediaAsset $a, SocialMediaAssetData $d): SocialMediaAsset
    {
        $this->ensureNotArchived($a);
        $cols = $d->columns(true);
        if (array_key_exists('title', $cols) && $cols['title'] === null) {
            unset($cols['title']);
        }
        if (array_key_exists('duration_seconds', $cols) && ! $a->isVideo()) {
            unset($cols['duration_seconds']);
        }
        $changed = array_filter($cols, fn ($v, $k) => json_encode($v, JSON_UNESCAPED_UNICODE) !== json_encode($this->raw($a, $k), JSON_UNESCAPED_UNICODE), ARRAY_FILTER_USE_BOTH);
        if (! $changed) {
            return $a;
        }
        if ($a->status === S::APPROVED && array_intersect(array_keys($changed), ['title', 'caption', 'brief', 'hashtags', 'cta', 'canva_url'])) {
            $changed += ['status' => S::PENDING_REVIEW, 'reviewed_by' => null, 'reviewed_at' => null];
        }

        return $this->repo->update($a, $changed);
    }

    private function raw(SocialMediaAsset $a, string $k): mixed
    {
        $v = $a->getAttribute($k);

        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v;
    }

    // ------------------------------------------------------------------ review

    public function submit(SocialMediaAsset $a): SocialMediaAsset
    {
        if (! in_array($a->status, [S::DRAFT, S::REJECTED], true)) {
            throw E::make('media', 'illegal_transition', 409, ['status' => $a->status->value]);
        }

        return $this->repo->update($a, ['status' => S::PENDING_REVIEW]);
    }

    public function approve(SocialMediaAsset $a, Admin $by, ?string $note = null): SocialMediaAsset
    {
        return $this->decide($a, $by, S::APPROVED, $note);
    }

    public function reject(SocialMediaAsset $a, Admin $by, ?string $note = null): SocialMediaAsset
    {
        return $this->decide($a, $by, S::REJECTED, $note);
    }

    private function decide(SocialMediaAsset $a, Admin $by, S $to, ?string $note): SocialMediaAsset
    {
        if (! $by->can('ai_media.approve')) {
            throw E::make('media', 'forbidden', 403);
        }
        if (! in_array($a->status, [S::DRAFT, S::PENDING_REVIEW, $to === S::APPROVED ? S::REJECTED : S::APPROVED], true)) {
            throw E::make('media', 'illegal_transition', 409, ['status' => $a->status->value]);
        }

        return $this->repo->update($a, ['status' => $to, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => $note ? mb_substr(trim($note), 0, 1000) : null]);
    }

    public function archive(SocialMediaAsset $a): SocialMediaAsset
    {
        return $a->status === S::ARCHIVED ? $a : $this->repo->update($a, ['status' => S::ARCHIVED]);
    }

    /** Delete = soft delete (the row and its private files stay restorable by a developer; never reachable from the app any more). */
    public function delete(SocialMediaAsset $a): void
    {
        $this->repo->delete($a);
    }

    private function ensureNotArchived(SocialMediaAsset $a): void
    {
        if ($a->status === S::ARCHIVED) {
            throw E::make('media', 'archived', 409);
        }
    }

    // ------------------------------------------------------------------ suggestions (P5 social_content / media_asset apply)

    /** Called inside the engine's apply transaction: a private draft item, never published anywhere. */
    public function createFromSuggestion(Product $product, array $fields, int $suggestionId, ?int $adminId): SocialMediaAsset
    {
        $by = $adminId ? Admin::query()->find($adminId) : null;

        return $this->create($product, SocialMediaAssetData::fromArray($fields + ['kind' => K::POST->value]), null, $by, 'suggestion', $suggestionId);
    }

    // ------------------------------------------------------------------ files

    /** Validated facts about an upload (throws a validation refusal). */
    public function inspect(UploadedFile $file): array
    {
        $cfg = (array) config('marketing.media');
        $path = $file->getRealPath();
        if (! $file->isValid() || ! $path || ! is_file($path)) {
            throw E::validation(['file' => __('marketing.media.errors.upload_failed')]);
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $size = (int) filesize($path);
        if (in_array($mime, $cfg['image_mimes'], true)) {
            $i = @getimagesize($path);
            if (! $i || ($i['mime'] ?? null) !== $mime) {
                throw E::validation(['file' => __('marketing.media.errors.bad_image')]);
            }
            [$w, $h] = [(int) $i[0], (int) $i[1]];
            if ($w < $cfg['min_dimension'] || $h < $cfg['min_dimension'] || $w > $cfg['max_dimension'] || $h > $cfg['max_dimension']) {
                throw E::validation(['file' => __('marketing.media.errors.dimensions', ['min' => $cfg['min_dimension'], 'max' => $cfg['max_dimension']])]);
            }
            if ($size > $cfg['image_max_kb'] * 1024) {
                throw E::validation(['file' => __('marketing.media.errors.too_big', ['mb' => round($cfg['image_max_kb'] / 1024)])]);
            }

            return ['kind' => 'image', 'mime' => $mime, 'size' => $size, 'width' => $w, 'height' => $h, 'ext' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime]];
        }
        if (in_array($mime, $cfg['video_mimes'], true)) {
            $head = (string) file_get_contents($path, false, null, 0, 16);
            $ok = $mime === 'video/webm' ? str_starts_with($head, "\x1A\x45\xDF\xA3") : substr($head, 4, 4) === 'ftyp';
            if (! $ok) {
                throw E::validation(['file' => __('marketing.media.errors.bad_video')]);
            }
            if ($size > $cfg['video_max_kb'] * 1024) {
                throw E::validation(['file' => __('marketing.media.errors.too_big', ['mb' => round($cfg['video_max_kb'] / 1024)])]);
            }

            return ['kind' => 'video', 'mime' => $mime, 'size' => $size, 'width' => null, 'height' => null, 'ext' => ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'][$mime]];
        }
        throw E::validation(['file' => __('marketing.media.errors.unsupported')]);
    }

    private function store(int $productId, UploadedFile $file, array $info): array
    {
        $disk = self::disk();
        $dir = trim((string) config('marketing.media.dir', 'social-media'), '/').'/'.$productId.'/'.date('Y/m');
        $name = Str::lower(Str::random(32));
        $path = "$dir/$name.{$info['ext']}";
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            if (! $disk->put($path, $stream)) {
                throw E::validation(['file' => __('marketing.media.errors.upload_failed')]);
            }
        } finally {
            is_resource($stream) && fclose($stream);
        }
        $thumb = null;
        if ($info['kind'] === 'image') {
            try {
                $px = (int) config('marketing.media.thumb_px', 480);
                $bytes = (string) (new ImageManager(new Driver))->decodePath($file->getRealPath())->scaleDown($px, $px)->encodeUsingFormat(Format::WEBP, quality: 75);
                if ($bytes !== '' && $disk->put($t = "$dir/{$name}_thumb.webp", $bytes)) {
                    $thumb = $t;
                }
            } catch (\Throwable $e) {
                report($e);   // no thumbnail: the preview falls back to the (protected) original
            }
        }

        return $info + ['path' => $path, 'thumb_path' => $thumb, 'disk' => (string) config('media.private_disk'),
            'original_name' => mb_substr(Str::of($file->getClientOriginalName())->replaceMatches('/[\x00-\x1F\x7F\/\\\\]/u', '')->trim()->value() ?: 'file', 0, 255),
            'checksum' => hash_file('sha256', $file->getRealPath())];
    }

    private function fileColumns(array $s, SocialMediaAssetData $d): array
    {
        return ['disk' => $s['disk'], 'path' => $s['path'], 'thumb_path' => $s['thumb_path'], 'original_name' => $s['original_name'], 'mime' => $s['mime'],
            'size_bytes' => $s['size'], 'width' => $s['width'], 'height' => $s['height'], 'checksum' => $s['checksum'],
            'duration_seconds' => $s['kind'] === 'video' ? $d->durationSeconds : null];
    }

    private function version(SocialMediaAsset $a, array $s, SocialMediaAssetData $d, ?Admin $by, int $v): void
    {
        SocialMediaAssetVersion::create(['asset_id' => $a->id, 'version' => $v, 'disk' => $s['disk'], 'path' => $s['path'], 'thumb_path' => $s['thumb_path'],
            'original_name' => $s['original_name'], 'mime' => $s['mime'], 'size_bytes' => $s['size'], 'width' => $s['width'], 'height' => $s['height'],
            'duration_seconds' => $s['kind'] === 'video' ? $d->durationSeconds : null, 'checksum' => $s['checksum'], 'note' => $d->note, 'uploaded_by' => $by?->id]);
    }

    private function deleteFiles(array $s): void
    {
        self::disk()->delete(array_filter([$s['path'] ?? null, $s['thumb_path'] ?? null]));
    }

    /**
     * Stream a file of an item (current or a given version; original or thumbnail) with private, no-store, nosniff, noindex headers.
     * Range requests work for local disks (video seeking). Authorisation is done by the caller (route `can:` / signed URL).
     */
    public function fileResponse(SocialMediaAsset $a, ?int $version = null, bool $thumb = false, bool $download = false): Response
    {
        $src = $a;
        if ($version !== null && $version !== $a->version) {
            $src = SocialMediaAssetVersion::query()->where('asset_id', $a->id)->where('version', $version)->first();
        }
        $path = $src ? ($thumb ? ($src->thumb_path ?: $src->path) : $src->path) : null;
        abort_if(! $path || str_contains($path, '..'), 404);
        $diskName = $src->disk ?: (string) config('media.private_disk');
        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($path), 404);
        $headers = ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox", 'Referrer-Policy' => 'no-referrer'];
        $name = Str::slug(pathinfo((string) $src->original_name, PATHINFO_FILENAME)) ?: 'asset-'.$a->id;
        $name .= '-v'.($src->version ?? $a->version).'.'.pathinfo($path, PATHINFO_EXTENSION);
        if ($disk instanceof FilesystemAdapter && config("filesystems.disks.$diskName.driver") === 'local' && is_file($abs = $disk->path($path))) {
            $r = response()->file($abs, $headers + ['Content-Type' => $thumb && $src->thumb_path ? 'image/webp' : $src->mime]);
            $r->setContentDisposition($download ? 'attachment' : 'inline', $name, 'asset.'.pathinfo($path, PATHINFO_EXTENSION));
            $r->headers->set('Cache-Control', $headers['Cache-Control']);   // BinaryFileResponse defaults to `public`: these files never are

            return $r;
        }

        return $download ? $disk->download($path, $name, $headers) : $disk->response($path, $name, $headers);
    }

    /** Short-lived signed preview URL (MCP / API): no session needed, expires after marketing.media.signed_preview_minutes. */
    public function signedPreviewUrl(SocialMediaAsset $a, bool $thumb = false): ?string
    {
        if (! $a->hasFile() || $a->trashed()) {
            return null;
        }

        return URL::temporarySignedRoute('marketing-media.preview', now()->addMinutes((int) config('marketing.media.signed_preview_minutes', 10)),
            ['id' => $a->id, 'v' => $a->version, 'thumb' => $thumb ? 1 : 0]);
    }

    // ------------------------------------------------------------------ presentation (metadata only — never storage paths)

    public function present(SocialMediaAsset $a, bool $withVersions = false, bool $signed = false): array
    {
        $out = [
            'id' => $a->id, 'product_id' => $a->product_id, 'product' => $a->relationLoaded('product') && $a->product ? ['id' => $a->product->id, 'name' => $a->product->name, 'sku' => $a->product->sku] : null,
            'kind' => $a->kind->value, 'title' => $a->title, 'caption' => (object) ($a->caption ?? []), 'platforms' => $a->platforms ?? [], 'format' => $a->format,
            'aspect_ratio' => $a->aspect_ratio, 'purpose' => $a->purpose, 'campaign' => $a->campaign, 'tags' => $a->tags ?? [], 'hashtags' => $a->hashtags ?? [],
            'cta' => $a->cta, 'brief' => $a->brief, 'suggested_publish_at' => $a->suggested_publish_at?->toIso8601String(), 'canva_url' => $a->canva_url,
            'generation_meta' => $a->generation_meta, 'has_file' => $a->hasFile(),
            'file' => $a->hasFile() ? ['original_name' => $a->original_name, 'mime' => $a->mime, 'size_bytes' => $a->size_bytes, 'width' => $a->width, 'height' => $a->height,
                'duration_seconds' => $a->duration_seconds, 'checksum_sha256' => $a->checksum] : null,
            'version' => $a->version, 'status' => $a->status->value, 'review_note' => $a->review_note, 'reviewed_at' => $a->reviewed_at?->toIso8601String(),
            'source' => $a->source, 'suggestion_id' => $a->suggestion_id, 'created_at' => $a->created_at?->toIso8601String(), 'updated_at' => $a->updated_at?->toIso8601String(),
            'internal_only' => true,
        ];
        if ($signed) {
            $out['preview_url'] = $this->signedPreviewUrl($a);
            $out['thumb_url'] = $a->isImage() ? $this->signedPreviewUrl($a, true) : null;
            $out['preview_expires_in_minutes'] = $a->hasFile() ? (int) config('marketing.media.signed_preview_minutes', 10) : null;
        }
        if ($withVersions) {
            $out['versions'] = $a->versions()->get()->map(fn (SocialMediaAssetVersion $v) => ['version' => $v->version, 'original_name' => $v->original_name, 'mime' => $v->mime,
                'size_bytes' => $v->size_bytes, 'width' => $v->width, 'height' => $v->height, 'duration_seconds' => $v->duration_seconds, 'note' => $v->note,
                'created_at' => $v->created_at?->toIso8601String()])->all();
        }

        return $out;
    }
}
