<?php

namespace App\Console\Commands;

use App\Services\Llm\FreeLlmClient;
use App\Services\Llm\LlmUnavailableException;
use App\Services\ProductAi\WebSearchService;
use Illuminate\Console\Command;

/**
 * Health of the Product AI tools (docs/FREELLMAPI_SETUP.md): config, gateway reachable, key valid, every configured model (ready / cooling /
 * failed, with a tiny probe call), a vision model, ddgs text search and FreeLLMAPI's own search engines. Exit 1 when something required fails.
 */
class LlmCheckCommand extends Command
{
    protected $signature = 'llm:check {--no-probe : Do not send test prompts; show the cached model states only} {--no-search : Skip the ddgs web search check}';

    protected $description = 'Check the FreeLLMAPI gateway, its models and the web search used by Product AI tools';

    private bool $failed = false;

    public function handle(FreeLlmClient $llm, WebSearchService $search): int
    {
        $base = (string) config('product_ai.base_url');
        $this->line('FreeLLMAPI: '.($base !== '' ? $base : '(FREELLM_BASE_URL empty)'));

        if (! $llm->configured()) {
            $this->report(false, 'configured', 'set FREELLM_BASE_URL and FREELLM_API_KEY in backend/.env, then php artisan config:cache');

            return self::FAILURE;
        }
        $this->report(true, 'configured');

        try {
            $models = $llm->availableModels();
            $this->report(true, 'reachable + key valid', count($models).' models available');
        } catch (LlmUnavailableException $e) {
            $this->report(false, $e->getMessage() === 'key_rejected' ? 'key valid' : 'reachable',
                match ($e->getMessage()) {
                    'key_rejected' => 'the gateway refused FREELLM_API_KEY (copy the unified key from the dashboard Keys page)',
                    'unreachable' => "nothing answers at $base (docker compose ps in /opt/freellmapi)",
                    default => $e->getMessage(),
                });

            return self::FAILURE;
        }

        foreach (['text', 'vision'] as $kind) {
            $ok = 0;
            foreach ($llm->modelStates($kind) as $state) {
                if (! $this->option('no-probe') && $state['state'] !== 'cooling') {
                    $probe = $llm->probe($state['model'], $kind);
                    $state = collect($llm->modelStates($kind))->firstWhere('model', $state['model']) ?? $state; // the probe updated the cache
                    if ($probe['ok']) {
                        $state = ['state' => 'ready', 'reason' => $probe['routed_via'] ? 'via '.$probe['routed_via'] : null] + $state;
                    }
                }
                $ok += $state['state'] === 'ready' ? 1 : 0;
                $detail = match ($state['state']) {
                    'cooling' => 'cooling down '.($state['seconds'] ?? '?').'s ('.$state['reason'].')',
                    'failed' => 'failed: '.($state['reason'] ?? '?'),
                    default => 'ready'.($state['reason'] ? ' ('.$state['reason'].')' : ''),
                };
                $this->line(sprintf('  %-7s %-28s %s', $kind, $state['model'], $detail));
            }
            $this->report($ok > 0, $kind === 'vision' ? 'vision model available' : 'text model available',
                $ok > 0 ? "$ok ready" : ($kind === 'vision' ? 'enable a vision model in the dashboard (e.g. Gemini 2.5 Flash), or set FREELLM_VISION_MODELS' : 'no text model answered'));
        }

        if (! $this->option('no-search')) {
            $r = $search->search('Philips air fryer', 3);
            $count = count(array_filter($r['results'], fn ($x) => $x['engine'] !== 'freellmapi'));
            $this->report($count > 0, 'ddgs text search', $count.' results'.($r['errors'] ? '; errors: '.json_encode($r['errors'], JSON_UNESCAPED_UNICODE) : ''));
        }

        try {
            $engines = $llm->searchEngines();
            $this->line('  FreeLLMAPI /search engines: '.($engines ? implode(', ', $engines) : 'none (optional: add a Tavily / Brave key in the dashboard)'));
        } catch (LlmUnavailableException $e) {
            $this->line('  FreeLLMAPI /search engines: not available ('.$e->getMessage().'), optional');
        }

        $this->newLine();
        $this->failed ? $this->error('llm:check found problems') : $this->info('llm:check OK');

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function report(bool $ok, string $what, ?string $detail = null): void
    {
        $this->failed = $this->failed || ! $ok;
        $this->line(($ok ? '<info>[OK]  </info> ' : '<error>[FAIL]</error> ').$what.($detail ? ' - '.$detail : ''));
    }
}
