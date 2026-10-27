<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BundleAbVariant extends Model
{
    use BelongsToTenant;

    protected $table = 'bundle_ab_variants';

    protected $fillable = ['bundle_ab_test_id', 'label', 'name', 'description', 'image', 'exposures', 'carts'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array'];
    }
}
