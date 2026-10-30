<?php

namespace App\Jobs;

use App\Events\Whatsapp\ChatMessageUpdated;
use App\Models\WhatsappMedia;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\Whatsapp\Chat\MediaPaths;
use App\Services\Whatsapp\NodeClient;
use App\Services\Whatsapp\WhatsappUnavailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/** Pull one inbound media file from Node (GET then DELETE), sniff its real MIME, store it on the private disk. Token loss/expiry => status expired. */
class PullWhatsappMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public ?int $tenantId, public int $mediaId, public string $token) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(NodeClient $node): void
    {
        $media = WhatsappMedia::ownedBy($this->tenantId)->find($this->mediaId);
        if (! $media || $media->status === 'ready') {
            return;
        }
        $sid = WhatsappSession::idFor($this->tenantId);
        $pulled = $node->pullMedia($sid, $this->token); // WhatsappUnavailable -> retry
        if ($pulled === null) {
            $this->finish($media, 'expired');

            return;
        }
        $tmp = $pulled['path'];
        try {
            $sha = hash_file('sha256', $tmp);
            if ($pulled['size'] === 0 || $pulled['size'] > config('whatsapp_chat.max_media_bytes') || ($pulled['sha256'] && ! hash_equals(strtolower($pulled['sha256']), $sha))) {
                $node->deleteMedia($sid, $this->token);
                $this->finish($media, 'failed');

                return;
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
            // `.bin` for anything unknown: the stored name never depends on remote input
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'application/pdf' => 'pdf'][$mime] ?? 'bin';
            $path = MediaPaths::dir($media->owner_key).'/'.bin2hex(random_bytes(16)).'.'.$ext;
            $h = fopen($tmp, 'rb');
            try {
                Storage::disk($media->disk)->put($path, $h);
            } finally {
                is_resource($h) && fclose($h);
            }
            $media->update(['path' => $path, 'mime' => $mime, 'size' => $pulled['size'], 'sha256' => $sha, 'status' => 'ready']);
        } finally {
            @unlink($tmp);
        }
        $node->deleteMedia($sid, $this->token);
        $this->broadcast($media);
    }

    public function failed(\Throwable $e): void
    {
        if ($media = WhatsappMedia::ownedBy($this->tenantId)->find($this->mediaId)) {
            $this->finish($media, $e instanceof WhatsappUnavailable ? 'expired' : 'failed');
        }
    }

    private function finish(WhatsappMedia $media, string $status): void
    {
        $media->update(['status' => $status]);
        $this->broadcast($media);
    }

    private function broadcast(WhatsappMedia $media): void
    {
        if ($msg = WhatsappMessage::ownedBy($this->tenantId)->where('media_id', $media->id)->first()) {
            ChatMessageUpdated::dispatch($this->tenantId, $msg->load('media')->toChat());
        }
    }
}
