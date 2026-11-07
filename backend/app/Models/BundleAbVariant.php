<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleAbVariant extends Model
{

    protected $table = 'bundle_ab_variants';

    protected $fillable = ['bundle_ab_test_id', 'label', 'name', 'description', 'image', 'exposures', 'carts'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array'];
    }
}
