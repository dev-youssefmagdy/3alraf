<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Site-wide key-value settings of the shop: name, country, currency_code, currency_symbol, default_locale, locales,
 * timezone and every former `settings` JSON key (cod_collect_shipping_upfront, growth, seo, notifications, branding...).
 * Values are stored JSON-encoded; `encrypted` rows (secrets) are encrypted at rest with APP_KEY. Read through setting().
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'encrypted'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['encrypted' => 'bool'];
    }

    /** Container key of the per-request cached key => value map. */
    private const CACHE = 'settings.all';

    protected static function booted(): void
    {
        static::saved(fn () => self::flush());
        static::deleted(fn () => self::flush());
    }

    /** Decoded value of this row. */
    public function decoded(): mixed
    {
        $raw = $this->attributes['value'] ?? null;
        if ($raw === null) {
            return null;
        }
        if ($this->encrypted) {
            $raw = Crypt::decryptString($raw);
        }

        return json_decode($raw, true);
    }

    /** @return array<string,mixed> every setting, decoded (cached per request / app instance; empty while installing) */
    public static function map(): array
    {
        if (app()->bound(self::CACHE)) {
            return app(self::CACHE);
        }
        try {
            $rows = static::query()->get();
        } catch (\Throwable) {
            return []; // table not migrated yet / no database connection (install, package discovery)
        }
        $map = $rows->mapWithKeys(fn (self $s) => [$s->key => $s->decoded()])->all();
        app()->instance(self::CACHE, $map);

        return $map;
    }

    /** Exact key first, else dot path into an array value ("growth.enabled" → growth['enabled']). */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::map();
        if (array_key_exists($key, $all)) {
            return $all[$key] ?? $default;
        }

        return data_get($all, $key, $default);
    }

    /** Shop profile keys: stored plain. Every other key belonged to the encrypted settings JSON of the pre-2026-11 shop row (secrets, payment/SEO/growth config). */
    public const PLAIN = ['name', 'country', 'currency_code', 'currency_symbol', 'default_locale', 'locales', 'timezone'];

    /** Write one top-level key. $encrypted null = keep the row's current flag (new rows: plain for PLAIN keys, encrypted otherwise). */
    public static function put(string $key, mixed $value, ?bool $encrypted = null): self
    {
        $row = static::query()->firstOrNew(['key' => $key]);
        $row->encrypted = $encrypted ?? ($row->exists ? (bool) $row->encrypted : ! in_array($key, self::PLAIN, true));
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $row->attributes['value'] = $row->encrypted ? Crypt::encryptString($json) : $json;
        $row->save();

        return $row;
    }

    /** @param array<string,mixed> $values */
    public static function putMany(array $values): void
    {
        foreach ($values as $k => $v) {
            self::put($k, $v);
        }
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::CACHE);
    }
}
