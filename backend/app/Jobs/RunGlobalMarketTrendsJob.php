<?php

namespace App\Jobs;

use App\DTOs\Ai\MarketTrendQuery;
use App\Exceptions\AiException;
use App\Models\AiModel;
use App\Models\Tenant;
use App\Services\Ai\GlobalMarketTrendService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Global market trends off the request. One run per tenant+query at a time; no retry (each try costs tokens). */
class RunGlobalMarketTrendsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    public function __construct(public int $tenantId, public array $countries, public int $period, public string $keyword, public int $count, public ?int $modelId = null) {}

    public function uniqueId(): string
    {
        return 'ai-mkt-'.$this->tenantId.'-'.$this->query()->key();
    }

    private function query(): MarketTrendQuery
    {
        return new MarketTrendQuery($this->countries, $this->period, $this->keyword, $this->count, $this->modelId);
    }

    public function handle(GlobalMarketTrendService $svc): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        $q = $this->query();
        TenantContext::run($tenant, function () use ($svc, $q) {
            try {
                $svc->generate($q, $this->modelId ? AiModel::find($this->modelId) : null);
                $svc->clearRunning($q);
            } catch (AiException $e) {
                $svc->clearRunning($q, $e->getMessage());
            } catch (\Throwable $e) {
                report($e);
                $svc->clearRunning($q, __('ai.errors.provider'));
            }
        });
    }
}
