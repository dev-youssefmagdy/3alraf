<?php

namespace App\Services\Growth;

use App\Models\ImprovementTask;
use App\Models\Interaction;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/** Voice of Customer: aggregation by product / channel / category, recurring-objection detection and Objection-to-Action rules -> improvement tasks. */
class VocService
{
    public const OBJECTIONS = ['price', 'shipping', 'trust', 'photos', 'size_color', 'returns', 'usage'];
    public const RECURRING_MIN = 3;
    public const TASK_STATUSES = ['open', 'doing', 'done', 'dismissed'];

    public function __construct(private ActionLogger $log) {}

    public function aggregate(int $days = 30): array
    {
        $rows = Interaction::where('received_at', '>=', now()->subDays($days))->get(['id', 'product_id', 'channel', 'category', 'sentiment', 'intent']);
        $count = fn ($g) => $g->map->count()->sortDesc()->all();
        $names = Product::whereIn('id', $rows->pluck('product_id')->filter()->unique())->get()->mapWithKeys(fn ($p) => [$p->id => $p->getTranslation('name', app()->getLocale(), true)]);

        return [
            'days' => $days, 'total' => $rows->count(),
            'by_category' => $count($rows->whereNotNull('category')->groupBy('category')),
            'by_channel' => $count($rows->groupBy('channel')),
            'by_sentiment' => $count($rows->whereNotNull('sentiment')->groupBy('sentiment')),
            'by_product' => $rows->whereNotNull('product_id')->groupBy('product_id')->map(fn ($g, $pid) => ['product_id' => (int) $pid, 'name' => $names[$pid] ?? '#'.$pid, 'count' => $g->count(), 'negative' => $g->where('sentiment', 'negative')->count(),
                'top_category' => $g->whereNotNull('category')->groupBy('category')->map->count()->sortDesc()->keys()->first()])->sortByDesc('count')->take(20)->values()->all(),
            'recurring_objections' => $this->recurring($days),
        ];
    }

    /** product x objection category with at least RECURRING_MIN mentions. */
    public function recurring(int $days = 30): array
    {
        return Interaction::whereNotNull('product_id')->whereIn('category', self::OBJECTIONS)->where('received_at', '>=', now()->subDays($days))
            ->selectRaw('product_id, category, COUNT(*) c')->groupBy('product_id', 'category')->havingRaw('COUNT(*) >= ?', [self::RECURRING_MIN])->orderByDesc('c')->get()
            ->map(fn ($r) => ['product_id' => (int) $r->product_id, 'category' => $r->category, 'count' => (int) $r->c])->all();
    }

    public function count(int $productId, string $topic, int $days = 30): int
    {
        return Interaction::where('product_id', $productId)->where('category', $topic)->where('received_at', '>=', now()->subDays($days))->count();
    }

    /** Objection-to-Action: one open task per product + topic. @return int created */
    public function generateTasks(): int
    {
        $n = 0;
        foreach ($this->recurring() as $r) {
            if (ImprovementTask::where('product_id', $r['product_id'])->where('topic', $r['category'])->whereIn('status', ['open', 'doing'])->exists()) {
                continue;
            }
            $name = Product::whereKey($r['product_id'])->first()?->getTranslation('name', app()->getLocale(), true) ?? '#'.$r['product_id'];
            $t = ImprovementTask::create(['product_id' => $r['product_id'], 'topic' => $r['category'], 'rule' => 'recurring_objection', 'title' => mb_substr(__('growth_hub.voc.actions.'.$r['category'], ['product' => $name]), 0, 200),
                'due_on' => now()->addDays(14)->toDateString(), 'status' => 'open', 'evidence' => [['source' => 'interactions', 'metric' => 'mentions_30d', 'value' => $r['count'], 'period' => '30d']], 'before_value' => $r['count']]);
            $this->log->log('voc.task_created', 'system', null, null, $t);
            $n++;
        }

        return $n;
    }

    public function updateTask(int $id, array $d, ?int $adminId): ImprovementTask
    {
        $t = ImprovementTask::findOrFail($id);
        if (isset($d['status']) && ! in_array($d['status'], self::TASK_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('growth_hub.voc.bad_status')]);
        }
        $t->fill(array_intersect_key($d, array_flip(['status', 'owner', 'due_on'])));
        if ($t->status === 'done' && $t->after_value === null && $t->product_id) {
            $t->after_value = $this->count($t->product_id, $t->topic); // objection mentions in the last 30d at completion (compare with before_value later)
        }
        $t->save();
        $this->log->log('voc.task_updated', 'admin', $adminId, null, $t, 'ok', ['status' => $t->status]);

        return $t;
    }
}
