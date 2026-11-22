<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualModelReferenceImage extends Model
{
    protected $fillable = ['virtual_model_id', 'kind', 'source', 'disk', 'path', 'mime', 'size_bytes', 'width', 'height', 'checksum', 'is_primary', 'sort_order', 'created_by'];

    protected $hidden = ['disk', 'path', 'checksum'];

    protected function casts(): array
    {
        return ['is_primary' => 'bool', 'size_bytes' => 'int'];
    }

    public function virtualModel(): BelongsTo
    {
        return $this->belongsTo(VirtualModel::class);
    }
}
