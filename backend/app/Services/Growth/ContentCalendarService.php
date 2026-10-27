<?php

namespace App\Services\Growth;

use App\Models\ContentAsset;
use App\Models\ContentCalendarItem;
use Illuminate\Validation\ValidationException;

/** Content calendar: approved assets scheduled per channel. Publishing is MANUAL (a task for a human); nothing is posted automatically. */
class ContentCalendarService
{
    public const CHANNELS = ['instagram', 'facebook', 'tiktok', 'whatsapp', 'email', 'website', 'other'];

    public function __construct(private ActionLogger $log) {}

    public function schedule(int $assetId, string $channel, string $when, ?string $note, ?int $adminId): ContentCalendarItem
    {
        $asset = ContentAsset::findOrFail($assetId); // tenant scoped
        if ($asset->status === 'retired') {
            throw ValidationException::withMessages(['content_asset_id' => __('growth_hub.content.retired')]);
        }
        $i = ContentCalendarItem::create(['content_asset_id' => $asset->id, 'channel' => $channel, 'scheduled_for' => $when, 'status' => 'planned', 'note' => $note, 'created_by' => $adminId]);
        $this->log->log('content.scheduled', 'admin', $adminId, null, $i, 'ok', ['asset_id' => $asset->id, 'channel' => $channel]);

        return $i;
    }

    /** Marks the manual publishing task done (idempotent: already published stays as is). */
    public function markPublished(int $id, ?int $adminId): ContentCalendarItem
    {
        $i = ContentCalendarItem::findOrFail($id);
        if ($i->status === 'planned') {
            $i->update(['status' => 'published', 'published_at' => now()]);
            $i->asset?->update(['status' => 'published']);
            $this->log->log('content.published', 'admin', $adminId, null, $i, 'ok', ['asset_id' => $i->content_asset_id]);
        }

        return $i;
    }

    public function skip(int $id, ?int $adminId): ContentCalendarItem
    {
        $i = ContentCalendarItem::findOrFail($id);
        if ($i->status === 'planned') {
            $i->update(['status' => 'skipped']);
            $this->log->log('content.skipped', 'admin', $adminId, null, $i);
        }

        return $i;
    }
}
