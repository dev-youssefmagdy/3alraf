<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminOtp extends Model
{
    protected $fillable = ['guard', 'admin_id', 'tenant_id', 'code_hash', 'attempts', 'resend_count', 'expires_at', 'last_sent_at', 'consumed_at', 'ip'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_sent_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
