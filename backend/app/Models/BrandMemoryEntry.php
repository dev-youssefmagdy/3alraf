<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BrandMemoryEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'brand_memory_entries';

    protected $fillable = ['kind', 'title', 'body', 'is_active', 'version', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }
}
