<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    protected $fillable = ['path', 'thumb_path', 'sort_order'];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return media_url($this->path);
    }

    public function getThumbUrlAttribute(): string
    {
        return media_url($this->thumb_path ?: $this->path);
    }
}
