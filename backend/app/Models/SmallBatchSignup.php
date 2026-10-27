<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SmallBatchSignup extends Model
{
    use BelongsToTenant;

    protected $table = 'small_batch_signups';

    protected $fillable = ['small_batch_test_id', 'contact_hash', 'contact_masked', 'answer', 'quantity', 'preorder_accepted', 'consent'];

    protected function casts(): array
    {
        return ['preorder_accepted' => 'boolean', 'consent' => 'boolean'];
    }
}
