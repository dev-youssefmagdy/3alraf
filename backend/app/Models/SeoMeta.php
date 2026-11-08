<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

/** Meta title / description per locale for storefront entities that have no own meta columns (rendered by the storefront layout). */
class SeoMeta extends Model
{
    use HasTranslations;

    protected array $translatable = ['meta_title', 'meta_description'];

    protected $fillable = ['entity_type', 'entity_id', 'meta_title', 'meta_description', 'suggestion_id', 'updated_by'];
}
