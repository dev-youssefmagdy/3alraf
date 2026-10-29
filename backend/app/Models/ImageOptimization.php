<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageOptimization extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime'];
    }
}
