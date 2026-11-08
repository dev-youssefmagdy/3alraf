<?php

namespace App\Services\Marketing\Suggestions;

use App\Enums\Marketing\SuggestionTypeKey as K;
use App\Services\Marketing\Suggestions\Contracts\SuggestionType;
use App\Services\Marketing\Suggestions\Types;
use Illuminate\Contracts\Container\Container;

/** type key → handler (one explicit class per type, MCP.md §23). Adding a type = enum case + handler + an entry here. */
class SuggestionTypeRegistry
{
    public const HANDLERS = [
        'product' => Types\ProductSuggestionType::class,
        'bundle' => Types\BundleSuggestionType::class,
        'slider' => Types\SliderSuggestionType::class,
        'offer' => Types\OfferSuggestionType::class,
        'coupon' => Types\CouponSuggestionType::class,
        'seo' => Types\SeoSuggestionType::class,
        'blog' => Types\BlogSuggestionType::class,
        'seasonal_theme' => Types\SeasonalThemeSuggestionType::class,
        'social_content' => Types\SocialContentSuggestionType::class,
        'media_asset' => Types\MediaAssetSuggestionType::class,
    ];

    private array $resolved = [];

    public function __construct(private Container $app) {}

    public function get(K|string $type): SuggestionType
    {
        $key = $type instanceof K ? $type->value : $type;
        if (! isset(self::HANDLERS[$key])) {
            throw SuggestionException::make('unknown_type', 404);
        }

        return $this->resolved[$key] ??= $this->app->make(self::HANDLERS[$key]);
    }

    public function has(string $type): bool
    {
        return isset(self::HANDLERS[$type]);
    }
}
