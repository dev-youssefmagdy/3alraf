<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class FlashSale extends Model
{
    use BelongsToTenant, HasTranslations;

    protected array $translatable = ['name'];

    protected $fillable = ['name', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'bool'];
    }

    public function items()
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    public function isRunning(): bool
    {
        return $this->is_active && now()->between($this->starts_at, $this->ends_at);
    }
}
