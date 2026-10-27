<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ApprovedProductClaim extends Model
{
    use BelongsToTenant;

    protected $table = 'approved_product_claims';

    protected $fillable = ['product_id', 'type', 'claim', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }
}
