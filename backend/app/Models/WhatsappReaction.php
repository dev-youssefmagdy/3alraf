<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tenant-less on purpose: always reached through its message (which is owner-scoped). */
class WhatsappReaction extends Model
{
    use HasFactory;

    protected $fillable = ['message_id', 'reactor_jid', 'emoji'];
}
