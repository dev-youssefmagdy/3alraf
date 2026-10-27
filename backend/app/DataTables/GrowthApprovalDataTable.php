<?php

namespace App\DataTables;

use App\Models\GrowthApproval;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class GrowthApprovalDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return GrowthApproval::query()->with('decider')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'id', 'title' => '#'],
            ['data' => 'type', 'title' => __('growth_hub.approvals.type')],
            ['data' => 'title', 'title' => __('growth_hub.approvals.title')],
            ['data' => 'details', 'title' => __('growth_hub.approvals.details'), 'orderable' => false, 'searchable' => false],
            ['data' => 'requested_by', 'title' => __('growth_hub.approvals.requested_by')],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'title' => __('growth_hub.events.created')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $can = (bool) auth('tenant')->user()?->can('growth.approve');
        $cls = ['pending' => 'badge-warning', 'approved' => 'badge-success', 'rejected' => 'badge-muted', 'expired' => 'badge-muted'];
        $dt->addColumn('details', fn (GrowthApproval $a) => '<code class="text-xs">'.e(\Illuminate\Support\Str::limit(json_encode($a->payload, JSON_UNESCAPED_UNICODE), 140)).'</code>'.($a->reason ? '<div class="text-xs text-slate-500">'.e($a->reason).'</div>' : ''))
            ->editColumn('title', fn (GrowthApproval $a) => e($a->title))
            ->editColumn('requested_by', fn (GrowthApproval $a) => e($a->requested_by ?? '—'))
            ->addColumn('status_label', fn (GrowthApproval $a) => '<span class="badge '.($cls[$a->status] ?? 'badge-muted').'">'.e(__('growth_hub.approvals.statuses.'.$a->status)).'</span>')
            ->editColumn('created_at', fn (GrowthApproval $a) => $a->created_at?->format('Y-m-d H:i'))
            ->addColumn('actions', fn (GrowthApproval $a) => $can && $a->isPending()
                ? '<div class="flex gap-2"><button type="button" class="btn-sm btn-primary" data-gpost="'.e(route('tenant.growth.approvals.approve', $a->id)).'" data-confirm="'.e(__('growth_hub.approvals.approve_confirm')).'">'.e(__('growth_hub.approvals.approve')).'</button>'
                    .'<button type="button" class="btn-sm btn-danger" data-gpost="'.e(route('tenant.growth.approvals.reject', $a->id)).'" data-reason="1">'.e(__('growth_hub.approvals.reject')).'</button></div>' : '');
    }

    protected function rawColumns(): array
    {
        return ['status_label', 'details', 'actions'];
    }
}
