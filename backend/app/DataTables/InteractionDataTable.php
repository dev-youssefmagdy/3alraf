<?php

namespace App\DataTables;

use App\Models\Interaction;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/** Growth interactions inbox: server-side paging/search (escalated first, then newest). */
class InteractionDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Interaction::query()->orderByRaw("status = 'escalated' DESC")->orderByDesc('received_at')->orderByDesc('id');
    }

    public function columns(): array
    {
        $c = __('growth_hub.inbox.cols');

        return [
            ['data' => 'channel_label', 'name' => 'channel', 'title' => $c['channel']],
            ['data' => 'text_masked', 'title' => $c['text']],
            ['data' => 'category_label', 'name' => 'category', 'title' => $c['category']],
            ['data' => 'intent_label', 'name' => 'intent', 'title' => $c['intent']],
            ['data' => 'status_label', 'name' => 'status', 'title' => $c['status']],
            ['data' => 'when', 'name' => 'received_at', 'title' => $c['when']],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $g = fn ($k) => __('growth_hub.inbox.'.$k);
        $can = (bool) auth('tenant')->user()?->can('growth.manage');
        $dt->addColumn('channel_label', fn (Interaction $r) => e($g('channels')[$r->channel] ?? $r->channel))
            ->editColumn('text_masked', function (Interaction $r) use ($g) {
                $h = e($r->text_masked);
                $h .= $r->opted_out ? ' <span class="badge badge-danger">'.e($g('opted_out')).'</span>' : '';
                $h .= $r->window_expires_at && $r->window_expires_at->isPast() ? ' <span class="badge badge-warning">'.e($g('window_closed')).'</span>' : '';
                $h .= $r->escalation_reason ? ' <span class="badge badge-danger">'.e($g('escalations')[$r->escalation_reason] ?? $r->escalation_reason).'</span>' : '';
                $h .= $r->suggested_reply ? '<p class="mt-1 rounded bg-slate-50 p-2 text-xs">'.e($r->suggested_reply).'</p>' : '';

                return $h;
            })
            ->addColumn('category_label', fn (Interaction $r) => $r->category ? e($g('categories')[$r->category] ?? $r->category) : '-')
            ->addColumn('intent_label', fn (Interaction $r) => $r->intent ? e($g('intents')[$r->intent] ?? $r->intent) : '-')
            ->addColumn('status_label', fn (Interaction $r) => e($g('statuses')[$r->status] ?? $r->status))
            ->addColumn('when', fn (Interaction $r) => '<span dir="ltr">'.$r->received_at?->format('Y-m-d H:i').'</span>')
            ->addColumn('actions', function (Interaction $r) use ($can, $g) {
                if (! $can) {
                    return '';
                }
                $h = '';
                foreach (['suggest', 'responded', 'close'] as $a) {
                    $h .= '<button type="button" class="btn-sm btn-secondary me-1" data-gpost="'.e(route('tenant.growth.interactions.action', $r->id).'?action='.$a).'">'.e($g($a)).'</button>';
                }

                return $h;
            });
    }

    protected function rawColumns(): array
    {
        return ['text_masked', 'when', 'actions'];
    }
}
