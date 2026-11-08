<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Services\Marketing\Media\SocialMediaLibraryService;
use App\Services\Marketing\Suggestions\ApplyResult;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Support\Marketing\CanvaUrl;
use Illuminate\Database\Eloquent\Model;

/**
 * Social types (P5 schema, P6 apply): social_content (post idea) and media_asset (image / video brief) for a product.
 * Apply = create a PRIVATE draft item in the product's social-media library through SocialMediaLibraryService (kind `post`, status `draft`,
 * source `suggestion`) — nothing is ever published to a social network or to the storefront. The item id is the suggestion's result entity
 * (`social_media_asset`). Rollback = archive that item (kept, never hard-deleted). No snapshot: the product's own fields are not changed,
 * so there is nothing to go stale (a deleted product ⇒ target_not_found).
 * rules() are unchanged from P5 (the MCP server, P7, sends exactly this schema: docs/marketing/MCP_SUGGESTION_TOOLS.md).
 */
abstract class AbstractPendingSocialType extends AbstractSuggestionType
{
    public const PLATFORMS = ['facebook', 'instagram', 'instagram_story', 'reels', 'tiktok', 'whatsapp', 'other'];

    protected array $targetModels = ['product' => Product::class];

    public function __construct(protected SocialMediaLibraryService $media) {}

    /** Library item fields (SocialMediaAssetData::fromArray keys) built from the normalised payload. */
    abstract protected function libraryFields(array $payload, MarketingSuggestion $s): array;

    public function targetRequired(?array $payload = null): bool
    {
        return true;
    }

    public function applicable(): bool
    {
        return true;
    }

    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array
    {
        return ['errors' => [], 'warnings' => [__('marketing.suggestions.warnings.social_private')]];
    }

    public function snapshot(?Model $target, array $payload): ?array
    {
        return null;
    }

    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult
    {
        if (! $target instanceof Product) {
            throw SuggestionException::make('target_not_found', 409);
        }
        $a = $this->media->createFromSuggestion($target, $this->libraryFields((array) $s->payload, $s), $s->id, $adminId);

        return new ApplyResult(['created' => false], ['created' => true, 'social_media_asset_id' => $a->id, 'kind' => $a->kind->value, 'status' => $a->status->value,
            'published' => false], 'social_media_asset', $a->id, [__('marketing.suggestions.warnings.social_private')]);
    }

    public function supportsRollback(): bool
    {
        return true;
    }

    /** Archive the library item created by apply (it stays in the library history; files are never deleted). */
    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        $a = $s->result_entity_id ? SocialMediaAsset::query()->find($s->result_entity_id) : null;
        if ($a) {
            $this->media->archive($a);
        }

        return ['social_media_asset_id' => $s->result_entity_id, 'archived' => (bool) $a];
    }

    /** https://(www.)canva.com/... only (Canva asset links; MCP.md §6). */
    protected static function canvaUrl(mixed $v): ?string
    {
        return CanvaUrl::normalize($v);
    }

    protected function canvaRule(): array
    {
        return ['nullable', 'string', 'max:500', fn ($a, $v, $fail) => self::canvaUrl($v) ? null : $fail(__('marketing.suggestions.errors.canva_url'))];
    }
}
