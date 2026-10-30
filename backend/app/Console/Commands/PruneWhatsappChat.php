<?php

namespace App\Console\Commands;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMedia;
use App\Models\WhatsappMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneWhatsappChat extends Command
{
    protected $signature = 'whatsapp:chat-prune {--days= : override config whatsapp_chat.retention_days} {--dry-run}';

    protected $description = 'Delete WhatsApp chat messages (and their media files) older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('whatsapp_chat.retention_days'));
        if ($days <= 0) {
            $this->info('Retention disabled (0 days).');

            return self::SUCCESS;
        }
        $cutoff = now()->subDays($days);
        $dry = (bool) $this->option('dry-run');
        $messages = $files = $convs = 0;

        // all owners: this is an operator command, so global scopes are bypassed on purpose
        WhatsappMessage::withoutGlobalScopes()->where('wa_timestamp', '<', $cutoff)->orderBy('id')->chunkById(500, function ($chunk) use ($dry, &$messages, &$files) {
            $messages += $chunk->count();
            $media = WhatsappMedia::withoutGlobalScopes()->whereIn('id', $chunk->pluck('media_id')->filter())->get();
            foreach ($media as $m) {
                foreach (array_filter([$m->path, $m->thumbnail_path]) as $path) {
                    $files++;
                    $dry || Storage::disk($m->disk)->delete($path);
                }
            }
            if (! $dry) {
                WhatsappMessage::withoutGlobalScopes()->whereIn('id', $chunk->pluck('id'))->delete();
                WhatsappMedia::withoutGlobalScopes()->whereIn('id', $media->pluck('id'))->delete();
            }
        });

        // conversations with nothing left and no recent activity
        $empty = WhatsappConversation::withoutGlobalScopes()->where(fn ($q) => $q->whereNull('last_message_at')->orWhere('last_message_at', '<', $cutoff))
            ->whereDoesntHave('messages', fn ($q) => $q->withoutGlobalScopes());
        $convs = (clone $empty)->count();
        $dry || $empty->delete();

        $this->info(($dry ? '[dry-run] ' : '')."messages: $messages, files: $files, conversations: $convs");

        return self::SUCCESS;
    }
}
