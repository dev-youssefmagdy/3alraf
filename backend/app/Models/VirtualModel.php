<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Official AI "virtual model" of the casting library. Images are on the PRIVATE disk (never public URLs). */
class VirtualModel extends Model
{
    use SoftDeletes;

    public const DRAFT = 'draft';
    public const APPROVED = 'approved';
    public const ARCHIVED = 'archived';

    protected $fillable = ['name', 'slug', 'gender', 'age_range', 'appearance', 'hairstyle', 'clothing_style', 'personality', 'ad_role', 'identity_prompt',
        'identity_seed', 'identity_mode', 'status', 'source_candidate_id', 'created_by', 'approved_by', 'approved_at', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'approved_at' => 'datetime', 'identity_seed' => 'int'];
    }

    public function references(): HasMany
    {
        return $this->hasMany(VirtualModelReferenceImage::class)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(ModelGenerationHistory::class)->latest('id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ModelUsageHistory::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'created_by');
    }

    public function isUsable(): bool
    {
        return $this->status === self::APPROVED;
    }
}
