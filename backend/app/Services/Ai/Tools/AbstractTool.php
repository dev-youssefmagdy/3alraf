<?php

namespace App\Services\Ai\Tools;

use App\DTOs\Reports\ReportFilter;
use App\Services\Ai\AiContext;
use App\Services\Ai\Contracts\AiTool;
use Carbon\Carbon;

/**
 * Base of every chat tool. Read-only by construction (no tool writes anything), tenant-bound: the context tenant must be the
 * current tenant, and all data is read through tenant-scoped models / ReportService (docs/CALCULATIONS.md stays the single source of math).
 */
abstract class AbstractTool implements AiTool
{
    public const MAX_ROWS = 25;

    final public function execute(array $arguments, AiContext $context): array
    {
        if ($context->tenantId === null || $context->tenantId !== current_tenant()?->id) {
            throw new \RuntimeException('tool_no_tenant');
        }

        return $this->run($arguments, $context);
    }

    abstract protected function run(array $a, AiContext $ctx): array;

    protected function limit(array $a, int $default = 10): int
    {
        return max(1, min(self::MAX_ROWS, (int) ($a['limit'] ?? $default)));
    }

    /** Untrusted text (product / customer / review) is wrapped and capped so the model treats it as data. */
    protected function data(mixed $text, int $max = 300): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        $t = str_ireplace(['<data>', '</data>', '<data', '</data'], '', strip_tags((string) $text));

        return '<data>'.mb_substr(trim(preg_replace('/\s+/u', ' ', $t)), 0, $max).'</data>';
    }

    /** "Ahmed Ali" -> "A*** A***": enough to recognise, nothing to leak. */
    protected function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return implode(' ', array_map(fn ($p) => mb_substr($p, 0, 1).'***', array_slice(preg_split('/\s+/u', trim($name)), 0, 3)));
    }

    protected function filter(array $a, AiContext $ctx, int $defaultDays = 30): ReportFilter
    {
        [$from, $to] = $this->range($a, $ctx, $defaultDays);

        return new ReportFilter($from, $to, false, [$ctx->tenantId]);
    }

    /** @return array{0:string,1:string} */
    protected function range(array $a, AiContext $ctx, int $defaultDays = 30): array
    {
        $tz = $ctx->timezone ?: 'UTC';
        $today = Carbon::now($tz)->startOfDay();
        $period = (string) ($a['period'] ?? '');
        try {
            [$from, $to] = match ($period) {
                'today' => [$today, $today],
                'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
                '7d' => [$today->copy()->subDays(6), $today],
                '30d' => [$today->copy()->subDays(29), $today],
                '90d' => [$today->copy()->subDays(89), $today],
                'this_month' => [$today->copy()->startOfMonth(), $today],
                'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
                default => [isset($a['from']) ? Carbon::parse($a['from'], $tz) : $today->copy()->subDays($defaultDays - 1), isset($a['to']) ? Carbon::parse($a['to'], $tz) : $today],
            };
        } catch (\Throwable) {
            [$from, $to] = [$today->copy()->subDays($defaultDays - 1), $today];
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    protected function periodSchema(): array
    {
        return [
            'period' => ['type' => 'string', 'enum' => ['today', 'yesterday', '7d', '30d', '90d', 'this_month', 'last_month'], 'description' => 'Preset range in the store timezone; or use from/to.'],
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'], 'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        ];
    }

    protected function obj(array $props, array $required = []): array
    {
        return ['type' => 'object', 'properties' => $props ?: (object) [], 'required' => $required];
    }
}
