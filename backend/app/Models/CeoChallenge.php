<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CeoChallenge extends Model
{

    protected $table = 'ceo_challenges';

    protected $fillable = ['week', 'rule', 'question', 'hypothesis', 'experiment', 'metric', 'duration_days', 'review_on', 'evidence', 'status', 'result_note', 'experiment_id'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'review_on' => 'date'];
    }
}
