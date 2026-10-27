<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GiftFinderSession extends Model
{
    use BelongsToTenant;

    protected $table = 'gift_finder_sessions';

    protected $fillable = ['token', 'occasion', 'recipient', 'budget', 'category_id', 'restrictions', 'result_ids', 'results_count', 'cart_at'];

    protected function casts(): array
    {
        return ['result_ids' => 'array', 'budget' => 'decimal:2', 'cart_at' => 'datetime'];
    }
}
