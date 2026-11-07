<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTrendSnapshot extends Model
{

    protected $fillable = ['kind', 'country', 'period', 'items', 'generated_at'];

    protected function casts(): array
    {
        return ['items' => 'array', 'generated_at' => 'datetime'];
    }
}
