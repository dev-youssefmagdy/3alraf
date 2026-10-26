<?php

namespace App\Jobs;

use App\Exceptions\AiException;
use App\Models\Tenant;
use App\Services\Ai\TrendService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/** Market trends off the request (web search can take a while). One run per tenant at a time; no retry (each try costs tokens). */
class RunMarketTrendsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    public function __construct(public int $tenantId) {}

    public function uniqueId(): string
    {
        return 'ai-trends-'.$this->tenantId;
    }

    public function handle(TrendService $trends): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        try {
            TenantContext::run($tenant, fn () => $trends->generateMarket());
        } catch (AiException $e) {
            Cache::put("ai-trends:error:{$this->tenantId}", $e->getMessage(), now()->addHour());
        } catch (\Throwable $e) {
            report($e);
            Cache::put("ai-trends:error:{$this->tenantId}", __('ai.errors.provider'), now()->addHour());
        } finally {
            Cache::forget("ai-trends:running:{$this->tenantId}");
        }
    }
}
