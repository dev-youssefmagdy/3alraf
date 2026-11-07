<?php

namespace App\Jobs;

use App\Exceptions\AiException;
use App\Services\Ai\TrendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/** Market trends off the request (web search can take a while). One run at a time; no retry (each try costs tokens). */
class RunMarketTrendsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return 'ai-trends';
    }

    public function handle(TrendService $trends): void
    {
        try {
            $trends->generateMarket();
        } catch (AiException $e) {
            Cache::put(TrendService::ERROR_KEY, $e->getMessage(), now()->addHour());
        } catch (\Throwable $e) {
            report($e);
            Cache::put(TrendService::ERROR_KEY, __('ai.errors.provider'), now()->addHour());
        } finally {
            Cache::forget(TrendService::RUNNING_KEY);
        }
    }
}
