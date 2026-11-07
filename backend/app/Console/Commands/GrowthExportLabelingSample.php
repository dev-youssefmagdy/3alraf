<?php

namespace App\Console\Commands;

use App\Models\Interaction;
use App\Services\Growth\PiiScrubber;
use Illuminate\Console\Command;

class GrowthExportLabelingSample extends Command
{
    protected $signature = 'growth:export-labeling-sample {--n=300 : sample size} {--out= : output JSON path (default storage/app/labeling-sample.json)}';

    protected $description = 'Export a stratified (by predicted category), PII-masked sample of unreviewed interactions for offline human labelling. No contact data, thread or customer ids.';

    public function handle(): int
    {
        $n = max(1, (int) $this->option('n'));
        $groups = Interaction::whereNull('reviewed_at')->where('opted_out', false)->get(['id', 'text_masked', 'category', 'intent', 'sentiment'])->groupBy(fn ($r) => $r->category ?? 'none');
        $per = max(1, (int) ceil($n / max(1, $groups->count())));
        $rows = $groups->flatMap(fn ($g) => $g->shuffle()->take($per))->shuffle()->take($n)->map(fn ($r) => [
            'id' => $r->id, 'text' => PiiScrubber::text((string) $r->text_masked), 'predicted' => ['category' => $r->category, 'intent' => $r->intent, 'sentiment' => $r->sentiment]])->values();
        $out = $this->option('out') ?: storage_path('app/labeling-sample.json');
        file_put_contents($out, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info($rows->count()." rows -> $out (review in the admin interactions page or offline, then record via the review control)");

        return self::SUCCESS;
    }
}
