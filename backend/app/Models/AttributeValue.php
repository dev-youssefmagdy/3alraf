<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeValue extends Model
{
    use HasTranslations, SoftDeletes;

    protected array $translatable = ['value'];

    protected $fillable = ['attribute_id', 'value', 'color_hex', 'sort_order'];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class)->withTrashed(); // history rows may point at a deleted attribute
    }
}
