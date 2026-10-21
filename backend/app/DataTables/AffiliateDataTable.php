<?php

namespace App\DataTables;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class AffiliateDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Affiliate::query()->withCount(['clicks', 'orders'])
            ->withSum(['commissions as pending_sum' => fn ($q) => $q->where('status', 'pending')], 'amount')
            ->withSum(['commissions as approved_sum' => fn ($q) => $q->where('status', 'approved')], 'amount')
            ->withSum(['commissions as paid_sum' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('admin.fields.name')],
            ['data' => 'email', 'title' => __('admin.fields.email')],
            ['data' => 'clicks_count', 'title' => __('growth.clicks'), 'searchable' => false],
            ['data' => 'orders_count', 'title' => __('orders.fields.orders'), 'searchable' => false],
            ['data' => 'pending_sum', 'title' => __('growth.commission_pending'), 'searchable' => false, 'orderable' => false],
            ['data' => 'approved_sum', 'title' => __('growth.commission_approved'), 'searchable' => false, 'orderable' => false],
            ['data' => 'paid_sum', 'title' => __('growth.commission_paid'), 'searchable' => false, 'orderable' => false],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('tenant')->user();
        $dt->editColumn('pending_sum', fn (Affiliate $m) => $this->money($m->pending_sum ?? 0))
            ->editColumn('approved_sum', fn (Affiliate $m) => $this->money($m->approved_sum ?? 0))
            ->editColumn('paid_sum', fn (Affiliate $m) => $this->money($m->paid_sum ?? 0))
            ->addColumn('status_label', fn (Affiliate $m) => '<span class="badge '.($m->status === AffiliateStatus::APPROVED ? 'badge-success' : ($m->status === AffiliateStatus::PENDING ? 'badge-warn' : 'badge-muted')).'">'.e($m->status->label()).'</span>')
            ->addColumn('actions', function (Affiliate $m) use ($u) {
                $h = '<div class="flex items-center gap-2">';
                if ($u?->can('affiliates.edit')) {
                    if ($m->status !== AffiliateStatus::APPROVED) {
                        $h .= '<button type="button" data-post-action="'.e(route('tenant.affiliates.approve', $m->id)).'" class="btn-sm btn-secondary">'.e(__('growth.approve')).'</button>';
                    }
                    if ($m->status === AffiliateStatus::APPROVED) {
                        $h .= '<button type="button" data-post-action="'.e(route('tenant.affiliates.suspend', $m->id)).'" class="btn-sm btn-secondary">'.e(__('growth.suspend')).'</button>';
                    }
                }
                if ($u?->can('affiliates.delete')) {
                    $h .= '<button type="button" data-delete="'.e(route('tenant.affiliates.destroy', $m->id)).'" class="btn-sm btn-danger">'.e(__('admin.delete')).'</button>';
                }

                return $h.'</div>';
            });
    }

    protected function rawColumns(): array
    {
        return ['status_label', 'actions'];
    }
}
