<?php

namespace App\Support\Marketing;

use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\Marketing\Suggestions\SuggestionEngine;

/** JSON shape of suggestions on the Marketing API (list / detail / status / result). Never exposes admin e-mails or customer data. */
final class SuggestionPresenter
{
    public static function summary(MarketingSuggestion $s): array
    {
        return ['id' => $s->id, 'type' => $s->type->value, 'title' => $s->title, 'status' => $s->status->value, 'priority' => $s->priority->value, 'source' => $s->source->value,
            'target' => $s->target_entity_type ? ['type' => $s->target_entity_type, 'id' => $s->target_entity_id] : null, 'version' => $s->version,
            'content_hash' => $s->content_hash, 'submitted' => $s->submitted_at !== null, 'own' => null,
            'created_at' => $s->created_at?->toIso8601String(), 'updated_at' => $s->updated_at?->toIso8601String(), 'expires_at' => $s->expires_at?->toIso8601String(),
            'approved_at' => $s->approved_at?->toIso8601String(), 'applied_at' => $s->applied_at?->toIso8601String()];
    }

    public static function detail(MarketingSuggestion $s, SuggestionEngine $engine): array
    {
        $check = $engine->warnings($s);

        return self::summary($s) + ['description' => $s->description, 'reasoning' => $s->reasoning, 'payload' => $s->payload, 'source_meta' => $s->source_meta,
            'source_data' => $s->source_data_snapshot, 'compare' => $engine->compare($s), 'validation' => ['errors' => $check['errors'], 'warnings' => $check['warnings']],
            'revisions' => $s->revisions()->get()->map(fn ($r) => ['version' => $r->version, 'content_hash' => $r->content_hash, 'editor_type' => $r->editor_type,
                'diff' => $r->diff, 'note' => $r->note, 'created_at' => $r->created_at?->toIso8601String()])->all(),
            'result' => self::resultEntity($s)];
    }

    public static function status(MarketingSuggestion $s): array
    {
        $approval = $s->validApproval();
        $decision = $s->approvals()->first();

        return ['id' => $s->id, 'status' => $s->status->value, 'version' => $s->version, 'content_hash' => $s->content_hash, 'submitted' => $s->submitted_at !== null,
            'approved' => $approval !== null, 'approval_expires_at' => $approval?->expires_at?->toIso8601String(),
            'last_decision' => $decision ? ['decision' => $decision->decision, 'version' => $decision->version, 'note' => $decision->note, 'invalidated' => $decision->invalidated_at !== null,
                'at' => $decision->created_at?->toIso8601String()] : null,
            'applied_at' => $s->applied_at?->toIso8601String(), 'rolled_back_at' => $s->rolled_back_at?->toIso8601String(), 'expires_at' => $s->expires_at?->toIso8601String(),
            'last_error' => $s->last_error];
    }

    public static function result(MarketingSuggestion $s): array
    {
        return ['id' => $s->id, 'status' => $s->status->value, 'result' => self::resultEntity($s),
            'executions' => $s->executions()->get()->map(fn (MarketingSuggestionExecution $e) => ['id' => $e->id, 'kind' => $e->kind, 'status' => $e->status->value,
                'version' => $e->version, 'before' => $e->before, 'after' => $e->after, 'warnings' => $e->warnings, 'error' => $e->error,
                'started_at' => $e->started_at?->toIso8601String(), 'finished_at' => $e->finished_at?->toIso8601String()])->all()];
    }

    private static function resultEntity(MarketingSuggestion $s): ?array
    {
        return $s->result_entity_type ? ['type' => $s->result_entity_type, 'id' => $s->result_entity_id] : null;
    }
}
