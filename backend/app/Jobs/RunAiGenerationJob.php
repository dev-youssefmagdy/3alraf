<?php

namespace App\Jobs;

use App\Enums\AiGenerationStatus;
use App\Exceptions\AiException;
use App\Models\AiGeneration;
use App\Models\Tenant;
use App\Enums\AiGenerationType;
use App\Services\Ai\GeneratorService;
use App\Services\Ai\ProductEditorService;
use App\Services\Ai\StudioService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Runs an ai_generations row off the request. stage main = text(+image); stage video = async video, polled by the UI. */
class RunAiGenerationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(public int $generationId, public int $tenantId, public string $stage = 'main') {}

    public function backoff(): array
    {
        return [30];
    }

    public function uniqueId(): string
    {
        return "ai-gen-{$this->generationId}-{$this->stage}";
    }

    public function handle(StudioService $studio): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        TenantContext::run($tenant, function () use ($studio) {
            $g = AiGeneration::find($this->generationId);   // tenant scoped: another tenant's id is simply not found
            if (! $g) {
                return;
            }
            try {
                if (in_array($g->type, GeneratorService::TYPES, true)) {
                    if ($g->status === AiGenerationStatus::QUEUED) {
                        app(GeneratorService::class)->generate($g);
                    }
                } elseif ($g->type === AiGenerationType::PRODUCT_EDIT) {
                    if ($g->status === AiGenerationStatus::QUEUED) {
                        app(ProductEditorService::class)->generate($g);
                    }
                } elseif ($this->stage === 'video') {
                    $studio->generateVideo($g);
                } elseif ($g->status === AiGenerationStatus::QUEUED) {
                    $studio->generate($g);
                }
            } catch (AiException $e) {
                $this->serviceFor($g)->fail($g, $e->getMessage());   // user-safe message; retrying would burn tokens again
            }
        });
    }

    private function serviceFor(AiGeneration $g): object
    {
        return match (true) {
            $g->type === AiGenerationType::PRODUCT_EDIT => app(ProductEditorService::class),
            in_array($g->type, GeneratorService::TYPES, true) => app(GeneratorService::class),
            default => app(StudioService::class),
        };
    }

    public function failed(\Throwable $e): void
    {
        report($e);
        $tenant = Tenant::find($this->tenantId);
        if ($tenant) {
            TenantContext::run($tenant, function () {
                $g = AiGeneration::find($this->generationId);
                if ($g && $this->stage === 'main') {
                    $this->serviceFor($g)->fail($g, __('ai.errors.provider'));
                }
            });
        }
    }
}
