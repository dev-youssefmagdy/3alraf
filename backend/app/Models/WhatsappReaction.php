<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Always reached through its message. */
class WhatsappReaction extends Model
{
    use HasFactory;

    protected $fillable = ['message_id', 'reactor_jid', 'emoji'];
}
