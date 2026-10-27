<?php

namespace App\Console\Commands;

use App\Services\Growth\InteractionService;
use Illuminate\Console\Command;

class GrowthEvalClassifier extends Command
{
    protected $signature = 'growth:eval-classifier {--file= : labelled JSON (default tests/Fixtures/growth/interactions_labelled.json)} {--confusion : print the category confusion matrix}';

    protected $description = 'Measure the keyword classifier against the hand-labelled set (accuracy per field vs the targets in config/growth.php). No AI call, no database.';

    public function handle(InteractionService $svc): int
    {
        $file = $this->option('file') ?: base_path('tests/Fixtures/growth/interactions_labelled.json');
        $set = json_decode((string) @file_get_contents($file), true);
        if (! is_array($set) || ! $set) {
            $this->error('Labelled set not found or invalid: '.$file);

            return self::FAILURE;
        }
        $res = $svc->evaluate($set);
        $target = (array) config('growth.classifier.target_accuracy', []);
        $this->info("Messages: {$res['n']}; low-confidence (would call the AI fallback): {$res['low_confidence']}");
        $below = false;
        foreach ($res['accuracy'] as $f => $acc) {
            $t = (float) ($target[$f] ?? 0);
            $below = $below || $acc < $t;
            $this->line(sprintf('%-10s accuracy %5.1f%%  (target %4.0f%%) %s', $f, $acc * 100, $t * 100, $acc >= $t ? 'OK' : 'BELOW TARGET'));
        }
        if ($this->option('confusion')) {
            $this->table(['expected -> predicted', 'count'], collect($res['confusion'])->map(fn ($c, $k) => [$k, $c])->values()->all());
        }

        return $below ? self::FAILURE : self::SUCCESS;
    }
}
