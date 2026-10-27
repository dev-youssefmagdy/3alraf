<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ContentAsset extends Model
{
    use BelongsToTenant;

    protected $table = 'content_assets';

    protected $fillable = ['product_id', 'ai_generation_id', 'type', 'variant', 'locale', 'title', 'body', 'status', 'version', 'utm_content', 'tracked_url'];

    protected function casts(): array
    {
        return [];
    }
}
