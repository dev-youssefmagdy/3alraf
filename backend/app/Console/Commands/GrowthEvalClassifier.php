<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Growth\InteractionService;
use Illuminate\Console\Command;

class GrowthEvalClassifier extends Command
{
    protected $signature = 'growth:eval-classifier {--file= : labelled JSON (default tests/Fixtures/growth/interactions_labelled.json)} {--set= : labelled (default) or blind} {--real : accuracy on human-reviewed rows of --tenant} {--tenant= : tenant slug for --real} {--confusion : print the confusion matrix}';

    protected $description = 'Measure the keyword classifier against the hand-labelled set (accuracy per field vs the targets in config/growth.php). No AI call, no database.';

    public function handle(InteractionService $svc): int
    {
        if ($this->option('real')) {
            return $this->real($svc);
        }
        if ($this->option('set') === 'blind' && ! $this->option('file')) {
            return $this->blind($svc);
        }
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

    private function blind(InteractionService $svc): int
    {
        $set = json_decode((string) @file_get_contents(base_path('tests/Fixtures/growth/interactions_blind.json')), true);
        if (! is_array($set) || ! $set) {
            $this->error('Blind set missing');

            return self::FAILURE;
        }
        $res = $svc->evaluateBlind($set);
        $this->info("Blind set (independent author): {$res['n']} messages. Category is not comparable (different taxonomy); intent is mapped coarsely.");
        $target = (array) config('growth.classifier.target_accuracy', []);
        foreach (['intent', 'sentiment'] as $f) {
            $this->line(sprintf('%-10s accuracy %5.1f%%  (target %4.0f%%)', $f, $res['accuracy'][$f] * 100, ($target[$f] ?? 0) * 100));
        }
        if ($this->option('confusion')) {
            $this->table(['sentiment expected -> predicted', 'count'], collect($res['confusion'])->map(fn ($c, $k) => [$k, $c])->values()->all());
        }

        return self::SUCCESS;
    }

    private function real(InteractionService $svc): int
    {
        $t = $this->option('tenant') ? Tenant::where('slug', $this->option('tenant'))->first() : null;
        if (! $t) {
            $this->error('--tenant=<slug> is required with --real');

            return self::FAILURE;
        }
        $prev = app()->bound('currentTenant') ? app('currentTenant') : null;
        app()->instance('currentTenant', $t);
        $res = $svc->evaluateReal();
        $svc->storeRealEval($res);
        $prev ? app()->instance('currentTenant', $prev) : app()->forgetInstance('currentTenant');
        $this->info("Reviewed rows: {$res['n']} (minimum {$res['min']}). Verdict: {$res['verdict']}");
        if ($res['verdict'] === 'insufficient_data') {
            $this->warn('Insufficient data: no accuracy verdict is given below the minimum sample.');

            return self::FAILURE;
        }
        foreach ($res['accuracy'] as $f => $a) {
            $this->line(sprintf('%-10s accuracy %5.1f%%', $f, $a * 100));
        }

        return $res['verdict'] === 'meets_target' ? self::SUCCESS : self::FAILURE;
    }
}
