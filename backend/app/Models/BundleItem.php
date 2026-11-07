<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleItem extends Model
{

    protected $fillable = ['bundle_id', 'product_id', 'product_variant_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'int'];
    }

    public function bundle()
    {
        return $this->belongsTo(Bundle::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
