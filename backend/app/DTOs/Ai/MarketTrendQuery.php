<?php

namespace App\DTOs\Ai;

use App\Http\Requests\Tenant\AiMarketTrendRequest;

final readonly class MarketTrendQuery
{
    /** @param array<int,string> $countries sorted ISO-2 */
    public function __construct(public array $countries, public int $period, public string $keyword, public int $count, public ?int $modelId = null, public bool $force = false) {}

    public static function fromRequest(AiMarketTrendRequest $r): self
    {
        $c = array_values(array_unique(array_map('strtoupper', (array) $r->input('countries', []))));
        sort($c);

        return new self($c, (int) ($r->input('period') ?: 30), trim((string) $r->input('keyword', '')), (int) ($r->input('count') ?: 10), $r->integer('ai_model_id') ?: null, $r->boolean('force'));
    }

    /** Snapshot cache key: sorted countries + period + keyword + count (tenant isolation comes from the snapshot's tenant scope). */
    public function key(): string
    {
        return substr(sha1(implode(',', $this->countries).'|'.$this->period.'|'.mb_strtolower($this->keyword).'|'.$this->count), 0, 20);
    }
}
