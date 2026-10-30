<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSessionLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['whatsapp_session_id', 'level', 'event', 'message'];
}
