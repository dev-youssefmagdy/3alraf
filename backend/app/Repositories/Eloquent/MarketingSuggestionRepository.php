<?php

namespace App\Repositories\Eloquent;

use App\Enums\Marketing\SuggestionStatus;
use App\Models\MarketingSuggestion;
use App\Repositories\Contracts\MarketingSuggestionRepositoryInterface;
use Illuminate\Support\Collection;

class MarketingSuggestionRepository extends BaseRepository implements MarketingSuggestionRepositoryInterface
{
    protected string $model = MarketingSuggestion::class;

    public function lockForUpdate(int $id): MarketingSuggestion
    {
        return MarketingSuggestion::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function openValues(): array
    {
        return array_map(fn ($s) => $s->value, SuggestionStatus::open());
    }

    public function openDuplicate(string $type, string $contentHash, ?int $exceptId = null): ?MarketingSuggestion
    {
        return MarketingSuggestion::query()->where('type', $type)->where('content_hash', $contentHash)->whereIn('status', $this->openValues())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->latest('id')->first();
    }

    public function recentlyRejected(string $type, string $contentHash, int $days): ?MarketingSuggestion
    {
        return MarketingSuggestion::query()->where('type', $type)->where('content_hash', $contentHash)->where('status', SuggestionStatus::REJECTED->value)
            ->where('rejected_at', '>=', now()->subDays($days))->latest('id')->first();
    }

    public function dueForExpiry(): Collection
    {
        return MarketingSuggestion::query()->whereIn('status', array_diff($this->openValues(), [SuggestionStatus::APPLYING->value]))
            ->whereNotNull('expires_at')->where('expires_at', '<', now())->get();
    }

    public function counts(array $types = []): array
    {
        $q = fn () => MarketingSuggestion::query()->when($types, fn ($w) => $w->whereIn('type', $types));

        return [
            'total' => $q()->count(),
            'by_status' => $q()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v)->all(),
            'by_type' => $q()->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    public function filtered(array $f): \Illuminate\Database\Eloquent\Builder
    {
        $q = MarketingSuggestion::query();
        ($f['types'] ?? []) && $q->whereIn('type', (array) $f['types']);
        ($f['status'] ?? null) && $q->where('status', $f['status']);
        ($f['priority'] ?? null) && $q->where('priority', $f['priority']);
        ($f['target_entity_type'] ?? null) && $q->where('target_entity_type', $f['target_entity_type']);
        ($f['target_entity_id'] ?? null) && $q->where('target_entity_id', (int) $f['target_entity_id']);
        ($f['token_id'] ?? null) && $q->where('created_by_token_id', (int) $f['token_id']);
        ($f['source'] ?? null) && $q->where('source', $f['source']);
        if ($s = trim((string) ($f['search'] ?? ''))) {
            $q->where(fn ($w) => $w->where('title', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('id', ctype_digit($s) ? (int) $s : 0));
        }
        ($f['from'] ?? null) && $q->where('created_at', '>=', $f['from'].' 00:00:00');
        ($f['to'] ?? null) && $q->where('created_at', '<=', $f['to'].' 23:59:59');
        if ($pid = (int) ($f['product_id'] ?? 0)) {
            $q->where(fn ($w) => $w->where(fn ($x) => $x->where('target_entity_type', 'product')->where('target_entity_id', $pid))
                ->orWhereJsonContains('payload->items', ['product_id' => $pid])->orWhereJsonContains('payload->product_ids', $pid));
        }
        if ($cid = (int) ($f['category_id'] ?? 0)) {
            $cat = \App\Models\Category::query()->find($cid);
            $ids = $cat ? \App\Models\Category::query()->where('id', $cid)->orWhere('path', 'like', $cat->path.'/%')->pluck('id')->all() : [$cid];
            $q->where(fn ($w) => $w->where(fn ($x) => $x->where('target_entity_type', 'category')->whereIn('target_entity_id', $ids))
                ->orWhere(fn ($x) => $x->where('target_entity_type', 'product')->whereIn('target_entity_id', \Illuminate\Support\Facades\DB::table('category_product')->select('product_id')->whereIn('category_id', $ids))));
        }
        match ($f['sort'] ?? 'newest') {
            'oldest' => $q->orderBy('id'),
            'updated' => $q->latest('updated_at')->latest('id'),
            'priority' => $q->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")->latest('id'),
            default => $q->latest('id'),
        };

        return $q;
    }
}
