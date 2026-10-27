<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BundleAbTest extends Model
{
    use BelongsToTenant;

    protected $table = 'bundle_ab_tests';

    protected $fillable = ['bundle_id', 'experiment_id', 'status', 'split_b', 'min_sample', 'winner', 'created_by'];

    protected function casts(): array
    {
        return [];
    }

    public function variants()
    {
        return $this->hasMany(BundleAbVariant::class)->orderBy('label');
    }

    public function bundle()
    {
        return $this->belongsTo(Bundle::class)->withTrashed();
    }
}
