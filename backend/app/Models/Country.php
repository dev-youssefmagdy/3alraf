<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasTranslations;

    protected array $translatable = ['name'];

    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
