<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    use BelongsToTenant, HasTranslations;

    protected array $translatable = ['title', 'excerpt', 'content', 'meta_description'];

    protected $fillable = ['slug', 'title', 'excerpt', 'content', 'meta_description', 'is_active', 'published_at'];

    /** Active and already published. */
    public function scopePublished($q)
    {
        return $q->where('is_active', true)->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    protected function casts(): array
    {
        return ['is_active' => 'bool', 'published_at' => 'datetime'];
    }
}
