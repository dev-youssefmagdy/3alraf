<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;

/**
 * tenants.settings.seo — search-engine / analytics configuration of the storefront.
 * Every tracking id is pattern-validated (SeoSettingsRequest) because it is echoed into inline <script> on every page.
 */
class SeoSettingsService
{
    /** id key => validation regex (also used by the request). */
    public const ID_PATTERNS = [
        'ga4_id' => '/^G-[A-Z0-9]{6,14}$/',
        'gtm_id' => '/^GTM-[A-Z0-9]{4,10}$/',
        'google_ads_id' => '/^AW-\d{6,12}$/',
        'google_ads_label' => '/^[A-Za-z0-9_\-]{6,40}$/',
        'meta_pixel_id' => '/^\d{8,20}$/',
        'tiktok_pixel_id' => '/^[A-Z0-9]{10,30}$/',
        'snapchat_pixel_id' => '/^[A-Za-z0-9\-]{10,60}$/',
        'clarity_id' => '/^[a-z0-9]{8,14}$/',
        'google_verification' => '/^[A-Za-z0-9_\-]{20,100}$/',
        'bing_verification' => '/^[A-Za-z0-9_\-]{16,100}$/',
        'yandex_verification' => '/^[A-Za-z0-9_\-]{8,100}$/',
        'pinterest_verification' => '/^[A-Za-z0-9_\-]{16,100}$/',
        'facebook_domain_verification' => '/^[A-Za-z0-9_\-]{16,100}$/',
    ];

    public const SOCIALS = ['facebook', 'instagram', 'x', 'youtube', 'tiktok', 'linkedin', 'pinterest', 'snapchat'];

    public const SCALARS = ['ga4_id', 'gtm_id', 'google_ads_id', 'google_ads_label', 'meta_pixel_id', 'tiktok_pixel_id', 'snapchat_pixel_id', 'clarity_id',
        'google_verification', 'bing_verification', 'yandex_verification', 'pinterest_verification', 'facebook_domain_verification',
        'twitter_handle', 'org_phone', 'org_email', 'org_type', 'org_street', 'org_city', 'org_region', 'org_postal', 'org_country', 'default_og_image', 'org_logo'];

    /** Request-local cache for the layout (rendered on every storefront page). */
    private ?array $current = null;

    public function __construct(private TenantRepositoryInterface $tenants) {}

    public function get(Tenant $tenant): array
    {
        $saved = (array) (($tenant->settings ?? [])['seo'] ?? []);
        $out = ['indexable' => (bool) ($saved['indexable'] ?? true), 'track_staff' => (bool) ($saved['track_staff'] ?? false),
            'home_title' => (array) ($saved['home_title'] ?? []), 'home_description' => (array) ($saved['home_description'] ?? []),
            'title_suffix' => (array) ($saved['title_suffix'] ?? []), 'social' => []];
        foreach (self::SCALARS as $k) {
            $out[$k] = (string) ($saved[$k] ?? '');
        }
        foreach (self::SOCIALS as $s) {
            $out['social'][$s] = (string) (($saved['social'] ?? [])[$s] ?? '');
        }

        $out['app_links'] = ['ios' => (string) ($tenant->settings['app_links']['ios'] ?? ''), 'android' => (string) ($tenant->settings['app_links']['android'] ?? '')];

        return $out;
    }

    /** The current tenant's settings, memoised for the request. */
    public function current(): array
    {
        return $this->current ??= $this->get(current_tenant());
    }

    public function update(Tenant $tenant, array $data): array
    {
        $clean = ['indexable' => (bool) ($data['indexable'] ?? false), 'track_staff' => (bool) ($data['track_staff'] ?? false)];
        foreach (['home_title', 'home_description', 'title_suffix'] as $k) {
            $clean[$k] = array_filter(array_map(fn ($v) => trim((string) $v), (array) ($data[$k] ?? [])), fn ($v) => $v !== '');
        }
        foreach (self::SCALARS as $k) {
            $clean[$k] = trim((string) ($data[$k] ?? ''));
        }
        $clean['social'] = array_filter(array_map(fn ($v) => trim((string) $v), array_intersect_key((array) ($data['social'] ?? []), array_flip(self::SOCIALS))), fn ($v) => $v !== '');

        $apps = array_filter(array_map(fn ($v) => trim((string) $v), array_intersect_key((array) ($data['app_links'] ?? []), array_flip(['ios', 'android']))), fn ($v) => $v !== '');

        $this->tenants->update($tenant, ['settings' => array_merge($tenant->settings ?? [], ['seo' => $clean, 'app_links' => $apps])]);
        $this->current = null;

        return $clean;
    }
}
