<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Gift Finder search: the answers, the ranked suggestions (products in result_ids, bundles in bundle_ids, display order in params.order) and what went to the cart. */
class GiftFinderSession extends Model
{
    protected $table = 'gift_finder_sessions';

    protected $fillable = ['token', 'occasion', 'recipient', 'budget', 'category_id', 'restrictions', 'params', 'result_ids', 'bundle_ids', 'results_count', 'cart_ids', 'cart_bundle_ids', 'cart_at'];

    protected function casts(): array
    {
        return ['result_ids' => 'array', 'bundle_ids' => 'array', 'cart_ids' => 'array', 'cart_bundle_ids' => 'array', 'params' => 'array', 'budget' => 'decimal:2', 'cart_at' => 'datetime'];
    }
}
