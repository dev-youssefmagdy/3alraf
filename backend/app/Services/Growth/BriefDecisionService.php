<?php

namespace App\Services\Growth;

use App\Models\GrowthBriefDecision;
use Illuminate\Validation\ValidationException;

/** Persists what the owner did with a brief recommendation (accept / dismiss / done + before/after metric) so the loop learns. */
class BriefDecisionService
{
    public function __construct(private ActionLogger $log, private ExperimentService $experiments) {}

    /** @param array{recommendation_key:string,status:string,kind?:?string,owner?:?string,note?:?string,evidence?:?array,metric_before?:mixed,metric_after?:mixed,outcome_metric?:?string,create_experiment?:bool} $d */
    public function decide(array $d, ?int $adminId): GrowthBriefDecision
    {
        if (! in_array($d['status'], GrowthBriefDecision::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('growth_hub.decisions.bad_status')]);
        }
        $row = GrowthBriefDecision::firstOrNew(['recommendation_key' => $d['recommendation_key']]);
        $evidence = $d['evidence'] ?? $row->evidence;
        $first = $evidence[0] ?? null;
        $row->fill([
            'status' => $d['status'], 'kind' => $d['kind'] ?? $row->kind, 'owner' => isset($d['owner']) ? mb_substr($d['owner'], 0, 100) : $row->owner,
            'note' => isset($d['note']) ? mb_substr($d['note'], 0, 500) : $row->note, 'decided_by' => $adminId, 'evidence' => $evidence,
            'outcome_metric' => $d['outcome_metric'] ?? $row->outcome_metric ?? ($first ? ($first['source'].'.'.$first['metric']) : null),
        ]);
        if (! $row->exists && $row->metric_before === null) { // the "before" figure is captured when the recommendation is first decided
            $row->metric_before = $d['metric_before'] ?? (is_numeric($first['value'] ?? null) ? $first['value'] : null);
        } elseif (isset($d['metric_before'])) {
            $row->metric_before = $d['metric_before'];
        }
        if ($d['status'] === 'done' && isset($d['metric_after']) && $d['metric_after'] !== '') {
            $row->metric_after = $d['metric_after'];
        }
        if ($d['status'] === 'accepted' && ! empty($d['create_experiment']) && ! $row->experiment_id) {
            $exp = $this->experiments->create(['name' => mb_substr('Brief: '.($row->kind ?: $row->recommendation_key), 0, 150), 'hypothesis' => $row->note, 'objective' => $row->kind, 'metric' => $row->outcome_metric, 'status' => 'draft'], $adminId);
            $row->experiment_id = $exp->id;
        }
        $row->save();
        $this->log->log('brief.decision.'.$row->status, 'admin', $adminId, $row->note, $row, 'ok', ['key' => $row->recommendation_key, 'experiment_id' => $row->experiment_id]);

        return $row;
    }

    /** @return array<string,GrowthBriefDecision> keyed by recommendation key */
    public function map(): array
    {
        return GrowthBriefDecision::get()->keyBy('recommendation_key')->all();
    }
}
