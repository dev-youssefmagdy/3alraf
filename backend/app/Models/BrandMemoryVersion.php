<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BrandMemoryVersion extends Model
{
    use BelongsToTenant;

    protected $table = 'brand_memory_versions';

    public $timestamps = false;

    protected $fillable = ['entry_id', 'version', 'title', 'body', 'is_active', 'changed_by', 'created_at'];

    protected function casts(): array
    {
        return ['is_active' => 'bool', 'created_at' => 'datetime'];
    }
}
