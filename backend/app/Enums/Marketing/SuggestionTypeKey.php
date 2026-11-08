<?php

namespace App\Enums\Marketing;

/**
 * Suggestion types of the Suggestions Center (MCP.md §16/§17/§18/§19/§20/§21). Each has exactly one handler
 * (App\Services\Marketing\Suggestions\Types\*, registered in SuggestionTypeRegistry) and one marketer page (page()).
 */
enum SuggestionTypeKey: string
{
    case PRODUCT = 'product';
    case BUNDLE = 'bundle';
    case SLIDER = 'slider';
    case OFFER = 'offer';
    case COUPON = 'coupon';
    case SEO = 'seo';
    case BLOG = 'blog';
    case SEASONAL_THEME = 'seasonal_theme';
    case SOCIAL_CONTENT = 'social_content';
    case MEDIA_ASSET = 'media_asset';

    public function label(): string
    {
        return __('marketing.suggestions.types.'.$this->value);
    }

    /** Marketer page slug (route marketer.suggestions.{page}). */
    public function page(): string
    {
        return match ($this) {
            self::PRODUCT => 'products', self::BUNDLE => 'bundles', self::SLIDER => 'banners', self::OFFER => 'offers', self::COUPON => 'coupons',
            self::SEO => 'seo', self::BLOG => 'blog', self::SEASONAL_THEME => 'themes', self::SOCIAL_CONTENT, self::MEDIA_ASSET => 'social',
        };
    }

    /** @return list<self> types listed on a page */
    public static function forPage(string $page): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => $t->page() === $page));
    }

    public static function pages(): array
    {
        return ['products', 'bundles', 'banners', 'offers', 'coupons', 'themes', 'seo', 'blog', 'social'];
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
