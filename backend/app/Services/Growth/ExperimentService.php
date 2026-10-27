<?php

namespace App\Services\Growth;

use App\Models\GrowthExperiment;
use App\Models\GrowthExperimentResult;
use App\Repositories\Contracts\GrowthExperimentRepositoryInterface;
use Illuminate\Validation\ValidationException;

/** Experiment & outcome log. Budget caps are bounded by the tenant guardrail; a cap is a ceiling, never a spend instruction. */
class ExperimentService
{
    public const STATUSES = ['draft', 'running', 'paused', 'completed', 'cancelled'];
    public const OUTCOMES = ['win', 'loss', 'inconclusive'];

    public function __construct(private ActionLogger $log, private GrowthConfig $config, private GrowthExperimentRepositoryInterface $repo) {}

    public function find(int $id): GrowthExperiment
    {
        return $this->repo->findWithResults($id);
    }

    public function create(array $d, ?int $adminId): GrowthExperiment
    {
        $this->check($d);
        $e = $this->repo->create($this->clean($d) + ['created_by' => $adminId]);
        $this->log->log('experiment.created', 'admin', $adminId, null, $e);

        return $e;
    }

    public function update(GrowthExperiment $e, array $d, ?int $adminId): GrowthExperiment
    {
        $this->check($d);
        $e = $this->repo->update($e, $this->clean($d));
        $this->log->log('experiment.updated', 'admin', $adminId, null, $e, 'ok', ['status' => $e->status, 'outcome' => $e->outcome]);

        return $e;
    }

    public function delete(GrowthExperiment $e, ?int $adminId): void
    {
        $this->log->log('experiment.deleted', 'admin', $adminId, null, $e);
        $this->repo->delete($e);
    }

    public function addResult(GrowthExperiment $e, array $d, string $source = 'admin', ?int $actorId = null): GrowthExperimentResult
    {
        $r = $e->results()->create([
            'recorded_on' => $d['recorded_on'] ?? now()->toDateString(), 'metric' => mb_substr((string) $d['metric'], 0, 100), 'value' => $d['value'] ?? null,
            'spend' => $d['spend'] ?? null, 'note' => isset($d['note']) ? mb_substr(PiiScrubber::text((string) $d['note']), 0, 300) : null, 'source' => $source, 'tenant_id' => $e->tenant_id,
        ]);
        $this->log->log('experiment.result', $source === 'admin' ? 'admin' : 'n8n', $actorId, null, $e, 'ok', ['metric' => $r->metric]);

        return $r;
    }

    private function check(array $d): void
    {
        $err = [];
        if (! empty($d['starts_on']) && ! empty($d['ends_on']) && $d['ends_on'] < $d['starts_on']) {
            $err['ends_on'] = __('growth_hub.experiments.dates');
        }
        $cap = (float) $this->config->get('ad_total_budget_cap', 0);
        if (isset($d['budget_cap']) && $d['budget_cap'] !== '' && $cap > 0 && (float) $d['budget_cap'] > $cap) {
            $err['budget_cap'] = __('growth_hub.experiments.over_cap', ['cap' => $cap]);
        }
        if (($d['status'] ?? null) === 'completed' && empty($d['outcome'])) {
            $err['outcome'] = __('growth_hub.experiments.outcome_required');
        }
        if ($err) {
            throw ValidationException::withMessages($err);
        }
    }

    private function clean(array $d): array
    {
        $o = collect($d)->only(['name', 'hypothesis', 'objective', 'metric', 'starts_on', 'ends_on', 'budget_cap', 'status', 'outcome', 'learnings'])->map(fn ($v) => $v === '' ? null : $v)->all();
        $o['status'] = in_array($o['status'] ?? 'draft', self::STATUSES, true) ? ($o['status'] ?? 'draft') : 'draft';

        return $o;
    }
}
