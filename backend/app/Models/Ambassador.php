<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Ambassador extends Model
{
    use BelongsToTenant;

    protected $table = 'ambassadors';

    protected $fillable = ['affiliate_id', 'status', 'disclosure', 'reward_per_order', 'reward_rule', 'terms_accepted_at'];

    protected function casts(): array
    {
        return ['reward_per_order' => 'decimal:2', 'terms_accepted_at' => 'datetime'];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
}
