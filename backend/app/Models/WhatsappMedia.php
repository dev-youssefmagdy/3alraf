<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappMedia extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_media';

    protected $fillable = ['disk', 'path', 'mime', 'size', 'original_name', 'sha256', 'width', 'height', 'duration', 'thumbnail_path', 'status'];
}
