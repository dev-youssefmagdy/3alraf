<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class CustomPage extends Model
{
    use BelongsToTenant, HasTranslations;

    protected array $translatable = ['title'];

    protected $fillable = ['slug', 'title', 'filters', 'is_active'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'is_active' => 'bool'];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'custom_page_products')->withPivot('sort_order')->orderByPivot('sort_order');
    }
}
