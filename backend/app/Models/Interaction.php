<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    use BelongsToTenant;

    protected $table = 'interactions';

    protected $fillable = ['channel', 'external_id', 'thread_id', 'product_id', 'text_masked', 'text_hash', 'language', 'category', 'intent', 'urgency', 'sentiment', 'status', 'consent', 'opted_out', 'escalation_reason', 'suggested_reply', 'reply_mode', 'window_expires_at', 'received_at', 'first_response_at', 'customer_id', 'reviewed_category', 'reviewed_intent', 'reviewed_sentiment', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['consent' => 'bool', 'opted_out' => 'bool', 'window_expires_at' => 'datetime', 'received_at' => 'datetime', 'reviewed_at' => 'datetime', 'first_response_at' => 'datetime'];
    }
}
