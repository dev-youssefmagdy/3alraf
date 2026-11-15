<?php

namespace App\Support;

use App\Models\Setting;

/**
 * System settings: the former custom .env keys (and APP_*), now editable in Admin → System settings and stored in the `settings` table
 * (key `sys_<name>`, encrypted at rest). apply() copies stored values over the config at boot; anything not stored keeps its config/env default.
 *
 * Not here on purpose (needed before the database is reachable, so they stay in .env): APP_KEY (encrypts these rows), APP_ENV, APP_MAINTENANCE_*,
 * TRUSTED_PROXIES, VITE_* (compiled into the JS bundle). APP_NAME / APP_LOCALE are the existing General settings `name` / `default_locale`.
 */
final class SystemSettings
{
    public const PREFIX = 'sys_';

    /**
     * group => name => [type, config paths, env var (import source)]. Types: string|int|float|bool|csv|secret.
     *
     * @return array<string,array<string,array{0:string,1:list<string>,2:string}>>
     */
    public static function schema(): array
    {
        return [
            'app' => [
                'app_debug' => ['bool', ['app.debug'], 'APP_DEBUG'],
                'app_url' => ['string', ['app.url'], 'APP_URL'],
                'app_fallback_locale' => ['string', ['app.fallback_locale'], 'APP_FALLBACK_LOCALE'],
                'app_faker_locale' => ['string', ['app.faker_locale'], 'APP_FAKER_LOCALE'],
                'mobile_legacy_store_slug' => ['string', ['mobile.legacy_store_slug'], 'MOBILE_LEGACY_STORE_SLUG'],
                'soft_delete_retention_days' => ['int', ['retention.soft_delete_days'], 'SOFT_DELETE_RETENTION_DAYS'],
                'integration_ips' => ['csv', ['integration.ip_allowlist'], 'INTEGRATION_IPS'],
            ],
            'media' => [
                'media_disk' => ['string', ['media.disk'], 'MEDIA_DISK'],
                'media_private_disk' => ['string', ['media.private_disk'], 'MEDIA_PRIVATE_DISK'],
                'media_url' => ['string', ['media.url', 'filesystems.disks.media.url'], 'MEDIA_URL'],
                'media_signed_ttl' => ['int', ['media.signed_ttl_minutes'], 'MEDIA_SIGNED_TTL'],
                'product_video_max_mb' => ['int', ['media.product_video.max_mb'], 'PRODUCT_VIDEO_MAX_MB'],
                'product_video_max_per_product' => ['int', ['media.product_video.max_per_product'], 'PRODUCT_VIDEO_MAX_PER_PRODUCT'],
                'social_media_image_max_kb' => ['int', ['marketing.media.image_max_kb'], 'SOCIAL_MEDIA_IMAGE_MAX_KB'],
                'social_media_video_max_kb' => ['int', ['marketing.media.video_max_kb'], 'SOCIAL_MEDIA_VIDEO_MAX_KB'],
                'image_webp_fallback' => ['bool', ['images.webp_fallback'], 'IMAGE_WEBP_FALLBACK'],
            ],
            'google' => [
                'google_client_id' => ['string', ['services.google.client_id'], 'GOOGLE_CLIENT_ID'],
                'google_client_secret' => ['secret', ['services.google.client_secret'], 'GOOGLE_CLIENT_SECRET'],
                'google_mobile_client_ids' => ['csv', ['services.google.mobile_client_ids'], 'GOOGLE_MOBILE_CLIENT_IDS'],
            ],
            'whatsapp' => [
                'whatsapp_service_url' => ['string', ['services.whatsapp.url'], 'WHATSAPP_SERVICE_URL'],
                'whatsapp_service_token' => ['secret', ['services.whatsapp.token'], 'WHATSAPP_SERVICE_TOKEN'],
                'whatsapp_callback_secret' => ['secret', ['services.whatsapp.callback_secret'], 'WHATSAPP_CALLBACK_SECRET'],
            ],
            'reverb' => [
                'reverb_app_id' => ['string', ['broadcasting.connections.reverb.app_id', 'reverb.apps.apps.0.app_id'], 'REVERB_APP_ID'],
                'reverb_app_key' => ['string', ['broadcasting.connections.reverb.key', 'reverb.apps.apps.0.key'], 'REVERB_APP_KEY'],
                'reverb_app_secret' => ['secret', ['broadcasting.connections.reverb.secret', 'reverb.apps.apps.0.secret'], 'REVERB_APP_SECRET'],
                'reverb_host' => ['string', ['broadcasting.connections.reverb.options.host', 'reverb.apps.apps.0.options.host', 'reverb.servers.reverb.hostname'], 'REVERB_HOST'],
                'reverb_port' => ['int', ['broadcasting.connections.reverb.options.port', 'reverb.apps.apps.0.options.port'], 'REVERB_PORT'],
                'reverb_scheme' => ['string', ['broadcasting.connections.reverb.options.scheme', 'reverb.apps.apps.0.options.scheme'], 'REVERB_SCHEME'],
                'reverb_server_host' => ['string', ['reverb.servers.reverb.host'], 'REVERB_SERVER_HOST'],
                'reverb_server_port' => ['int', ['reverb.servers.reverb.port'], 'REVERB_SERVER_PORT'],
            ],
            'meta' => [
                'meta_graph_version' => ['string', ['meta.graph_version'], 'META_GRAPH_VERSION'],
                'meta_graph_url' => ['string', ['meta.graph_url'], 'META_GRAPH_URL'],
                'meta_access_token' => ['secret', ['meta.access_token'], 'META_ACCESS_TOKEN'],
                'meta_app_id' => ['string', ['meta.app_id'], 'META_APP_ID'],
                'meta_app_secret' => ['secret', ['meta.app_secret'], 'META_APP_SECRET'],
                'meta_ad_account_id' => ['string', ['meta.ad_account_id'], 'META_AD_ACCOUNT_ID'],
                'meta_page_id' => ['string', ['meta.page_id'], 'META_PAGE_ID'],
                'meta_instagram_user_id' => ['string', ['meta.instagram_user_id'], 'META_INSTAGRAM_USER_ID'],
                'meta_currency' => ['string', ['meta.default_currency'], 'META_CURRENCY'],
                'meta_cache_minutes' => ['int', ['meta.cache_minutes'], 'META_CACHE_MINUTES'],
                'meta_approval_valid_hours' => ['int', ['meta.approval_valid_hours'], 'META_APPROVAL_VALID_HOURS'],
                'meta_min_daily_budget' => ['float', ['meta.min_daily_budget'], 'META_MIN_DAILY_BUDGET'],
                'meta_max_daily_budget' => ['float', ['meta.max_daily_budget'], 'META_MAX_DAILY_BUDGET'],
                'meta_max_lifetime_budget' => ['float', ['meta.max_lifetime_budget'], 'META_MAX_LIFETIME_BUDGET'],
            ],
        ];
    }

