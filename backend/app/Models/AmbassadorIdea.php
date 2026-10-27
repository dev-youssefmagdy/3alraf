<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AmbassadorIdea extends Model
{
    use BelongsToTenant;

    protected $table = 'ambassador_ideas';

    protected $fillable = ['title', 'body', 'channel'];

    protected function casts(): array
    {
        return [];
    }
}
