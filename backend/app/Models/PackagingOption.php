<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Paid special-packaging option (docs/CALCULATIONS.md "Special packaging"). `cost` is merchant-only. */
class PackagingOption extends Model
{
    use Audited, BelongsToTenant, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description', 'image', 'price', 'cost', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'cost' => 'decimal:2', 'is_active' => 'bool', 'sort_order' => 'int'];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function imageUrl(): ?string
    {
        return media_url($this->image);
    }
}
