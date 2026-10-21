<?php

namespace App\Models;

use App\Enums\AttributeType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasTranslations;

    protected array $translatable = ['name'];

    protected $fillable = ['name', 'code', 'type', 'sort_order'];

    protected function casts(): array
    {
        return ['type' => AttributeType::class];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
