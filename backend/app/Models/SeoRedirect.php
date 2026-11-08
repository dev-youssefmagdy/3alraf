<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 301 from an old storefront path to the current one (slug changes). Localized paths are stored without the `/{locale}` prefix. */
class SeoRedirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'localized', 'entity_type', 'entity_id', 'status_code', 'hits', 'last_hit_at', 'suggestion_id', 'created_by'];

    protected function casts(): array
    {
        return ['localized' => 'bool', 'hits' => 'int', 'status_code' => 'int', 'last_hit_at' => 'datetime'];
    }
}
