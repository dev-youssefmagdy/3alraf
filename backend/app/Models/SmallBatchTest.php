<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmallBatchTest extends Model
{

    protected $table = 'small_batch_tests';

    protected $fillable = ['code', 'name', 'product_id', 'mode', 'description', 'survey_question', 'preorder', 'preorder_terms', 'limited_stock_qty', 'target_signups', 'starts_on', 'ends_on', 'status', 'decision', 'decision_note', 'opened_at'];

    protected function casts(): array
    {
        return ['preorder' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date', 'opened_at' => 'datetime'];
    }
}
