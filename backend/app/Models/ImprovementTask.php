<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ImprovementTask extends Model
{
    use BelongsToTenant;

    protected $table = 'improvement_tasks';

    protected $fillable = ['product_id', 'topic', 'rule', 'title', 'owner', 'due_on', 'status', 'evidence', 'before_value', 'after_value'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'due_on' => 'date'];
    }
}