    /** @return array<string,array{0:string,1:list<string>,2:string,3:string}> name => [type, paths, env, group] */
    public static function flat(): array
    {
        $out = [];
        foreach (self::schema() as $group => $items) {
            foreach ($items as $name => $def) {
                $out[$name] = [...$def, $group];
            }
        }

        return $out;
    }

    /** Copy stored values over the config (called once per process from AppServiceProvider::boot). */
    public static function apply(): void
    {
        $stored = [];
        foreach (self::flat() as $name => [$type, $paths]) {
            $v = Setting::get(self::PREFIX.$name);
            if ($v === null) {
                continue;
            }
            $stored[$name] = $v;
            $value = self::cast($type, $v);
            foreach ($paths as $p) {
                config([$p => $value]);
            }
        }
        // Derived defaults: public storage / media URLs follow app_url unless media_url is set explicitly
        if (isset($stored['app_url'])) {
            $base = rtrim((string) $stored['app_url'], '/');
            config(['app.host' => strtolower((string) (parse_url($base, PHP_URL_HOST) ?: 'localhost')), 'filesystems.disks.public.url' => $base.'/storage']);
            if (! isset($stored['media_url'])) {
                config(['media.url' => $base.'/media', 'filesystems.disks.media.url' => $base.'/media']);
            }
        }
        if (isset($stored['reverb_scheme'])) {
            $tls = $stored['reverb_scheme'] === 'https';
            config(['broadcasting.connections.reverb.options.useTLS' => $tls, 'reverb.apps.apps.0.options.useTLS' => $tls]);
        }
        if (isset($stored['media_url'])) {
            config(['media.url' => rtrim((string) $stored['media_url'], '/')]);
        }
        // General settings: shop name (APP_NAME) and default locale (APP_LOCALE)
        if ($n = Setting::get('name')) {
            config(['app.name' => $n]);
        }
        if (($l = Setting::get('default_locale')) && is_string($l)) {
            config(['app.locale' => $l]);
        }
    }

    /** Effective value (stored, else config default) of every field, for the form. Secrets are never returned. */
    public static function current(): array
    {
        $out = [];
        foreach (self::flat() as $name => [$type, $paths]) {
            if ($type === 'secret') {
                $out[$name] = null;

                continue;
            }
            $v = config($paths[0]);
            $out[$name] = $type === 'csv' ? implode(',', (array) $v) : ($type === 'bool' ? (bool) $v : $v);
        }

        return $out;
    }

    public static function isSecretSet(string $name): bool
    {
        $def = self::flat()[$name] ?? null;

        return $def && (string) config($def[1][0]) !== '';
    }

    public static function cast(string $type, mixed $v): mixed
    {
        return match ($type) {
            'int' => (int) $v,
            'float' => (float) $v,
            'bool' => (bool) $v,
            'csv' => is_array($v) ? array_values($v) : array_values(array_filter(array_map('trim', explode(',', (string) $v)), 'strlen')),
            default => $v === null ? null : (string) $v,
        };
    }
}
