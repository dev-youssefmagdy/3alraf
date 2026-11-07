<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmallBatchSignup extends Model
{

    protected $table = 'small_batch_signups';

    protected $fillable = ['small_batch_test_id', 'contact_hash', 'contact_masked', 'answer', 'quantity', 'preorder_accepted', 'consent'];

    protected function casts(): array
    {
        return ['preorder_accepted' => 'boolean', 'consent' => 'boolean'];
    }
}
