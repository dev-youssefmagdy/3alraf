<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class StaticPage extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'content'];

    protected $fillable = ['slug', 'title', 'content', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }
}
