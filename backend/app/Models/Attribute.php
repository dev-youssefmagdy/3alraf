<?php

namespace App\Models;

use App\Enums\AttributeType;
use App\Models\Concerns\FreesUniqueOnDelete;
use App\Models\Concerns\HasDraftState;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Model
{
    use FreesUniqueOnDelete, HasDraftState, HasTranslations, SoftDeletes;

    /** Soft delete tombstones the code ("size#del12") so a new attribute can reuse it. */
    protected array $freedOnDelete = ['code'];

    protected array $translatable = ['name'];

    protected $fillable = ['name', 'code', 'type', 'sort_order'];

    protected function casts(): array
    {
        return ['type' => AttributeType::class];
    }

    /** Hide the soft-delete tombstone suffix (see FreesUniqueOnDelete) from every display. */
    public function getCodeAttribute($v): ?string
    {
        return $v === null ? null : preg_replace('/#del\d+$/', '', $v);
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
