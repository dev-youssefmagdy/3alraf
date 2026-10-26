<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Services\Ai\Support\AiRequest;
use App\Services\Ai\Support\AiResponse;
use Illuminate\Support\Facades\RateLimiter;

/** Single entry for every model call: access -> quota -> throttle -> runner/driver -> usage log. Never leaks vendor errors. */
class AiGateway
{
    public function __construct(private AiManager $manager, private AiRunner $runner, private AiAccess $access, private AiContextFactory $contexts) {}

    /**
     * @param  array<int,Contracts\AiTool>  $tools
     */
    public function chat(AiModel $model, AiRequest $req, string $feature, array $tools = [], ?AiContext $ctx = null, ?array &$trace = null): AiResponse
    {
        $ctx ??= $this->contexts->forTenant();
        $this->authorize($model, $feature, $ctx, 'tokens');
        $driver = $this->manager->for($model);
        $req->model = $model->model_id;
        $req->system = trim($ctx->systemPreamble()."\n".($req->system ?? ''));

        return $this->measure($model, $feature, $ctx, function () use ($driver, $model, $req, $tools, $ctx, &$trace) {
            return $tools ? $this->runner->run($driver, $model, $req, $tools, $ctx, $trace) : $driver->chat($req);
        });
    }

    public function image(AiModel $model, string $prompt, string $feature, array $options = [], ?AiContext $ctx = null): AiResponse
    {
        $ctx ??= $this->contexts->forTenant();
        $this->authorize($model, $feature, $ctx, 'images');

        return $this->measure($model, $feature, $ctx, fn () => $this->manager->for($model)->image($model->model_id, $prompt, $options));
    }

    public function video(AiModel $model, string $prompt, string $feature, array $options = [], ?AiContext $ctx = null): AiResponse
    {
        $ctx ??= $this->contexts->forTenant();
        $this->authorize($model, $feature, $ctx, 'video');

        return $this->measure($model, $feature, $ctx, fn () => $this->manager->for($model)->video($model->model_id, $prompt, $options));
    }

    /** Monthly usage for a tenant. */
    public function usage(?int $tenantId): array
    {
        $q = AiUsageLog::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'ok')->where('created_at', '>=', now()->startOfMonth());

        return ['tokens' => (int) (clone $q)->selectRaw('COALESCE(SUM(tokens_in + tokens_out),0) t')->value('t'), 'images' => (int) (clone $q)->sum('images'), 'video' => (int) (clone $q)->sum('video_secs')];
    }

    private function authorize(AiModel $model, string $feature, AiContext $ctx, string $unit): void
    {
        if ($ctx->tenantId === null) {
            return;   // central (platform) calls: no tenant quota
        }
        $tenant = \App\Models\Tenant::find($ctx->tenantId);
        $s = $this->access->settings($tenant);
        if (! $s || ! $s->featureOn($feature) || ! $s->modelAllowed($model->id)) {
            throw AiException::disabled();
        }
        $used = $this->usage($ctx->tenantId);
        $quotas = ['tokens' => $s->monthly_token_quota, 'images' => $s->monthly_image_quota, 'video' => $s->monthly_video_quota];
        if ($quotas[$unit] !== null && $used[$unit] >= $quotas[$unit]) {
            $this->log($model, $feature, $ctx, new AiResponse, 0, 'quota');
            throw AiException::quota($unit);
        }
        $key = 'ai:'.$ctx->tenantId;
        if (RateLimiter::tooManyAttempts($key, (int) config('ai.rate_per_minute', 30))) {
            throw AiException::throttled();
        }
        RateLimiter::hit($key, 60);
    }

    private function measure(AiModel $model, string $feature, AiContext $ctx, callable $fn): AiResponse
    {
        $t0 = microtime(true);
        try {
            $res = $fn();
        } catch (AiException $e) {
            $this->log($model, $feature, $ctx, new AiResponse, (int) ((microtime(true) - $t0) * 1000), 'error', $e->reason);
            throw $e;
        } catch (\Throwable $e) {
            $this->log($model, $feature, $ctx, new AiResponse, (int) ((microtime(true) - $t0) * 1000), 'error', 'exception');
            throw AiException::provider($e);
        }
        $this->log($model, $feature, $ctx, $res, (int) ((microtime(true) - $t0) * 1000), 'ok');

        return $res;
    }

    private function log(AiModel $model, string $feature, AiContext $ctx, AiResponse $r, int $ms, string $status, ?string $err = null): void
    {
        $user = null;
        foreach (['tenant', 'central'] as $g) {
            if (auth($g)->check()) {
                $user = auth($g)->user();
                break;
            }
        }
        $log = new AiUsageLog(['user_type' => $user ? $user::class : null, 'user_id' => $user?->getAuthIdentifier(), 'feature' => $feature, 'ai_model_id' => $model->id,
            'tokens_in' => $r->tokensIn, 'tokens_out' => $r->tokensOut, 'images' => $r->images, 'video_secs' => $r->videoSecs,
            'est_cost' => $model->estimateCost($r->tokensIn, $r->tokensOut), 'latency_ms' => $ms, 'status' => $status, 'country' => $ctx->country, 'error' => $err]);
        $log->tenant_id = $ctx->tenantId;
        $log->save();
    }
}
