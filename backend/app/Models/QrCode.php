<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Stored QR PNG (media disk) for a store product / variant, optionally one affiliate's tracked link. */
class QrCode extends Model
{

    protected $fillable = ['owner_type', 'owner_id', 'affiliate_id', 'affiliate_key', 'url', 'url_hash', 'path'];

    protected static function booted(): void
    {
        static::saving(fn (self $q) => $q->affiliate_key = (int) $q->affiliate_id);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function getFileUrlAttribute(): string
    {
        return media_url($this->path);
    }
}
