<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ContentCalendarItem extends Model
{
    use BelongsToTenant;

    protected $table = 'content_calendar';

    protected $fillable = ['content_asset_id', 'channel', 'scheduled_for', 'status', 'note', 'created_by', 'published_at'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'published_at' => 'datetime'];
    }

    public function asset(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ContentAsset::class, 'content_asset_id');
    }
}
