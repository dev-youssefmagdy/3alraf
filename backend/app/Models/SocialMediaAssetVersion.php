<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One uploaded file of a library item (version history, P6). Files stay on the private disk. */
class SocialMediaAssetVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['asset_id', 'version', 'disk', 'path', 'thumb_path', 'original_name', 'mime', 'size_bytes', 'width', 'height', 'duration_seconds',
        'checksum', 'note', 'uploaded_by'];

    protected $hidden = ['disk', 'path', 'thumb_path', 'checksum'];

    protected function casts(): array
    {
        return ['version' => 'int', 'size_bytes' => 'int', 'width' => 'int', 'height' => 'int', 'duration_seconds' => 'int'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(SocialMediaAsset::class, 'asset_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }
}
