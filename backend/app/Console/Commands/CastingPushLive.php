<?php

namespace App\Console\Commands;

use App\Models\ModelGenerationHistory;
use App\Services\Casting\LivePushService;
use Illuminate\Console\Command;

class CastingPushLive extends Command
{
    protected $signature = 'casting:push-live {generation : model_generation_history id of a finished generation for a live product}';

    protected $description = 'Send one finished casting image / video to 3alraf.com (private library, pending review)';

    public function handle(LivePushService $push): int
    {
        try {
            $r = $push->push(ModelGenerationHistory::findOrFail((int) $this->argument('generation')));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info(($r['duplicate'] ? 'Already on live' : 'Sent to live').": library item #{$r['asset_id']} (pending review) ".($r['review_url'] ?? ''));

        return self::SUCCESS;
    }
}
