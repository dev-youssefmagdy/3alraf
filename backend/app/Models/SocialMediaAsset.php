<?php

namespace App\Models;

use App\Enums\Marketing\MediaAssetKind;
use App\Enums\Marketing\MediaAssetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Private product social-media library item (P6, MCP.md §21; docs/marketing/SOCIAL_MEDIA_LIBRARY.md). The file lives on the PRIVATE disk and
 * is only reachable through authorised controller routes (marketer.media.file / .thumb / .download) or a short-lived signed preview URL.
 * Deliberately NOT a relation of Product and never part of the storefront gallery, product feeds, API v1 resources or the sitemap.
 * Written only by App\Services\Marketing\Media\SocialMediaLibraryService.
 */
class SocialMediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = ['product_id', 'kind', 'title', 'caption', 'platforms', 'format', 'aspect_ratio', 'purpose', 'campaign', 'tags', 'hashtags', 'cta', 'brief',
        'suggested_publish_at', 'canva_url', 'generation_meta', 'disk', 'path', 'thumb_path', 'original_name', 'mime', 'size_bytes', 'width', 'height',
        'duration_seconds', 'checksum', 'version', 'status', 'review_note', 'reviewed_by', 'reviewed_at', 'source', 'suggestion_id', 'created_by'];

    /** Storage paths are internal: never serialised. */
    protected $hidden = ['disk', 'path', 'thumb_path', 'checksum'];

    protected function casts(): array
    {
        return ['kind' => MediaAssetKind::class, 'status' => MediaAssetStatus::class, 'caption' => 'array', 'platforms' => 'array', 'tags' => 'array',
            'hashtags' => 'array', 'generation_meta' => 'array', 'suggested_publish_at' => 'datetime', 'reviewed_at' => 'datetime', 'version' => 'int',
            'size_bytes' => 'int', 'width' => 'int', 'height' => 'int', 'duration_seconds' => 'int'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SocialMediaAssetVersion::class, 'asset_id')->orderByDesc('version');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(MarketingSuggestion::class, 'suggestion_id');
    }

    public function hasFile(): bool
    {
        return $this->path !== null;
    }

    public function isVideo(): bool
    {
        return $this->hasFile() && str_starts_with((string) $this->mime, 'video/');
    }

    public function isImage(): bool
    {
        return $this->hasFile() && str_starts_with((string) $this->mime, 'image/');
    }

    /** Caption in the current locale (fallback: any). */
    public function captionText(?string $locale = null): ?string
    {
        $c = (array) $this->caption;

        return ($c[$locale ?? app()->getLocale()] ?? null) ?: (collect($c)->filter()->first() ?: null);
    }
}
