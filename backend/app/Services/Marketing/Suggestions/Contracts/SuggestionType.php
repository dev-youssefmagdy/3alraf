<?php

namespace App\Services\Marketing\Suggestions\Contracts;

use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\Marketing\Suggestions\ApplyResult;
use Illuminate\Database\Eloquent\Model;

/**
 * One suggestion type = one explicit schema + one explicit application handler (MCP.md §23). Registered in SuggestionTypeRegistry.
 * The engine (SuggestionEngine) owns lifecycle, hashing, approvals, idempotency and logging; a handler never changes suggestion status.
 * Every change to store data goes through the store's own services (ProductService, BundleService, ...), never raw column writes.
 */
interface SuggestionType
{
    public function key(): SuggestionTypeKey;

    /** Allowed target_entity_type values ([] = the type never has a target). */
    public function targetTypes(): array;

    /** Target required on create (e.g. product, seo) or optional (bundle create vs update). */
    public function targetRequired(?array $payload = null): bool;

    /**
     * Laravel rules for the payload, keys prefixed `payload.` (also the allow-list of fields: anything else is rejected as unknown).
     * $targetType lets a type vary its schema per target (SEO).
     */
    public function rules(?string $targetType = null): array;

    /** Canonical payload (trimmed, sanitised HTML, normalised colours / links, defaults, sorted sets). Must be idempotent. */
    public function normalize(array $payload, ?string $targetType = null): array;

    /** The target model (null when none or not found; soft-deleted rows are "not found"). */
    public function resolveTarget(?string $type, ?int $id): ?Model;

    /** Business validation (margins, duplicates, existence). @return array{errors: array<string, string>, warnings: list<string>} */
    public function check(array $payload, ?Model $target, ?MarketingSuggestion $s = null): array;

    /** Current values of what this suggestion would change (stale detection); null = nothing to compare. */
    public function snapshot(?Model $target, array $payload): ?array;

    /** Rows for the compare view: list<array{field:string,label:string,current:mixed,proposed:mixed}>. */
    public function compare(MarketingSuggestion $s, ?Model $target): array;

    /** Apply (inside a DB transaction opened by the engine). Throw SuggestionException to refuse. */
    public function apply(MarketingSuggestion $s, ?Model $target, array $options, ?int $adminId): ApplyResult;

    public function supportsRollback(): bool;

    /** Undo an applied execution where practical; returns the restored values. */
    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array;

    /** Extra permission needed to approve / apply (besides ai_suggestions.approve / .apply), e.g. ai_seo.approve. */
    public function approvePermission(): ?string;

    /** False for schema-only types whose handler arrives later (P6: social_content, media_asset). */
    public function applicable(): bool;

    /** Apply-time options shown on the apply form: name => ['kind' => 'bool', 'label' => lang key]. */
    public function applyOptions(): array;

    /** Panel edit form fields: list<array{name:string, kind:string, label:string, options?:array}> (payload paths with dots). */
    public function editFields(?string $targetType = null): array;
}
