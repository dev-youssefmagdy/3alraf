<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmbassadorIdea extends Model
{

    protected $table = 'ambassador_ideas';

    protected $fillable = ['title', 'body', 'channel'];

    protected function casts(): array
    {
        return [];
    }
}
