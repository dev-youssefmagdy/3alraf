<?php

namespace App\DataTables;

use App\Models\MissedOpportunity;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/** Missed-opportunity list: server-side paging/search; quick status buttons (owner/due/note edit via the modal on the page). */
class MissedOpportunityDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return MissedOpportunity::query()->orderByRaw("FIELD(status,'open','accepted','done','dismissed')")->orderByRaw("FIELD(confidence,'high','medium','low')")->latest('id');
    }

    public function columns(): array
    {
        $m = fn ($k) => __('growth_hub.missed.'.$k);

        return [
            ['data' => 'title', 'title' => $m('title')],
            ['data' => 'detail', 'title' => $m('evidence'), 'orderable' => false, 'searchable' => false],
            ['data' => 'confidence', 'title' => $m('confidence')],
            ['data' => 'status_label', 'name' => 'status', 'title' => __('admin.status')],
            ['data' => 'owner', 'title' => $m('owner')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $m = fn ($k) => __('growth_hub.missed.'.$k);
        $can = (bool) auth('tenant')->user()?->can('growth.manage');
        $dt->editColumn('title', fn (MissedOpportunity $o) => e($o->title))
            ->addColumn('detail', function (MissedOpportunity $o) use ($m) {
                $ev = collect($o->evidence)->map(fn ($e) => e($e['metric'].' = '.$e['value'].' ('.$e['period'].')'))->implode(', ');

                return '<div class="text-sm"><b>'.e($m('evidence')).':</b> '.$ev.'</div><div class="text-sm"><b>'.e($m('cause')).':</b> '.e($o->probable_cause).'</div><div class="text-sm"><b>'.e($m('experiment')).':</b> '.e($o->suggestion).'</div>';
            })
            ->addColumn('status_label', fn (MissedOpportunity $o) => e($m('statuses')[$o->status] ?? $o->status))
            ->editColumn('owner', fn (MissedOpportunity $o) => e($o->owner ?? '-'))
            ->addColumn('actions', function (MissedOpportunity $o) use ($can, $m) {
                if (! $can) {
                    return '';
                }
                $h = '<button type="button" class="btn-sm btn-primary me-1 mb-1" data-missed-edit data-url="'.e(route('tenant.growth.missed.update', $o->id)).'" data-status="'.e($o->status).'" data-owner="'.e((string) $o->owner).'" data-due="'.e($o->due_on?->toDateString() ?? '').'" data-note="'.e((string) $o->result_note).'">'.e(__('admin.edit')).'</button>';
                foreach ($m('statuses') as $k => $label) {
                    if ($k !== $o->status) {
                        $h .= '<button type="button" class="btn-sm btn-secondary me-1 mb-1" data-gpost="'.e(route('tenant.growth.missed.status', $o->id).'?status='.$k).'">'.e($label).'</button>';
                    }
                }

                return $h;
            });
    }

    protected function rawColumns(): array
    {
        return ['detail', 'actions'];
    }
}
